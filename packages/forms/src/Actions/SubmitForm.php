<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Definition\Form;
use LynxCms\Forms\Definition\FormValidator;
use LynxCms\Forms\Events\FormSubmitted;
use LynxCms\Forms\FormRegistry;
use LynxCms\Forms\FormsFiles;
use LynxCms\Forms\Models\Submission;

/**
 * Receives a form's answers (ADR-0052): validates them in the visitor's language, stores the
 * submission with a snapshot of the form and its files on the private forms disk, and announces
 * it after commit, which mails the people who handle it. For visitors, so not an agent tool.
 */
final class SubmitForm extends Action
{
    public function __construct(
        private readonly FormRegistry $forms,
        private readonly FormValidator $validator,
        private readonly LanguageRegistry $languages,
        private readonly LanguageContext $context,
        private readonly FormsFiles $files,
    ) {}

    public static function name(): string
    {
        return 'forms.submit';
    }

    public static function description(): string
    {
        return 'Send answers to a form, as a visitor does.';
    }

    public static function permission(): string
    {
        return self::EVERYONE;
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return [
            'form' => ['required', 'string'],
            'data' => ['sometimes', 'array'],
            'page' => ['sometimes', 'nullable'],
            'language' => ['sometimes', 'nullable', 'string'],
            'meta' => ['sometimes', 'array'],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'form' => $schema->string()->description('The form\'s key.')->required(),
            'data' => $schema->object()->description('Answers by field name.'),
            'page' => $schema->string()->description('Id or path of the page the form was on.'),
            'language' => $schema->string()->description('The visitor\'s language code. Defaults to the current language.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Submission
    {
        $key = (string) $input->string('form');
        $form = $this->forms->get($key) ?? throw ValidationException::withMessages(['form' => "There is no form [{$key}]."]);
        $language = $input->string('language') !== null ? $this->languages->get($input->string('language')) : $this->context->current();
        $data = $input->array('data');

        lynx()->language((string) $language->code, fn () => $this->validator->make($form, $data)->validate());

        $submission = $this->store($form, $data, $language, $input, $context->actor);
        $this->storeFiles($submission, $this->validator->files($form, $data), $context->dryRun);
        $context->changes->created('form_submission', $submission->id, "Receive a {$form->key()} submission", ['form' => $form->key(), 'uuid' => $submission->uuid]);
        $context->event(new FormSubmitted($submission->id));

        return $submission;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function store(Form $form, array $data, Language $language, Input $input, Actor $actor): Submission
    {
        $page = $input->get('page') === null ? null : lynx()->pages->get(Value::string($input->get('page')));

        return Submission::query()->create([
            'uuid' => (string) Str::uuid(),
            'form' => $form->key(),
            'page_id' => $page?->id,
            'language_id' => $language->id,
            // A signed-in visitor (a member); not staff or agents submitting for someone else
            'user_id' => $actor->kind === Actor::USER && $actor->id !== null ? (string) $actor->id : null,
            'data' => $this->validator->values($form, $data),
            'fields' => $form->snapshot(),
            'status' => 'new',
            'meta' => $input->array('meta') === [] ? null : $input->array('meta'),
        ]);
    }

    /**
     * @param  array<string, list<UploadedFile>>  $files
     */
    private function storeFiles(Submission $submission, array $files, bool $dryRun): void
    {
        foreach ($files as $field => $uploads) {
            foreach ($uploads as $upload) {
                $path = $submission->folder().'/'.$field.'-'.Str::lower(Str::random(8)).'.'.strtolower($upload->getClientOriginalExtension());

                if (! $dryRun) {
                    $this->files->disk()->putFileAs(dirname($path), $upload, basename($path));
                    DB::afterRollBack(fn () => $this->files->disk()->delete($path));
                }

                $submission->files()->create(['field' => $field, 'path' => $path, 'name' => $upload->getClientOriginalName(), 'mime' => (string) $upload->getMimeType(), 'bytes' => (int) $upload->getSize()]);
            }
        }
    }
}
