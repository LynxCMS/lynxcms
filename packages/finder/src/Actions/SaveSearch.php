<?php

declare(strict_types=1);

namespace LynxCms\Finder\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;
use LynxCms\Finder\Models\SavedSearch;
use LynxCms\Finder\Search\FinderQuery;

/**
 * Saves a finder search under a name, or changes a saved one (ADR-0111). A new search belongs to
 * whoever saves it; sharing it with everyone who may use the finder needs finder.share.
 */
final class SaveSearch extends Action
{
    use ResolvesSearches;

    public static function name(): string
    {
        return 'finder.save_search';
    }

    public static function description(): string
    {
        return 'Save a Page Finder search under a name, or change a saved one. Shared searches are seen by everyone who may use the finder.';
    }

    public static function permission(): string
    {
        return 'pages.view';
    }

    public static function rules(): array
    {
        return [
            'search' => ['sometimes', 'nullable', 'integer'],
            'name' => ['required', 'string', 'max:100'],
            'query' => ['required', 'array'],
            'shared' => ['sometimes', 'boolean'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->integer()->description('The id of a saved search to change; leave out to save a new one.'),
            'name' => $schema->string()->description('What the search is called in the finder.')->required(),
            'query' => $schema->object()->description('The search: {"match": "all"|"any", "groups": [{"match": "all"|"any", "conditions": [{"subject", "operator", "value", "to", "pages"}]}], "sort", "columns": [field names shown in the results]}.')->required(),
            'shared' => $schema->boolean()->description('Show the search to everyone who may use the finder (needs finder.share).'),
        ];
    }

    public function handle(Input $input, ActionContext $context): SavedSearch
    {
        $isShared = Value::bool($input->get('shared', false));

        if ($isShared) {
            $context->authorize('finder.share');
        }

        $existing = $input->get('search');
        $search = $existing === null ? new SavedSearch(['owner' => SavedSearch::ownerKey($context->actor)]) : $this->ownSearch($existing, $context);
        $isNew = ! $search->exists;

        $search->fill([
            'name' => trim(Value::string($input->get('name'))),
            'is_shared' => $isShared,
            'query' => FinderQuery::fromArray(Value::array($input->get('query')))->toArray(),
        ])->save();

        $summary = ($isNew ? 'Save search ' : 'Change saved search ').$search->name;
        $isNew ? $context->changes->created('finder_search', $search->id, $summary) : $context->changes->updated('finder_search', $search->id, $summary);

        return $search;
    }
}
