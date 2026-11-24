<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\DeleteSubmissions;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\PersonalData;

/**
 * The Personal data tab (ADR-0147): an email address, every submission where it is a whole
 * answer across forms and statuses, then Export (JSON) to hand the person, or Delete after a dry
 * run says how many submissions and files go.
 *
 * @internal
 */
final class PersonalDataSearch extends Component
{
    use Notifies;

    /** Rows listed; the export and delete take them all. */
    private const int SHOWN = 100;

    #[Url]
    public string $email = '';

    /** @var array{submissions: int, files: int}|null */
    #[Locked]
    public ?array $confirmingDelete = null;

    public ?string $status = null;

    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen('forms.manage');
    }

    public function updatedEmail(): void
    {
        $this->email = trim($this->email);
        $this->confirmingDelete = null;
    }

    public function confirmDelete(): void
    {
        $counts = Value::map(lynx()->run(DeleteSubmissions::class, $this->selection(), dryRun: true)->value);
        $this->confirmingDelete = ['submissions' => Value::int($counts['submissions'] ?? 0), 'files' => Value::int($counts['files'] ?? 0)];
    }

    public function delete(): void
    {
        $count = Value::int(Value::map(lynx()->run(DeleteSubmissions::class, $this->selection())->value)['submissions'] ?? 0);
        $this->confirmingDelete = null;
        $this->notify(trans_choice('lynx-forms::admin.inbox.deleted', $count));
    }

    public function render(PersonalData $data): View
    {
        $isSearchable = $this->isSearchable();

        return view('lynx-forms::admin.livewire.personal-data', [
            'submissions' => $isSearchable ? $data->filter($this->email)->query()->orderByDesc('id')->limit(self::SHOWN)->get() : collect(),
            'total' => $isSearchable ? $data->filter($this->email)->query()->count() : 0,
            'isSearchable' => $isSearchable,
            'forms' => array_map(fn (Form $form): string => $form->text('label')->in(), lynx()->forms()->all()),
        ]);
    }

    /** An address with an @, so a stray letter doesn't list everyone's submissions. */
    private function isSearchable(): bool
    {
        return filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /** @return array{filter: array<string, string|int>} */
    private function selection(): array
    {
        abort_unless($this->isSearchable(), 422);

        return ['filter' => app(PersonalData::class)->filter($this->email)->toArray()];
    }
}
