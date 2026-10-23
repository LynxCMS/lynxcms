<?php

declare(strict_types=1);

namespace LynxCms\Forms\Results;

use Illuminate\Database\Eloquent\Builder;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Models\Submission;

/**
 * A form's answers summed up per question, as a survey's results: how many chose each option,
 * the average of a number, the range of a date, the latest written answers. Spam is left out.
 * Labels are in the current language. The admin's Results screen and the summarize_form tool
 * read it.
 *
 * @api
 */
final class FormResults
{
    /** Written answers listed per question, newest first. */
    public const int LATEST = 5;

    /**
     * The answers to a form, or only those sent from one page, such as one course's survey.
     *
     * @return array{responses: int, questions: list<array<string, mixed>>}
     */
    public function summarize(Form $form, ?int $pageId = null): array
    {
        $answers = $this->submissions($form)->when($pageId !== null, fn ($query) => $query->where('page_id', $pageId))
            ->latest('id')->pluck('data')->map(fn (mixed $data): array => Value::map($data))->all();

        $questions = [];
        foreach ($form->fields() as $field) {
            if (! in_array($field->type, ['file', 'group'], true)) {
                $values = array_values(array_filter(array_column($answers, $field->name), fn (mixed $value): bool => ! in_array($value, [null, '', []], true)));
                $questions[] = ['name' => $field->name, 'label' => $field->label->in(), 'type' => $field->type, 'answered' => count($values)] + $this->summary($field, $values);
            }
        }

        return ['responses' => count($answers), 'questions' => $questions];
    }

    /**
     * The pages answers came from, with how many each, for a filter: page id => count.
     *
     * @return array<int, int>
     */
    public function pages(Form $form): array
    {
        return $this->submissions($form)->whereNotNull('page_id')->groupBy('page_id')->selectRaw('page_id, count(*) as answers')
            ->pluck('answers', 'page_id')->mapWithKeys(fn (mixed $count, mixed $page): array => [(int) $page => (int) $count])->all();
    }

    /** @return Builder<Submission> */
    private function submissions(Form $form): Builder
    {
        return Submission::query()->where('form', $form->key())->where('status', '!=', 'spam');
    }

    /**
     * @param  list<mixed>  $values
     * @return array<string, mixed>
     */
    private function summary(FormField $field, array $values): array
    {
        return match (true) {
            in_array($field->type, FormField::CHOICE_TYPES, true) => ['options' => $this->counts($field, $values)],
            $field->type === 'checkbox' => ['options' => [
                ['value' => 'yes', 'label' => __('lynx-forms::forms.yes'), 'count' => count(array_filter($values, fn (mixed $value): bool => (bool) $value))],
            ]],
            $field->type === 'number' => $this->numbers($values),
            $field->type === 'date' => $values === [] ? [] : ['earliest' => min(Value::strings($values)), 'latest' => max(Value::strings($values))],
            default => ['latest' => array_slice(Value::strings($values), 0, self::LATEST)],
        };
    }

    /**
     * Every option, in the form's order, with how many chose it; several per answer for checkboxes.
     *
     * @param  list<mixed>  $values
     * @return list<array{value: string, label: string, count: int}>
     */
    private function counts(FormField $field, array $values): array
    {
        $chosen = array_count_values(array_map(strval(...), array_merge(...array_map(fn (mixed $value): array => is_array($value) ? $value : [$value], $values))));

        return array_map(fn (string $value): array => ['value' => $value, 'label' => $field->options[$value]->in(), 'count' => $chosen[$value] ?? 0], array_map(strval(...), array_keys($field->options)));
    }

    /**
     * @param  list<mixed>  $values
     * @return array{average?: float, lowest?: float, highest?: float}
     */
    private function numbers(array $values): array
    {
        $numbers = array_map(fn (mixed $value): float => (float) Value::string($value), array_filter($values, is_numeric(...)));

        return $numbers === [] ? [] : ['average' => round(array_sum($numbers) / count($numbers), 1), 'lowest' => min($numbers), 'highest' => max($numbers)];
    }
}
