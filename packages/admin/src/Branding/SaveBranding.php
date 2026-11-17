<?php

declare(strict_types=1);

namespace LynxCms\Admin\Branding;

use finfo;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Models\Setting;

/**
 * Saves the admin's branding (ADR-0084): the site's name, logo and colours on the sign-in screens
 * and the sidebar, and a logo and colours of their own for dark mode. Superusers only. A key left out stays as it is; an empty one goes back to the
 * config default. The logo comes as a data URL (PNG, JPEG, WebP or SVG, up to 1 MB) and is kept in
 * private storage. Files are written and deleted once the change is committed, so a rollback or a
 * dry run leaves storage as it was.
 *
 * @api
 */
final class SaveBranding extends Action
{
    private const int MAX_LOGO_BYTES = 1_048_576;

    /** A megabyte in base64, plus the data URL's header. */
    private const int MAX_LOGO_CHARACTERS = 1_400_100;

    /** The colour keys; each *_dark one is what dark mode shows instead. */
    private const array COLOURS = ['background', 'accent', 'background_dark', 'accent_dark'];

    /** The logo keys: the logo, and the one dark mode shows instead. */
    private const array LOGOS = ['logo', 'logo_dark'];

    private const array EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];

    /** What finfo may call an SVG, which is XML text. */
    private const array SVG_TYPES = ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html'];

    public static function name(): string
    {
        return 'admin.branding';
    }

    public static function description(): string
    {
        return 'Change the name, logo and colors the admin shows on its sign-in screens and sidebar, and those for dark mode. Leave a key out to keep it; send it empty to go back to the default.';
    }

    public static function rules(): array
    {
        $hex = ['sometimes', 'nullable', 'string', 'regex:'.Branding::HEX];
        $logo = ['sometimes', 'nullable', 'string', 'max:'.self::MAX_LOGO_CHARACTERS];

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:60'],
            ...array_fill_keys(self::COLOURS, $hex),
            ...array_fill_keys(self::LOGOS, $logo),
            'remove_logo' => ['sometimes', 'boolean'],
            'remove_logo_dark' => ['sometimes', 'boolean'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'name' => $schema->string()->description('The site\'s name, shown with or instead of the logo. Empty: the default.'),
            'background' => $schema->string()->description('The sign-in screens\' backdrop, as #rrggbb. Empty: the default.'),
            'accent' => $schema->string()->description('Buttons, links and focus rings, as #rrggbb. Empty: the default.'),
            'logo' => $schema->string()->description('A new logo as a data URL (data:image/png;base64,...): PNG, JPEG, WebP or SVG, up to 1 MB.'),
            'remove_logo' => $schema->boolean()->description('Remove the uploaded logo, going back to the default.'),
            'background_dark' => $schema->string()->description('The sign-in screens\' backdrop in dark mode, as #rrggbb. Empty: the light one.'),
            'accent_dark' => $schema->string()->description('The accent in dark mode, as #rrggbb. Empty: the light one.'),
            'logo_dark' => $schema->string()->description('A logo for dark mode as a data URL, such as a light version of the logo: PNG, JPEG, WebP or SVG, up to 1 MB.'),
            'remove_logo_dark' => $schema->boolean()->description('Remove the uploaded dark mode logo, going back to the light one.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Branding
    {
        $previous = Branding::saved();
        $saved = $previous;

        foreach (['name', ...self::COLOURS] as $key) {
            if ($input->has($key)) {
                $saved = $this->with($saved, $key, trim((string) $input->string($key, '')));
            }
        }

        foreach (self::LOGOS as $key) {
            $saved = $this->withLogo($saved, $key, $input);
        }

        Setting::write(Branding::SETTING, $saved === [] ? null : json_encode($saved, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $this->forgetLogos($previous, $saved);
        $context->changes->updated('setting', Branding::SETTING, 'Change the admin\'s branding', $saved);

        return Branding::from($saved, (array) config('lynx-admin.branding', []));
    }

    /**
     * @param  array<string, string>  $saved
     * @return array<string, string>
     */
    private function with(array $saved, string $key, string $value): array
    {
        if ($value === '') {
            unset($saved[$key]);

            return $saved;
        }

        return [...$saved, $key => $key === 'name' ? $value : strtolower($value)];
    }

    /**
     * @param  array<string, string>  $saved
     * @return array<string, string>
     */
    private function withLogo(array $saved, string $key, Input $input): array
    {
        if ($input->bool('remove_'.$key)) {
            unset($saved[$key]);
        }

        if ((string) $input->string($key, '') !== '') {
            $saved[$key] = $this->storeLogo((string) $input->string($key), $key);
        }

        return $saved;
    }

    /** Checks the image by its bytes, not its claimed type, and stores it under its hash. */
    private function storeLogo(string $dataUrl, string $key): string
    {
        $bytes = preg_match('#^data:[\w/+.-]+;base64,(.+)$#s', $dataUrl, $match) === 1 ? base64_decode($match[1], true) : false;

        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages([$key => 'Send the logo as a data URL: data:image/png;base64,...']);
        }

        if (strlen($bytes) > self::MAX_LOGO_BYTES) {
            throw ValidationException::withMessages([$key => 'The logo may be 1 MB at most.']);
        }

        $type = (string) new finfo(FILEINFO_MIME_TYPE)->buffer($bytes);
        $isSvg = in_array($type, self::SVG_TYPES, true) && preg_match('/<svg[\s>]/i', $bytes) === 1;
        $extension = $isSvg ? 'svg' : (self::EXTENSIONS[$type] ?? null);

        if ($extension === null) {
            throw ValidationException::withMessages([$key => 'The logo must be a PNG, JPEG, WebP or SVG image.']);
        }

        $file = 'logo-'.substr(hash('sha256', $bytes), 0, 16).'.'.$extension;

        DB::afterCommit(function () use ($file, $bytes): void {
            Storage::disk(Branding::DISK)->put(Branding::FOLDER.'/'.$file, $bytes);
        });

        return $file;
    }

    /**
     * Replaced logos go once the change is committed, unless the other mode shows the same file:
     * files are named by their contents, so one image can be both logos.
     *
     * @param  array<string, string>  $previous
     * @param  array<string, string>  $saved
     */
    private function forgetLogos(array $previous, array $saved): void
    {
        $inUse = array_intersect_key($saved, array_flip(self::LOGOS));
        $replaced = array_diff(array_intersect_key($previous, array_flip(self::LOGOS)), $inUse);

        foreach (array_unique($replaced) as $file) {
            DB::afterCommit(fn (): bool => Storage::disk(Branding::DISK)->delete(Branding::FOLDER.'/'.$file));
        }
    }
}
