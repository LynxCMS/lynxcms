<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Actions\Pages\SchedulePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * The Schedule section of the editor's Settings tab (ADR-0036): when the page is published and
 * unpublished, as date-times in the app's time zone. One row sets every language; "Different
 * times per language" shows a row per language, and opens by itself when the saved times
 * differ. An empty field clears the entry. Entries that could not run show why. While the page
 * has a draft, a last field sets when the draft goes live (ADR-0092).
 *
 * @api
 */
final class PageSchedule extends Component
{
    use Notifies;

    /** The format of <input type="datetime-local">. */
    private const string INPUT_FORMAT = 'Y-m-d\TH:i';

    #[Locked]
    public int $pageId;

    /** @var array<string, array{publish: string, unpublish: string}> language code => times */
    public array $times = [];

    /** @var array<string, array{publish: string, unpublish: string}> the times as loaded, to send only changes */
    #[Locked]
    public array $loaded = [];

    /** @var array{publish: string, unpublish: string} the one row that sets every language */
    public array $all = ['publish' => '', 'unpublish' => ''];

    /** @var array{publish: string, unpublish: string} the one row as loaded */
    #[Locked]
    public array $loadedAll = ['publish' => '', 'unpublish' => ''];

    public bool $perLanguage = false;

    /** When the page's draft goes live, or '' for never. */
    public string $draftAt = '';

    #[Locked]
    public string $loadedDraftAt = '';

    #[Locked]
    public ?string $status = null;

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
    }

    public function mount(int $pageId): void
    {
        $this->pageId = $pageId;
        $this->load();
    }

    /** Switching modes carries the times over, so nothing typed is lost. */
    public function updatedPerLanguage(bool $perLanguage): void
    {
        if ($perLanguage) {
            $this->times = array_map(fn (): array => $this->all, $this->times);

            return;
        }

        $default = (string) lynx()->languages->default()->code;
        $this->all = $this->times[$default] ?? $this->all;
    }

    public function saveSchedule(): void
    {
        $this->status = null;
        $isSaved = ($this->perLanguage ? $this->saveEachLanguage() : $this->saveAllLanguages()) && $this->saveDraftTime();

        if ($isSaved) {
            $this->load();
            $this->notify('Schedule saved.');
        }
    }

    public function render(): View
    {
        $page = $this->page();

        return view('lynx-admin::livewire.page-schedule', [
            'languages' => lynx()->languages->all(),
            'failed' => array_values(array_filter(lynx()->pages->schedules($this->pageId), fn (array $entry): bool => $entry['error'] !== null)),
            'canPublish' => app(AdminAccess::class)->allows('pages.publish', $page),
            'hasLanguageTimes' => ! $this->isWholePage() && lynx()->languages->isMultilingual(),
            'hasDraft' => $this->loadedDraftAt !== '' || lynx()->pages->draft($page) !== null,
            'canPublishDraft' => app(AdminAccess::class)->allows('pages.publish', $page) && app(AdminAccess::class)->allows('pages.edit', $page),
        ]);
    }

    private function saveAllLanguages(): bool
    {
        $input = $this->changedInput($this->all, $this->loadedAll);

        // A page without a URL is in every language at once: its schedule is the default language's, the whole page's.
        $language = $this->isWholePage() ? (string) lynx()->languages->default()->code : SchedulePage::ALL_LANGUAGES;

        return $input === [] || $this->schedule($language, $input, 'all');
    }

    private function saveDraftTime(): bool
    {
        if ($this->draftAt === $this->loadedDraftAt) {
            return true;
        }

        try {
            lynx()->run(SchedulePage::class, ['page' => $this->pageId, 'publish_draft_at' => $this->draftAt === '' ? null : $this->draftAt]);
        } catch (ValidationException $exception) {
            $this->addError('draftAt', Value::string(Value::array(array_first($exception->errors()) ?? [])[0] ?? ''));

            return false;
        }

        return true;
    }

    private function saveEachLanguage(): bool
    {
        foreach ($this->times as $code => $times) {
            $input = $this->changedInput($times, $this->loaded[$code] ?? []);

            if ($input !== [] && ! $this->schedule((string) $code, $input, "times.{$code}")) {
                return false;
            }
        }

        return true;
    }

    /**
     * Runs SchedulePage, showing a refusal under the row's field.
     *
     * @param  array<string, string|null>  $input
     */
    private function schedule(string $language, array $input, string $errorPrefix): bool
    {
        try {
            lynx()->run(SchedulePage::class, ['page' => $this->pageId, 'language' => $language, ...$input]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $this->addError($errorPrefix.'.'.str_replace('_at', '', (string) $key), Value::string(Value::array($messages)[0] ?? ''));
            }

            return false;
        }

        return true;
    }

    /**
     * The keys SchedulePage needs for the times that changed: a time, or null to clear.
     *
     * @param  array<array-key, mixed>  $times
     * @param  array<array-key, mixed>  $loaded
     * @return array<string, string|null>
     */
    private function changedInput(array $times, array $loaded): array
    {
        $input = [];

        foreach (SchedulePage::ACTIONS as $action) {
            $value = Value::string($times[$action] ?? '');

            if ($value !== Value::string($loaded[$action] ?? '')) {
                $input[$action.'_at'] = $value === '' ? null : $value;
            }
        }

        return $input;
    }

    private function load(): void
    {
        $times = [];

        foreach (lynx()->languages->all() as $language) {
            $times[(string) $language->code] = ['publish' => '', 'unpublish' => ''];
        }

        $this->draftAt = '';

        foreach (lynx()->pages->schedules($this->pageId) as $entry) {
            if ($entry['action'] === SchedulePage::PUBLISH_DRAFT) {
                $this->draftAt = $entry['at']->format(self::INPUT_FORMAT);
            } elseif (isset($times[$entry['language']])) {
                $times[$entry['language']][$entry['action'] === 'publish' ? 'publish' : 'unpublish'] = $entry['at']->format(self::INPUT_FORMAT);
            }
        }

        $this->loadedDraftAt = $this->draftAt;
        $this->times = $times;
        $this->loaded = $times;
        $distinct = array_unique(array_map(serialize(...), $times));
        $this->perLanguage = ! $this->isWholePage() && count($distinct) > 1;
        $this->all = match (true) {
            $this->isWholePage() => $times[(string) lynx()->languages->default()->code] ?? $this->all,
            $this->perLanguage => ['publish' => '', 'unpublish' => ''],
            default => reset($times) ?: $this->all,
        };
        $this->loadedAll = $this->all;
    }

    /** A page without a URL shows in every language at once, so it has no times per language. */
    private function isWholePage(): bool
    {
        return ! $this->page()->template()->hasUrl();
    }

    private function page(): Page
    {
        return lynx()->pages->get($this->pageId) ?? abort(404);
    }
}
