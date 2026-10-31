<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\DeleteSubmission;
use LynxCms\Forms\Actions\UpdateSubmission;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\SubmissionPresenter;

/**
 * One submission: its answers (from the fields stored with it), files, where and in which
 * language it was sent, the team's notes and its status. Opening a new one marks it read.
 *
 * @internal
 */
final class SubmissionView extends Component
{
    use Notifies;

    #[Locked]
    public string $uuid = '';

    public string $notes = '';

    public ?string $status = null;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('forms.view'), 403);
    }

    public function mount(string $uuid): void
    {
        $this->uuid = $uuid;
        $submission = $this->submission();
        $this->notes = (string) $submission->notes;

        if ($submission->status === 'new') {
            lynx()->run(UpdateSubmission::class, ['submission' => $uuid, 'status' => 'read']);
        }
    }

    public function saveNotes(): void
    {
        lynx()->run(UpdateSubmission::class, ['submission' => $this->uuid, 'notes' => trim($this->notes) === '' ? null : $this->notes]);
        $this->notify(Value::string(__('lynx-forms::admin.submission.notes_saved')));
    }

    public function setStatus(string $status): void
    {
        lynx()->run(UpdateSubmission::class, ['submission' => $this->uuid, 'status' => $status]);
        $this->notify(match ($status) {
            'archived' => Value::string(__('lynx-forms::admin.submission.archived')),
            'spam' => Value::string(__('lynx-forms::admin.submission.spam_done')),
            'new' => Value::string(__('lynx-forms::admin.submission.new_done')),
            default => Value::string(__('lynx-forms::admin.submission.read_done')),
        });
    }

    public function delete(): void
    {
        abort_unless(app(AdminAccess::class)->allows('forms.manage'), 403);
        lynx()->run(DeleteSubmission::class, ['submission' => $this->uuid]);
        $this->redirectRoute('lynx.admin.forms');
    }

    public function render(): View
    {
        $submission = $this->submission();

        return view('lynx-forms::admin.livewire.submission', [
            'submission' => $submission,
            'lines' => app(SubmissionPresenter::class)->lines($submission),
            'form' => lynx()->forms()->get($submission->form),
            'page' => $submission->page_id === null ? null : lynx()->pages->get($submission->page_id),
            'canDelete' => app(AdminAccess::class)->allows('forms.manage'),
        ]);
    }

    private function submission(): Submission
    {
        return Submission::query()->with('files')->where('uuid', $this->uuid)->firstOrFail();
    }
}
