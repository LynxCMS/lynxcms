<?php

declare(strict_types=1);

namespace LynxCms\Forms\Actions;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\FormSchema;
use LynxCms\Forms\Rules\FormName;
use LynxCms\Forms\Transfer\FormExporter;
use LynxCms\Forms\Transfer\FormTransferError;

/**
 * Copies a builder form within the site (ADR-0146): its structure through the export and
 * forms.import, then the settings that belong to this site and that an export leaves out
 * (recipients, one answer per account, retention, the daily summary). The copy is unpublished,
 * named "{name}-copy" unless a name is given.
 *
 * @api
 */
final class DuplicateForm extends Action
{
    /** Form settings a copy keeps on the same site. */
    private const array SITE_SETTINGS = [FormSchema::RECIPIENTS, FormSchema::ONCE_PER_USER, FormSchema::RETENTION, FormSchema::DIGEST];

    public function __construct(private readonly FormExporter $exporter) {}

    public static function name(): string
    {
        return 'forms.duplicate';
    }

    public static function description(): string
    {
        return 'Copy a form built in the admin, unpublished, with its fields, messages and settings. name is the copy\'s key; without it, "{form}-copy".';
    }

    public static function permission(): string
    {
        return 'forms.manage';
    }

    public static function rules(): array
    {
        return ['form' => ['required', 'string'], 'name' => ['sometimes', 'string', new FormName]];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'form' => $schema->string()->description('The form to copy, by name.')->required(),
            'name' => $schema->string()->description('The copy\'s name: lowercase letters, digits and dashes.'),
        ];
    }

    /** @return array{name: string, page: int} */
    public function handle(Input $input, ActionContext $context): array
    {
        $source = (string) $input->string('form');

        try {
            $file = $this->exporter->export($source);
        } catch (FormTransferError) {
            throw ValidationException::withMessages(['form' => Value::string(__('lynx-forms::validation.form.missing', ['form' => $source]))]);
        }

        $name = $input->string('name') ?? $this->freeName($source);
        $copy = Value::map($context->run(ImportForm::class, ['form' => $file, 'name' => $name]));
        $context->run(SavePage::class, ['id' => Value::int($copy['page'] ?? 0), 'values' => $this->siteSettings($source)]);

        return ['name' => $name, 'page' => Value::int($copy['page'] ?? 0)];
    }

    /** "contact-copy", or "contact-copy-2" and on when that is taken. */
    private function freeName(string $source): string
    {
        $name = "{$source}-copy";

        for ($n = 2; lynx()->forms()->get($name) !== null || lynx()->pages->findOne('template='.FormSchema::FORM_TEMPLATE.', name='.$name.', include=all') !== null; $n++) {
            $name = "{$source}-copy-{$n}";
        }

        return $name;
    }

    /** @return array<string, mixed> */
    private function siteSettings(string $source): array
    {
        $page = lynx()->pages->findOne('template='.FormSchema::FORM_TEMPLATE.', name='.$source.', include=all');
        $values = [];

        foreach (self::SITE_SETTINGS as $field) {
            if ($page instanceof Page && $page->templateField($field) !== null) {
                $values[$field] = $page->getUnformatted($field);
            }
        }

        return array_filter($values, fn (mixed $value): bool => $value !== null);
    }
}
