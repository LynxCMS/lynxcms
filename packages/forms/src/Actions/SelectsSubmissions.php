<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\JsonSchema\Types\Type;
use Illuminate\Support\Str;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\SubmissionFilter;

/**
 * The submissions a bulk action acts on: a list of uuids or ids, or every submission a filter
 * matches (SubmissionFilter), read a chunk at a time by id so any number fits in memory.
 *
 * @internal
 */
trait SelectsSubmissions
{
    /** How many submissions a bulk action loads at once. */
    private const int CHUNK = 500;

    /** @return array<string, list<mixed>> */
    private static function selectionRules(): array
    {
        return [
            'submissions' => ['required_without:filter', 'array', 'max:1000'],
            'submissions.*' => ['string'],
            'filter' => ['required_without:submissions', 'array'],
            'filter.show' => ['sometimes', 'string', 'in:'.implode(',', SubmissionFilter::SHOWS)],
            'filter.form' => ['sometimes', 'string'],
            'filter.search' => ['sometimes', 'string', 'max:200'],
            'filter.from' => ['sometimes', 'date_format:Y-m-d'],
            'filter.to' => ['sometimes', 'date_format:Y-m-d'],
            'filter.up_to' => ['sometimes', 'integer'],
            'filter.email' => ['sometimes', 'string', 'max:254'],
        ];
    }

    /** @return array<string, Type> */
    private static function selectionSchema(JsonSchema $schema): array
    {
        return [
            'submissions' => $schema->array()->items($schema->string())->description('Uuids or ids, at most 1000. Or give filter.'),
            'filter' => $schema->object()->description('Every submission that matches: {show: open|all|new|read|archived|spam, form, search, from: Y-m-d, to: Y-m-d, up_to: the newest id to include, email: an address that is a whole answer}.'),
        ];
    }

    /** @return Builder<Submission> */
    private function selected(Input $input): Builder
    {
        if ($input->has('submissions')) {
            $references = Value::strings($input->get('submissions'));
            // PostgreSQL's uuid column refuses text that is no uuid: uuids and ids are compared apart.
            $uuids = array_values(array_filter($references, Str::isUuid(...)));
            $ids = array_map(intval(...), array_values(array_filter($references, ctype_digit(...))));

            return Submission::query()->where(fn (Builder $q) => $q->whereIn('uuid', $uuids)->orWhereIn('id', $ids));
        }

        return SubmissionFilter::fromArray(Value::map($input->get('filter')))->query();
    }
}
