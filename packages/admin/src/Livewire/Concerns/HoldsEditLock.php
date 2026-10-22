<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Editor\ActorNames;
use LynxCms\Core\Actions\Pages\LockEdit;
use LynxCms\Core\Actions\Pages\UnlockEdit;

/**
 * The page editor's edit lock (ADR-0037): taken on open, renewed by a poll every minute. While
 * someone else holds it, the editor is read only and says who; the same poll picks the lock up
 * once they are gone. Superusers may take over.
 *
 * @internal
 */
trait HoldsEditLock
{
    #[Locked]
    public bool $holdsEditLock = false;

    /** Who else is editing, by name, and until when (H:i); null when nobody else is. */
    #[Locked]
    public ?string $editedBy = null;

    #[Locked]
    public ?string $editedUntil = null;

    /** Renew the lock, or try to take it when someone else had it. */
    public function renewEditLock(): void
    {
        $this->takeEditLock();
    }

    public function takeOverEditLock(): void
    {
        if (! app(AdminAccess::class)->isSuperuser()) {
            return;
        }

        lynx()->run(UnlockEdit::class, ['page' => $this->pageId]);
        $this->takeEditLock();
    }

    protected function takeEditLock(): void
    {
        try {
            lynx()->run(LockEdit::class, ['page' => $this->pageId]);
            $this->holdsEditLock = true;
            $this->editedBy = null;
            $this->editedUntil = null;
        } catch (ValidationException) {
            $this->holdsEditLock = false;
            $this->describeEditLock();
        } catch (AuthorizationException) {
            // Without pages.edit the editor is read only anyway.
            $this->holdsEditLock = false;
        }
    }

    private function describeEditLock(): void
    {
        $lock = lynx()->pages->editLock($this->pageId);

        if ($lock === null) {
            return;
        }

        $parts = explode(':', $lock['holder'], 2);
        $this->editedBy = app(ActorNames::class)->label($parts[0], $parts[1] ?? null);
        $this->editedUntil = $lock['expires_at']->format('H:i');
    }
}
