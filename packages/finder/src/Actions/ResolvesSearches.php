<?php

declare(strict_types=1);

namespace LynxCms\Finder\Actions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\Value;
use LynxCms\Finder\Models\SavedSearch;

/**
 * Finds a saved search by id for a change, which only its owner or a superuser may make.
 *
 * @internal
 */
trait ResolvesSearches
{
    private function ownSearch(mixed $reference, ActionContext $context): SavedSearch
    {
        $id = Value::int($reference);
        $search = SavedSearch::query()->find($id)
            ?? throw ValidationException::withMessages(['search' => "There is no saved search [{$id}]."]);

        if ($search->owner !== SavedSearch::ownerKey($context->actor) && ! app(Authorizer::class)->isSuperuser($context->actor)) {
            throw new AuthorizationException("Only the user who saved the search [{$search->name}], or a superuser, may change it.");
        }

        return $search;
    }
}
