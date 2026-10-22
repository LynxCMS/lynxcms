<?php

declare(strict_types=1);

namespace LynxCms\Forms\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use LynxCms\Core\Livewire\UsesLynxPage;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Actions\SubmitForm;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormField;
use LynxCms\Forms\Definition\FormValidator;
use LynxCms\Forms\Livewire\Concerns\EditsRows;
use LynxCms\Forms\Livewire\Concerns\GuardsSubmissions;
use LynxCms\Forms\SubmissionPresenter;
use Throwable;

/**
 * A form on a page (ADR-0052): <livewire:lynx-form form="quote" :page-id="$page->id" />. Steps
 * validated one at a time, fields shown as the answers ask for them, checked when left, group
 * rows, uploads, spam protection, and thanks in place. Extend it to add live behaviour, such as an
 * estimate, and name a view for it in asideView().
 *
 * @api
 */
class FormComponent extends Component
{
    use EditsRows;
    use GuardsSubmissions;
    use UsesLynxPage;
    use WithFileUploads;

    #[Locked]
    public string $form = '';

    /** @var array<string, mixed> answers by field name */
    public array $data = [];

    /** @var array<string, mixed> uploads by file field name */
    public array $files = [];

    public int $step = 0;

    public bool $isSent = false;

    /** A problem that is not a field's, such as too many attempts. */
    public ?string $problem = null;

    public function mount(string $form): void
    {
        $this->form = $form;
        $this->definition();
    }

    /** Checks a field as soon as it changes or is left (wire:model.blur). */
    public function updated(string $property): void
    {
        if (! str_starts_with($property, 'data.') && ! str_starts_with($property, 'files.')) {
            return;
        }

        $key = 'data.'.substr($property, strpos($property, '.') + 1);
        $name = explode('.', $key)[1] ?? '';
        $errors = app(FormValidator::class)->make($this->definition(), $this->answers(), [$name])->errors();
        $this->resetErrorBag($key);

        if ($errors->has($key)) {
            $this->addError($key, Value::string($errors->first($key)));
        }
    }

    public function next(): void
    {
        app(FormValidator::class)->make($this->definition(), $this->answers(), $this->stepFields($this->step))->validate();
        $this->step = min($this->step + 1, max(0, count($this->definition()->steps()) - 1));
    }

    public function back(): void
    {
        $this->step = max(0, $this->step - 1);
    }

    public function submit(): void
    {
        $this->problem = null;
        $wait = $this->secondsUntilAllowed();

        if ($wait !== null) {
            $this->problem = __('lynx-forms::forms.too_many', ['seconds' => $wait]);

            return;
        }

        $this->countAttempt();

        if (! $this->looksLikeSpam()) {
            $this->send();
        }

        if ($this->problem === null) {
            $this->isSent = true;
            $this->dispatch('lynx-form-sent', form: $this->form);
        }
    }

    public function definition(): Form
    {
        return lynx()->forms()->get($this->form) ?? abort(404);
    }

    /**
     * The earlier steps' answers as label and text, shown on the last step before sending.
     *
     * @return list<array{name: string, label: string, value: string}>
     */
    public function summary(): array
    {
        $form = $this->definition();
        $later = $this->stepFields($this->step) ?? [];
        $files = array_map(
            fn (mixed $uploads): array => array_values(array_map(fn (mixed $file): string => is_object($file) && method_exists($file, 'getClientOriginalName') ? Value::string($file->getClientOriginalName()) : '', (array) $uploads)),
            $this->files,
        );
        $lines = app(SubmissionPresenter::class)->linesFor($form->snapshot(), app(FormValidator::class)->values($form, $this->answers()), $files);

        return array_values(array_filter($lines, fn (array $line): bool => ! in_array($line['name'], $later, true)));
    }

    /** A view shown beside the form, for subclasses (an estimate, a summary). */
    public function asideView(): ?string
    {
        return null;
    }

    public function render(): View
    {
        $form = $this->definition();
        $steps = $form->steps();

        return view('lynx-forms::livewire.form', [
            'definition' => $form,
            'steps' => $steps,
            'fields' => $this->visibleFields(),
            'isLastStep' => $steps === [] || $this->step >= count($steps) - 1,
            'aside' => $this->asideView(),
            'summary' => count($steps) > 1 && $this->step === count($steps) - 1 ? $this->summary() : [],
        ]);
    }

    /**
     * The current step's fields the answers show.
     *
     * @return list<FormField>
     */
    public function visibleFields(): array
    {
        $names = $this->stepFields($this->step);
        $answers = $this->answers();

        return array_values(array_filter(
            $this->definition()->fields(),
            fn (FormField $field): bool => ($names === null || in_array($field->name, $names, true)) && $field->isShown($answers),
        ));
    }

    /**
     * Answers and uploads together, as forms.submit takes them.
     *
     * @return array<string, mixed>
     */
    protected function answers(): array
    {
        return array_replace($this->data, array_filter($this->files, fn (mixed $files): bool => $files !== [] && $files !== null));
    }

    /**
     * @return list<string>|null null for a form without steps
     */
    private function stepFields(int $step): ?array
    {
        return ($this->definition()->steps()[$step] ?? null)?->fields;
    }

    private function send(): void
    {
        try {
            lynx()->run(SubmitForm::class, [
                'form' => $this->form,
                'data' => $this->answers(),
                'page' => $this->pageId,
                'language' => $this->lang,
                'meta' => array_filter(['user_agent' => mb_substr((string) request()->userAgent(), 0, 255)]),
            ]);
        } catch (ValidationException $e) {
            $this->step = $this->stepOf((string) array_key_first($e->errors()));

            throw $e;
        } catch (Throwable $e) {
            report($e);
            $this->problem = $this->definition()->text('error')->in();
        }
    }

    /** The step that asks for the field an error key names ("data.email" → the contact step). */
    private function stepOf(string $errorKey): int
    {
        $name = explode('.', $errorKey)[1] ?? '';

        foreach ($this->definition()->steps() as $index => $step) {
            if (in_array($name, $step->fields, true)) {
                return $index;
            }
        }

        return $this->step;
    }
}
