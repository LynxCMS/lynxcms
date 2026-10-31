<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Editor\SchemaOwners;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Actions\Languages\CreateLanguage;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\Change;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Value;

/**
 * Setup > Languages > New language and Edit language. Writes go through languages.create,
 * languages.update and languages.delete, whose errors show by their inputs. The default language
 * cannot be deleted and has no fallback.
 *
 * @api
 */
final class LanguageForm extends Component
{
    use Notifies;

    /** The code of the language being edited; null on the New language screen. */
    #[Locked]
    public ?string $language = null;

    #[Locked]
    public bool $isDefault = false;

    public string $code = '';

    public string $name = '';

    /** Shown on the language tabs; empty shows the name. */
    public string $label = '';

    public string $locale = '';

    /** Empty uses the code (the default language has no prefix). */
    public string $prefix = '';

    /** Code of the fallback language; empty falls back to the default language. */
    public string $fallback = '';

    public bool $active = true;

    #[Locked]
    public ?string $status = null;

    #[Locked]
    public bool $isConfirmingDelete = false;

    /** @var list<string> the dry run's change lines */
    #[Locked]
    public array $deleteSummary = [];

    /** Runs on mount and on every Livewire update, so the checks also guard /livewire/update. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('schema.manage'), 403, Value::string(__('lynx-admin::languages.denied')));
    }

    public function mount(?string $language = null): void
    {
        $existing = $language === null ? null : lynx()->languages->find($language);

        if ($existing instanceof Language) {
            $this->language = $existing->code;
            $this->isDefault = $existing->isDefault();
            $this->code = $existing->code;
            $this->name = $existing->name;
            $this->label = $existing->label ?? '';
            $this->locale = $existing->locale ?? '';
            $this->prefix = $existing->url_prefix;
            $this->fallback = $existing->fallback_id === null ? '' : (string) lynx()->languages->find($existing->fallback_id)?->code;
            $this->active = $existing->is_active;
        }
    }

    public function save(): void
    {
        $this->authorizeEditing();
        $this->status = null;

        if ($this->language === null) {
            lynx()->run(CreateLanguage::class, array_filter($this->input(), fn (mixed $value): bool => $value !== '') + ['active' => $this->active]);
            $this->redirectRoute('lynx.admin.languages.edit', trim($this->code));

            return;
        }

        $language = lynx()->languages->update($this->language, $this->input() + ['active' => $this->active]);

        if ($language instanceof Language && $language->code !== $this->language) {
            $this->redirectRoute('lynx.admin.languages.edit', $language->code);

            return;
        }

        $this->notify(__('lynx-admin::languages.saved'));
    }

    /** A dry run first, so the confirmation can say how much content goes with the language. */
    public function confirmDelete(): void
    {
        $this->authorizeDelete();
        $result = lynx()->languages->delete((string) $this->language, dryRun: true);
        $this->deleteSummary = $result instanceof ActionResult ? array_map(fn (Change $change): string => $change->summary, $result->changes->all()) : [];
        $this->isConfirmingDelete = true;
    }

    public function cancelDelete(): void
    {
        $this->isConfirmingDelete = false;
        $this->deleteSummary = [];
    }

    public function delete(): void
    {
        $this->authorizeDelete();
        lynx()->languages->delete((string) $this->language);
        $this->redirectRoute('lynx.admin.languages');
    }

    public function render(): View
    {
        return view('lynx-admin::livewire.language-form', [
            'canEdit' => app(SchemaOwners::class)->isEditingEnabled(),
            'fallbacks' => lynx()->languages->all()
                ->reject(fn (Language $language): bool => $language->code === $this->language)
                ->mapWithKeys(fn (Language $language): array => [$language->code => $language->name])->all(),
        ]);
    }

    /**
     * The form as the language actions take it. The default language never gets a fallback.
     *
     * @return array<string, string|null>
     */
    private function input(): array
    {
        $input = [
            'code' => trim($this->code),
            'name' => trim($this->name),
            'label' => trim($this->label),
            'locale' => trim($this->locale) !== '' ? trim($this->locale) : null,
            'prefix' => trim($this->prefix),
        ];

        if (! $this->isDefault) {
            $input['fallback'] = $this->fallback;
        }

        if ($this->language === null && $input['prefix'] === '') {
            unset($input['prefix']);
        }

        return $input;
    }

    /** Read only screens refuse writes on the server too, whatever the browser sends. */
    private function authorizeEditing(): void
    {
        abort_unless(app(SchemaOwners::class)->isEditingEnabled(), 403, Value::string(__('lynx-admin::languages.from_files')));
    }

    private function authorizeDelete(): void
    {
        $this->authorizeEditing();
        abort_if($this->language === null || $this->isDefault, 403, Value::string(__('lynx-admin::languages.default_kept')));
    }
}
