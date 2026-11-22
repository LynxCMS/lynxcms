<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages\Saving;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\MergesInput;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageLoader;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Pages\PathResolver;
use LynxCms\Core\Support\Value;
use LynxCms\Core\Templates\TemplateRegistry;

/**
 * The page a pages.save input describes: a Page object as given (PHP API), or an existing or new
 * page with the input's names, values and status applied (MCP and the AI agent).
 *
 * @internal
 */
final readonly class PageInput
{
    public function __construct(
        private PageLoader $loader,
        private TemplateRegistry $templates,
        private LanguageRegistry $languages,
        private LanguageContext $context,
        private PathResolver $paths,
    ) {}

    public function page(Input $input): Page
    {
        $page = $input->get('page');

        if ($page instanceof Page) {
            return $page;
        }

        $page = $input->int('id') !== null ? $this->existing($input) : $this->new($input);
        $this->applyNames($page, $input->array('names'));
        $this->applyValues($page, $input);

        if ($input->has('status')) {
            $page->status = PageStatus::fromNames(Value::strings($input->array('status')));
        }

        return $page;
    }

    private function existing(Input $input): Page
    {
        $id = (int) $input->int('id');
        $page = $this->loader->find($id) ?? throw ValidationException::withMessages(['id' => Value::string(__('lynx::validation.page.missing', ['page' => $id]))]);

        if ($input->string('template') !== null && $this->templates->get($input->string('template'))->id !== $page->templateId) {
            throw ValidationException::withMessages(['template' => Value::string(__('lynx::validation.page.switch_template'))]);
        }

        return $page;
    }

    private function new(Input $input): Page
    {
        $name = $input->string('template') ?? throw ValidationException::withMessages(['template' => Value::string(__('lynx::validation.page.template_required'))]);
        $template = $this->templates->find($name) ?? throw ValidationException::withMessages(['template' => Value::string(__('lynx::validation.template.missing', ['template' => $name]))]);

        return new Page($template->id, $this->parentId($input->get('parent')));
    }

    /**
     * @param  array<array-key, mixed>  $names
     */
    private function applyNames(Page $page, array $names): void
    {
        foreach ($names as $code => $entry) {
            $code = (string) $code;
            $entry = is_array($entry) ? $entry : ['name' => $entry];
            $page->setLanguageName($code, Value::string($entry['name'] ?? $page->name($code)), isset($entry['active']) ? Value::bool($entry['active']) : null);
        }
    }

    /**
     * Translatable fields take {"code": value}; anything else is the value in the input's language.
     * Shared fields refuse {"code": value}.
     */
    private function applyValues(Page $page, Input $input): void
    {
        $language = $input->string('language') !== null ? $this->languages->get($input->string('language')) : $this->context->current();

        foreach ($input->array('values') as $fieldName => $value) {
            $templateField = $page->templateField((string) $fieldName)
                ?? throw ValidationException::withMessages(["values.{$fieldName}" => Value::string(__('lynx::validation.values.no_field', ['field' => $fieldName]))]);

            $isLanguageMap = is_array($value) && $value !== [] && $this->isLanguageMap($value);

            // A shared field would turn the map into an empty value; say so instead of dropping it.
            if ($isLanguageMap && ! $templateField->field->isTranslatable()) {
                throw ValidationException::withMessages(["values.{$fieldName}" => Value::string(__('lynx::validation.values.not_translatable', ['field' => $fieldName]))]);
            }

            if ($isLanguageMap) {
                foreach ($value as $code => $languageValue) {
                    $page->setLanguageValue((string) $code, (string) $fieldName, $languageValue);
                }
            } else {
                $type = $templateField->field->fieldType();

                // Item fields take input for one language and keep the others (ADR-0044).
                if ($type instanceof MergesInput) {
                    $value = $type->merge($page->getUnformatted((string) $fieldName), $value, (string) $language->code, $templateField->field->config());
                }

                $page->setLanguageValue($language, (string) $fieldName, $value);
            }
        }
    }

    private function parentId(mixed $parent): ?int
    {
        if ($parent === null || $parent === '') {
            return null;
        }

        if (is_int($parent) || (is_string($parent) && ctype_digit($parent))) {
            return (int) $parent;
        }

        if (! is_string($parent)) {
            throw ValidationException::withMessages(['parent' => Value::string(__('lynx::validation.parent.reference'))]);
        }

        return $this->paths->resolve($this->languages->default(), $parent)[0]
            ?? throw ValidationException::withMessages(['parent' => Value::string(__('lynx::validation.parent.no_path', ['path' => $parent]))]);
    }

    /**
     * @param  array<mixed>  $value
     */
    private function isLanguageMap(array $value): bool
    {
        return array_all(array_keys($value), fn (int|string $key): bool => is_string($key) && $this->languages->find($key) !== null);
    }
}
