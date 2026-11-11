<?php

declare(strict_types=1);

namespace LynxCms\Finder\Admin;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Admin\Livewire\Concerns\Notifies;
use LynxCms\Core\Query\SelectorException;
use LynxCms\Core\Support\Value;
use LynxCms\Finder\Admin\Concerns\ActsOnResults;
use LynxCms\Finder\Admin\Concerns\ManagesSavedSearches;
use LynxCms\Finder\Catalog\ConditionCatalog;
use LynxCms\Finder\Catalog\Kind;
use LynxCms\Finder\Catalog\Operator;
use LynxCms\Finder\FinderResults;
use LynxCms\Finder\Search\Condition;
use LynxCms\Finder\Search\ConditionGroup;
use LynxCms\Finder\Search\FinderQuery;

/**
 * The Find pages screen: groups of conditions, each matching all or any of its conditions and
 * combined so a page matches every group or any, then the pages found and the selector the search
 * makes. The search lives in the URL, so a link opens it again. Searches can be saved
 * (ManagesSavedSearches), the ticked results changed together (ActsOnResults) and every result
 * exported as CSV (ExportResults).
 *
 * @internal
 */
final class FinderScreen extends Component
{
    use ActsOnResults;
    use ManagesSavedSearches;
    use Notifies;

    /** @var array<array-key, mixed> the search, as FinderQuery::toArray() writes it */
    #[Url(as: 'q')]
    public array $search = [];

    #[Url(except: 0)]
    public int $start = 0;

    /** The last notice, for screen readers and tests (Notifies). */
    public ?string $status = null;

    /** Every request, updates included: they reach /livewire/update, outside the admin's routes and their gate. */
    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('pages.view'), 403);
    }

    public function mount(): void
    {
        $query = FinderQuery::fromArray($this->search);
        $this->search = ($query->groups === [] ? $this->blank() : $query)->toArray();
    }

    public function addGroup(): void
    {
        $this->change(fn (FinderQuery $query): FinderQuery => new FinderQuery($query->matchAll, [...$query->groups, new ConditionGroup(conditions: [$this->blankCondition()])], $query->sort));
    }

    public function removeGroup(int $group): void
    {
        $this->change(function (FinderQuery $query) use ($group): FinderQuery {
            $groups = $query->groups;
            unset($groups[$group]);

            return new FinderQuery($query->matchAll, array_values($groups), $query->sort);
        });
    }

    public function addCondition(int $group): void
    {
        $this->changeGroup($group, fn (ConditionGroup $found): ConditionGroup => new ConditionGroup($found->matchAll, [...$found->conditions, $this->blankCondition()]));
    }

    public function removeCondition(int $group, int $condition): void
    {
        $this->changeGroup($group, function (ConditionGroup $found) use ($condition): ConditionGroup {
            $conditions = $found->conditions;
            unset($conditions[$condition]);

            return new ConditionGroup($found->matchAll, array_values($conditions));
        });
    }

    public function resetSearch(): void
    {
        $this->replaceSearch($this->blank());
        $this->forgetSavedSearch();
    }

    public function previous(): void
    {
        $this->start = max(0, $this->start - FinderResults::PER_PAGE);
    }

    public function next(): void
    {
        $this->start += FinderResults::PER_PAGE;
    }

    /**
     * A new subject keeps nothing of the old one: its operator becomes the subject's first, and the
     * value is cleared. Any change starts the results over.
     */
    public function updatedSearch(mixed $value, string $key): void
    {
        if (preg_match('/^groups\.(\d+)\.conditions\.(\d+)\.subject$/', $key, $match) === 1) {
            $subject = app(ConditionCatalog::class)->find(Value::string($value));
            $operator = $subject?->kind->operators()[0]->value ?? '';
            $this->changeGroup((int) $match[1], fn (ConditionGroup $group): ConditionGroup => $this->replaceCondition($group, (int) $match[2], new Condition(Value::string($value), $operator)));
        }

        $this->replaceSearch(FinderQuery::fromArray($this->search));
    }

    public function render(): View
    {
        $query = FinderQuery::fromArray($this->search);
        $results = app(FinderResults::class);

        try {
            $pages = $results->find($query, $this->start);
            $found = ['rows' => (new ResultRows)->from($pages), 'total' => $pages->total(), 'error' => null];
        } catch (SelectorException $exception) {
            $found = ['rows' => [], 'total' => 0, 'error' => $exception->getMessage()];
        }

        return view('lynx-finder::admin.screen', [
            ...$found,
            'catalog' => app(ConditionCatalog::class),
            'selector' => $results->selector($query),
            'perPage' => FinderResults::PER_PAGE,
            'sorts' => self::sorts(),
            'saved' => $this->savedSearches(),
            'canShare' => $this->canShare(),
            'canChangeSaved' => $this->canChangeSaved(),
            'exportUrl' => route('lynx.admin.finder.export', ['q' => $this->search]),
        ]);
    }

    /**
     * The orders the results may take, with their labels.
     *
     * @return array<string, string>
     */
    public static function sorts(): array
    {
        $sorts = [];

        foreach (FinderQuery::SORTS as $sort) {
            $key = ltrim($sort, '-').(str_starts_with($sort, '-') ? '_desc' : '');
            $sorts[$sort] = Value::string(__("lynx-finder::admin.sorts.{$key}"));
        }

        return $sorts;
    }

    /** The input a condition's value takes: none, text, number, date, choice or pages. */
    public static function input(?Kind $kind, string $operator): string
    {
        $found = Operator::tryFrom($operator);

        return $kind instanceof Kind && $found instanceof Operator ? $kind->input($found) : 'none';
    }

    private function blank(): FinderQuery
    {
        return new FinderQuery(groups: [new ConditionGroup(conditions: [$this->blankCondition()])]);
    }

    private function blankCondition(): Condition
    {
        return new Condition('', '');
    }

    private function replaceCondition(ConditionGroup $group, int $index, Condition $condition): ConditionGroup
    {
        $conditions = $group->conditions;

        if (isset($conditions[$index])) {
            $conditions[$index] = $condition;
        }

        return new ConditionGroup($group->matchAll, $conditions);
    }

    /**
     * @param  callable(FinderQuery): FinderQuery  $change
     */
    private function change(callable $change): void
    {
        $this->replaceSearch($change(FinderQuery::fromArray($this->search)));
    }

    /** A new search starts the results over, with nothing ticked; one without groups gets a blank group. */
    private function replaceSearch(FinderQuery $query): void
    {
        $this->search = ($query->groups === [] ? $this->blank() : $query)->toArray();
        $this->start = 0;
        $this->selected = [];
        $this->trashing = [];
    }

    /**
     * @param  callable(ConditionGroup): ConditionGroup  $change
     */
    private function changeGroup(int $index, callable $change): void
    {
        $this->change(function (FinderQuery $query) use ($index, $change): FinderQuery {
            $groups = $query->groups;

            if (isset($groups[$index])) {
                $groups[$index] = $change($groups[$index]);
            }

            return new FinderQuery($query->matchAll, $groups, $query->sort);
        });
    }
}
