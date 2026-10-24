<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Branding\Branding;
use LynxCms\Admin\Branding\SaveBranding;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Support\Value;

/**
 * Settings > General: the site's name, logo and colours on the sign-in screens and the sidebar, with
 * a preview of the sign-in card. Saves through admin.branding; an empty field is the default.
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

    #[Locked]
    public ?string $status = null;

    /** Runs on mount and on every Livewire update, so the checks also guard /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(RoleList::currentUserIsSuperuser(), 403, 'Only superusers change the branding.');
    }

    public function mount(): void
    {
        $saved = Branding::saved();
        $this->name = $saved['name'] ?? '';
        $this->background = $saved['background'] ?? '';
        $this->accent = $saved['accent'] ?? '';
    }

    public function save(): void
    {
        $this->validate([
            'logo' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg', 'max:1024'],
            'background' => ['nullable', 'regex:'.Branding::HEX],
            'accent' => ['nullable', 'regex:'.Branding::HEX],
        ]);

        $logo = $this->logo instanceof TemporaryUploadedFile
            ? 'data:'.$this->logo->getMimeType().';base64,'.base64_encode((string) $this->logo->get())
            : null;

        lynx()->run(SaveBranding::class, [
            'name' => $this->name, 'background' => $this->background, 'accent' => $this->accent,
            'remove_logo' => $this->removeLogo, ...($logo === null ? [] : ['logo' => $logo]),
        ]);

        $this->reset('logo', 'removeLogo');
        $this->notify('Saved. The sign-in screens and the sidebar show it from the next page.');
    }

    /** A raster logo previews before it is saved; an SVG is only ever served sandboxed, once saved. */
    private function previewUrl(): ?string
    {
        $isRaster = in_array(strtolower((string) $this->logo?->getClientOriginalExtension()), ['png', 'jpg', 'jpeg', 'webp'], true);

        return $isRaster && $this->logo?->isPreviewable() === true ? Value::string($this->logo->temporaryUrl()) : null;
    }

    public function render(): View
    {
        $defaults = Value::map(config('lynx-admin.branding', []));
        $saved = array_filter(['name' => $this->name, 'background' => $this->background, 'accent' => $this->accent], fn (string $value): bool => $value !== '');
        $current = Branding::saved();

        if (! $this->removeLogo && isset($current['logo'])) {
            $saved['logo'] = $current['logo'];
        }

        return view('lynx-admin::livewire.branding-form', [
            'preview' => Branding::from($saved, $defaults),
            'defaults' => Branding::from([], $defaults),
            'hasUploadedLogo' => isset($current['logo']),
            'newLogoUrl' => $this->previewUrl(),
        ]);
    }
}
