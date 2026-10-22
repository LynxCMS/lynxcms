<?php

declare(strict_types=1);

namespace Workbench\App\Livewire;

use Illuminate\Contracts\View\Factory;
use Illuminate\Contracts\View\View;
use Livewire\Component;
use LynxCms\Core\Livewire\UsesLynxPage;
use Workbench\App\Models\Enrolment;

/**
 * Demo app component: an enrolment form living outside the CMS, placed on course pages through
 * a "component" field. It stores enrolments in its own table; the rest of the page comes from Lynx.
 */
final class EnrolForm extends Component
{
    use UsesLynxPage;

    public string $email = '';

    public bool $sent = false;

    public function save(): void
    {
        if ($this->isFull()) {
            return;
        }

        $this->validate(['email' => ['required', 'email']]);

        Enrolment::query()->create([
            'page_id' => $this->pageId,
            'lang' => $this->lang,
            'email' => $this->email,
        ]);

        $this->sent = true;
    }

    public function render(): Factory|View
    {
        return view('livewire.enrol-form', [
            'course' => $this->page(),
            'isFull' => $this->isFull(),
        ]);
    }

    /** The "capacity" setting, when set, caps enrolments for this page. */
    private function isFull(): bool
    {
        $capacity = $this->settings['capacity'] ?? null;

        return is_int($capacity) && Enrolment::query()->where('page_id', $this->pageId)->count() >= $capacity;
    }
}
