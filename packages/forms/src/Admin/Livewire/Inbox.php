<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\UpdateSubmission;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Models\Submission;

/**
 * The submissions inbox: filtered by form and status, searched by text, newest first, a page at
 * a time, with the selected ones marked read, archived or spam in one go.
 *
 * @internal
 */
final class Inbox extends Component
{
    use Notifies;

    private const int PER_PAGE = 25;

    /** "open" (new and read), a status, or "all". */
    #[Url]
    public string $show = 'open';

    #[Url]
    public string $form = '';

    #[Url]
    public string $search = '';

    public int $page = 1;

    /** @var list<string> uuids */
    public array $selected = [];

    public ?string $status = null;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('forms.view'), 403);
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['show', 'form', 'search'], true)) {
            $this->page = 1;
            $this->selected = [];
        }
    }

    public function markSelected(string $status): void
    {
        foreach ($this->selected as $uuid) {
            lynx()->run(UpdateSubmission::class, ['submission' => $uuid, 'status' => $status]);
        }

        $count = count($this->selected);
        $this->selected = [];
        $this->notify($count === 1 ? '1 submission updated.' : "{$count} submissions updated.");
    }

    public function render(): View
    {
        $query = $this->query();
        $total = (clone $query)->count();
        $submissions = $query->with('files')->orderByDesc('id')->forPage($this->page, self::PER_PAGE)->get();

        return view('lynx-forms::admin.livewire.inbox', [
            'submissions' => $submissions,
            'forms' => array_map(fn (Form $form): string => $form->text('label')->in(), lynx()->forms()->all()),
            'hasMore' => $total > $this->page * self::PER_PAGE,
            'total' => $total,
        ]);
    }

    /** "Ana Lima · ana@example.com": the first short answers, to tell rows apart. */
    public function summaryOf(Submission $submission): string
    {
        $parts = [];

        foreach ($submission->fields as $field) {
            $value = $submission->data[(string) ($field['name'] ?? '')] ?? null;

            if (in_array($field['type'] ?? '', ['text', 'email', 'tel'], true) && is_string($value) && $value !== '') {
                $parts[] = $value;
            }
        }

        return implode(' · ', array_slice($parts, 0, 2));
    }

    /** @return Builder<Submission> */
    private function query(): Builder
    {
        $query = Submission::query();

        match ($this->show) {
            'open' => $query->whereIn('status', ['new', 'read']),
            'all' => null,
            default => $query->where('status', $this->show),
        };

        if ($this->form !== '') {
            $query->where('form', $this->form);
        }

        $search = mb_strtolower(trim($this->search));

        if ($search !== '') {
            // Answers are JSON: match the text as typed and as JSON escapes it (ã → ã).
            $escaped = mb_strtolower(trim(Value::string(json_encode($search)), '"'));
            $query->where(fn (Builder $q) => $q
                ->whereRaw('LOWER(CAST(data AS TEXT)) LIKE ?', ['%'.$search.'%'])
                ->orWhereRaw('LOWER(CAST(data AS TEXT)) LIKE ?', ['%'.$escaped.'%']));
        }

        return $query;
    }
}
