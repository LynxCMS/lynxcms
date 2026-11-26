<?php

declare(strict_types=1);

namespace LynxCms\Core\Query;

use Closure;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Fields\FieldAccess;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Search\SearchText;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateField;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * Limits on a selector that comes from outside the site's code, checked before it compiles: which
 * fields it may filter, sort or load, which terms it may use, and how much it may ask for. A refused
 * selector throws a SelectorException, as an invalid one does.
 *
 *     lynx()->pages->find($request->string('q'), maxLimit: 50, policy: SelectorPolicy::forVisitors(['title', 'price']));
 *
 * @api
 */
final readonly class SelectorPolicy
{
    /** Terms that reach unpublished, hidden or draft pages, or load values: never from visitors. */
    public const array VISITOR_REFUSED_TERMS = ['include', 'join', ...PageStateMatch::TERMS];

    /** The page's own columns, which every page has and every visitor may filter on. */
    private const array PAGE_COLUMNS = ['id', 'template', 'parent', 'has_parent', 'name', 'created', 'modified', 'published', 'sort', 'children'];

    /** Terms that shape the query rather than name a field. */
    private const array CONTROL_TERMS = ['sort', 'limit', 'start', 'include', 'join', 'language', 'lang', ...PageStateMatch::TERMS];

    /**
     * @param  Closure(Field): bool|null  $allowsField  whether the selector may use a field; null allows every field
     * @param  list<string>  $refusedTerms  terms such as "include" or "status" the selector may not use
     * @param  int|null  $maxClauses  most clauses, OR groups' included
     * @param  int|null  $maxValues  most values across clauses ("a=1|2" holds two; words of ~= and search count one each)
     * @param  int|null  $maxStart  highest "start="
     */
    public function __construct(
        private ?Closure $allowsField = null,
        private array $refusedTerms = [],
        private ?int $maxClauses = null,
        private ?int $maxValues = null,
        private ?int $maxStart = null,
    ) {}

    /**
     * For a selector a visitor typed or sent: only published pages, only the fields named, and a
     * bounded amount of work. "search" is allowed when $fields holds it.
     *
     * @param  list<string>  $fields
     */
    public static function forVisitors(array $fields, int $maxClauses = 10, int $maxValues = 20, int $maxStart = 1000): self
    {
        return new self(
            allowsField: fn (Field $field): bool => in_array($field->name, $fields, true),
            refusedTerms: [...self::VISITOR_REFUSED_TERMS, ...(in_array('search', $fields, true) ? [] : ['search'])],
            maxClauses: $maxClauses,
            maxValues: $maxValues,
            maxStart: $maxStart,
        );
    }

    /**
     * For a selector an editor or agent runs (ADR-0151): no field the actor's role may not view, and
     * no "search" while such a field is in the search index, since matches and their order would
     * tell its value. Null when every field is open to the actor.
     */
    public static function forActor(Actor $actor): ?self
    {
        $access = app(FieldAccess::class);
        $hidden = app(FieldRegistry::class)->all()->reject(fn (Field $field): bool => $access->canView($field, $actor));

        if ($hidden->isEmpty()) {
            return null;
        }

        $names = $hidden->pluck('name')->all();
        $templates = app(TemplateRegistry::class);
        $isIndexed = $templates->all()->contains(fn (Template $template): bool => $templates->fields($template)
            ->contains(fn (TemplateField $field, string $name): bool => in_array($name, $names, true) && SearchText::indexes($name, $field)));

        return new self(
            allowsField: fn (Field $field): bool => ! in_array($field->name, $names, true),
            refusedTerms: $isIndexed ? ['search'] : [],
        );
    }

    /** Refuses the selector when it breaks a limit. */
    public function check(ParsedSelector $parsed, FieldRegistry $fields): void
    {
        $clauses = [...$parsed->clauses, ...array_merge([], ...array_map(fn (OrGroupSet $set): array => array_merge([], ...$set->alternatives), $parsed->groups))];

        if ($this->maxClauses !== null && count($clauses) > $this->maxClauses) {
            throw new SelectorException(Value::string(__('lynx::selectors.policy.clauses', ['max' => $this->maxClauses])));
        }

        $values = 0;

        foreach ($clauses as $clause) {
            $values += $this->countValues($clause);
            $this->checkClause($clause, $fields);
        }

        if ($this->maxValues !== null && $values > $this->maxValues) {
            throw new SelectorException(Value::string(__('lynx::selectors.policy.values', ['max' => $this->maxValues])));
        }
    }

    private function checkClause(Clause $clause, FieldRegistry $fields): void
    {
        foreach (explode('|', $clause->field) as $name) {
            if (in_array($name, $this->refusedTerms, true)) {
                throw new SelectorException(Value::string(__('lynx::selectors.policy.term', ['term' => $name])));
            }
        }

        if ($clause->field === 'start' && $this->maxStart !== null && (int) $clause->value() > $this->maxStart) {
            throw new SelectorException(Value::string(__('lynx::selectors.policy.start', ['max' => $this->maxStart])));
        }

        foreach ($this->namedFields($clause) as $name) {
            $field = $fields->find($name);

            if ($field instanceof Field && $this->allowsField instanceof Closure && ! ($this->allowsField)($field)) {
                throw new SelectorException(Value::string(__('lynx::selectors.policy.field', ['field' => $name])));
            }
        }
    }

    /**
     * Every name in the clause that may be a field: each part of "a|b", each segment of
     * "related.price" (the page it points at has fields too), and the names "sort=" and "join=" give.
     *
     * @return list<string>
     */
    private function namedFields(Clause $clause): array
    {
        $names = match ($clause->field) {
            'sort' => array_map(fn (string $sort): string => ltrim($sort, '-'), $clause->values),
            'join' => $clause->values,
            default => in_array($clause->field, self::CONTROL_TERMS, true) ? [] : explode('|', $clause->field),
        };

        $segments = array_merge([], ...array_map(fn (string $name): array => explode('.', $name), $names));

        return array_values(array_diff($segments, self::PAGE_COLUMNS, ['count', 'all']));
    }

    private function countValues(Clause $clause): int
    {
        $words = fn (string $value): int => count(preg_split('/\s+/u', trim($value), -1, PREG_SPLIT_NO_EMPTY) ?: []);

        return in_array($clause->operator, ['~=', '*='], true) || $clause->field === 'search'
            ? array_sum(array_map($words, $clause->values))
            : count($clause->values);
    }
}
