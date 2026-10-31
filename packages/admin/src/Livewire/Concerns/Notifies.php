<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Closure;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Notices in the admin's notification bar (components/notifications): notify() shows a message,
 * green, yellow or red, and keeps it in $status for screen readers and tests. A save refused by
 * validation gets a red notice; the errors stay next to their inputs.
 */
trait Notifies
{
    protected function notify(string $message, string $type = 'success'): void
    {
        $this->status = $message;
        $this->dispatch('lynx-notify', message: $message, type: $type);
    }

    /** Livewire trait hook, run for any exception in a request. */
    public function exceptionNotifies(Throwable $e, Closure $stopPropagation): void
    {
        if (! $e instanceof ValidationException) {
            return;
        }

        $count = count($e->validator->errors()->all());
        $first = (string) $e->validator->errors()->first();
        $message = $count > 1
            ? __('lynx-admin::shell.not_saved_many', ['problem' => $first, 'count' => $count])
            : __('lynx-admin::shell.not_saved', ['problem' => $first]);
        $this->dispatch('lynx-notify', message: $message, type: 'error');
    }
}
