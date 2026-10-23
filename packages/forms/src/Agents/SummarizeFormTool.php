<?php

declare(strict_types=1);

namespace LynxCms\Forms\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use LynxCms\Core\Agents\AgentReadTool;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\FormRegistry;
use LynxCms\Forms\Results\FormResults;

/**
 * A form's answers summed up per question, for "how did the course survey go?".
 */
final class SummarizeFormTool extends AgentReadTool
{
    public static function name(): string
    {
        return 'summarize_form';
    }

    public static function description(): string
    {
        return 'Sum up a form\'s answers per question, such as a survey\'s results: how many chose each option, the average of numbers, the range of dates, and the latest written answers. Spam is left out. Read single submissions with get_submission.';
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'form' => $schema->string()->description('The form\'s key, e.g. "course-feedback".')->required(),
            'page' => $schema->string()->description('Only answers sent from this page, by id or path, e.g. one course\'s survey.'),
        ];
    }

    public static function permission(): string
    {
        return 'forms.view';
    }

    public function handle(array $arguments): mixed
    {
        $form = app(FormRegistry::class)->get(Value::string($arguments['form'] ?? ''));

        if ($form === null) {
            return ['error' => 'There is no such form.'];
        }

        $reference = Value::string($arguments['page'] ?? '');
        $page = $reference === '' ? null : lynx()->pages->get(ctype_digit($reference) ? (int) $reference : $reference);

        return $reference !== '' && $page === null
            ? ['error' => 'There is no such page.']
            : app(FormResults::class)->summarize($form, $page?->id);
    }
}
