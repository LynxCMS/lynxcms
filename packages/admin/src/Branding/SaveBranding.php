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
 * and the sidebar. Superusers only. A key left out stays as it is; an empty one goes back to the
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

    private const array EXTENSIONS = ['image/png' => 'png', 'image/jpeg' => 'jpg', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];

    /** What finfo may call an SVG, which is XML text. */
    private const array SVG_TYPES = ['image/svg+xml', 'text/xml', 'application/xml', 'text/plain', 'text/html'];

    public static function name(): string
    {
        return 'admin.branding';
    }

    public static function description(): string
    {
        return 'Change the name, logo and colours the admin shows on its sign-in screens and sidebar. Leave a key out to keep it; send it empty to go back to the default.';
    }

    public static function rules(): array
    {
        $hex = ['sometimes', 'nullable', 'string', 'regex:'.Branding::HEX];

        return [
            'name' => ['sometimes', 'nullable', 'string', 'max:60'],
            'background' => $hex,
            'accent' => $hex,
            'logo' => ['sometimes', 'nullable', 'string', 'max:'.self::MAX_LOGO_CHARACTERS],
            'remove_logo' => ['sometimes', 'boolean'],
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
        ];
    }

    public function handle(Input $input, ActionContext $context): Branding
    {
        $saved = Branding::saved();
        $previousLogo = $saved['logo'] ?? null;

        foreach (['name', 'background', 'accent'] as $key) {
            if ($input->has($key)) {
                $saved = $this->with($saved, $key, trim((string) $input->string($key, '')));
            }
        }

        if ($input->bool('remove_logo')) {
            unset($saved['logo']);
        }

        if ((string) $input->string('logo', '') !== '') {
            $saved['logo'] = $this->storeLogo((string) $input->string('logo'));
        }

        Setting::write(Branding::SETTING, $saved === [] ? null : json_encode($saved, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $this->forgetLogo($previousLogo, $saved['logo'] ?? null);
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

    /** Checks the image by its bytes, not its claimed type, and stores it under its hash. */
    private function storeLogo(string $dataUrl): string
    {
        $bytes = preg_match('#^data:[\w/+.-]+;base64,(.+)$#s', $dataUrl, $match) === 1 ? base64_decode($match[1], true) : false;

        if ($bytes === false || $bytes === '') {
            throw ValidationException::withMessages(['logo' => 'Send the logo as a data URL: data:image/png;base64,...']);
        }

        if (strlen($bytes) > self::MAX_LOGO_BYTES) {
            throw ValidationException::withMessages(['logo' => 'The logo may be 1 MB at most.']);
        }

        $type = (string) new finfo(FILEINFO_MIME_TYPE)->buffer($bytes);
        $isSvg = in_array($type, self::SVG_TYPES, true) && preg_match('/<svg[\s>]/i', $bytes) === 1;
        $extension = $isSvg ? 'svg' : (self::EXTENSIONS[$type] ?? null);

        if ($extension === null) {
            throw ValidationException::withMessages(['logo' => 'The logo must be a PNG, JPEG, WebP or SVG image.']);
        }

        $file = 'logo-'.substr(hash('sha256', $bytes), 0, 16).'.'.$extension;

        DB::afterCommit(function () use ($file, $bytes): void {
            Storage::disk(Branding::DISK)->put(Branding::FOLDER.'/'.$file, $bytes);
        });

        return $file;
    }

    /** The replaced logo goes once the change is committed. */
    private function forgetLogo(?string $previous, ?string $current): void
    {
        if ($previous !== null && $previous !== $current) {
            DB::afterCommit(fn (): bool => Storage::disk(Branding::DISK)->delete(Branding::FOLDER.'/'.$previous));
        }
    }
}
