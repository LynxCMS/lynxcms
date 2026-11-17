<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Branding\Branding;
use LynxCms\Admin\Branding\SaveBranding;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Support\Value;

/**
 * Settings > General: the site's name, logo and colours on the sign-in screens and the sidebar, and
 * those for dark mode, with a preview of the sign-in card in each mode. Saves through
 * admin.branding; an empty field is the default, and an empty dark one the light value.
 *
 * @internal
 */
final class BrandingForm extends Component
{
    use Notifies;
    use WithFileUploads;

    public string $name = '';

    public string $background = '';

    public string $accent = '';

    /** A new logo picked in the file input, until it is saved. */
    public ?TemporaryUploadedFile $logo = null;

    public bool $removeLogo = false;

    public string $backgroundDark = '';

    public string $accentDark = '';

    /** A new dark mode logo picked in the file input, until it is saved. */
    public ?TemporaryUploadedFile $logoDark = null;

    public bool $removeLogoDark = false;

    #[Locked]
    public ?string $status = null;

    /** Runs on mount and on every Livewire update, so the checks also guard /livewire/update. */
    public function boot(AdminAccess $access): void
    {
        $access->authorizeSuperuser(Value::string(__('lynx-admin::general.branding.denied')));
    }

    public function mount(): void
    {
        $saved = Branding::saved();
        $this->name = $saved['name'] ?? '';
        $this->background = $saved['background'] ?? '';
        $this->accent = $saved['accent'] ?? '';
        $this->backgroundDark = $saved['background_dark'] ?? '';
        $this->accentDark = $saved['accent_dark'] ?? '';
    }

    public function save(): void
    {
        $image = ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'];
        $hex = ['nullable', 'regex:'.Branding::HEX];
        $this->validate(['logo' => $image, 'logoDark' => $image, 'background' => $hex, 'accent' => $hex, 'backgroundDark' => $hex, 'accentDark' => $hex]);

        lynx()->run(SaveBranding::class, array_filter([
            'name' => $this->name, 'background' => $this->background, 'accent' => $this->accent,
            'background_dark' => $this->backgroundDark, 'accent_dark' => $this->accentDark,
            'remove_logo' => $this->removeLogo, 'remove_logo_dark' => $this->removeLogoDark,
            'logo' => $this->dataUrl($this->logo), 'logo_dark' => $this->dataUrl($this->logoDark),
        ], fn (string|bool|null $value): bool => $value !== null));

        $this->reset('logo', 'removeLogo', 'logoDark', 'removeLogoDark');
        $this->notify(__('lynx-admin::general.branding.saved'));
    }

    private function dataUrl(?TemporaryUploadedFile $file): ?string
    {
        return $file instanceof TemporaryUploadedFile ? 'data:'.$file->getMimeType().';base64,'.base64_encode((string) $file->get()) : null;
    }

    /** A raster logo previews before it is saved; an SVG is only ever served sandboxed, once saved. */
    private function previewUrl(?TemporaryUploadedFile $file): ?string
    {
        $isRaster = in_array(strtolower((string) $file?->getClientOriginalExtension()), ['png', 'jpg', 'jpeg', 'webp'], true);

        return $isRaster && $file?->isPreviewable() === true ? Value::string($file->temporaryUrl()) : null;
    }

    public function render(): View
    {
        $defaults = Value::map(config('lynx-admin.branding', []));
        $current = Branding::saved();

        return view('lynx-admin::livewire.branding-form', [
            'preview' => Branding::from($this->typed($current), $defaults),
            'defaults' => Branding::from([], $defaults),
            'hasUploadedLogo' => isset($current['logo']),
            'hasUploadedDarkLogo' => isset($current['logo_dark']),
            'newLogoUrl' => $this->previewUrl($this->logo),
            'newDarkLogoUrl' => $this->previewUrl($this->logoDark),
            'hasSvgPending' => ($this->logo && ! $this->previewUrl($this->logo)) || ($this->logoDark && ! $this->previewUrl($this->logoDark)),
        ]);
    }

    /**
     * The branding as typed, with the saved logos the form is not removing.
     *
     * @param  array<string, string>  $current
     * @return array<string, string>
     */
    private function typed(array $current): array
    {
        $typed = array_filter([
            'name' => $this->name, 'background' => $this->background, 'accent' => $this->accent,
            'background_dark' => $this->backgroundDark, 'accent_dark' => $this->accentDark,
        ], fn (string $value): bool => $value !== '');

        foreach (['logo' => $this->removeLogo, 'logo_dark' => $this->removeLogoDark] as $key => $isRemoved) {
            if (! $isRemoved && isset($current[$key])) {
                $typed[$key] = $current[$key];
            }
        }

        return $typed;
    }
}
