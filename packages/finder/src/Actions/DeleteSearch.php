<?php

declare(strict_types=1);

namespace LynxCms\Finder\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Finder\Models\SavedSearch;

/**
 * Deletes a saved finder search, for its owner or a superuser (ADR-0111). Pages are untouched.
 */
final class DeleteSearch extends Action
{
    use ResolvesSearches;

    public static function name(): string
    {
        return 'finder.delete_search';
    }

    public static function description(): string
    {
        return 'Delete a saved Page Finder search. The pages it found are not changed.';
    }

    public static function permission(): string
    {
        return 'pages.view';
    }

    public static function rules(): array
    {
        return ['search' => ['required', 'integer']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return ['search' => $schema->integer()->description('The id of the saved search.')->required()];
    }

    public function handle(Input $input, ActionContext $context): SavedSearch
    {
        $search = $this->ownSearch($input->get('search'), $context);
        $search->delete();
        $context->changes->deleted('finder_search', $search->id, "Delete saved search {$search->name}");

        return $search;
    }
}
