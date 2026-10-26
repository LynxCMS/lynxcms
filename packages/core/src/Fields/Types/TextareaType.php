<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\Rule;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\HtmlCleaner;
use LynxCms\Core\Fields\PresentsValue;
use LynxCms\Core\Fields\ProvidesSearchText;
use LynxCms\Core\Fields\Types\Concerns\PresentsSharedText;
use LynxCms\Core\Fields\Types\Concerns\ProvidesPlainSearchText;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\SharedValue;
use LynxCms\Core\Support\Value;

/** Multi-line text: plain, HTML (rich text) or Markdown. Translatable. */
final class TextareaType extends BaseFieldType implements DescribesPhpType, PresentsValue, ProvidesSearchText
{
    use PresentsSharedText;
    use ProvidesPlainSearchText;

    public function __construct(private readonly HtmlCleaner $html) {}

    public static function name(): string
    {
        return 'textarea';
    }

    public static function description(): string
    {
        return 'Multi-line text. Setting "format" is "plain" (default), "html" for rich text, or "markdown". Can be translatable.';
    }

    public function supportsTranslation(): bool
    {
        return true;
    }

    public function indexable(): bool
    {
        return false;
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->text('data')->nullable();
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'format' => $schema->string()->enum(['plain', 'html', 'markdown'])->description('How the text is entered and rendered.'),
            'max_length' => $schema->integer()->description('Maximum length in characters.'),
        ];
    }

    public function settingsRules(): array
    {
        return [
            'format' => ['sometimes', Rule::in(['plain', 'html', 'markdown'])],
            'max_length' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    public function rules(array $settings): array
    {
        return array_values(array_filter(['nullable', 'string', isset($settings['max_length']) ? 'max:'.Value::int($settings['max_length']) : null]));
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        if ($value === null) {
            return null;
        }

        // {"markdown": "..."} is converted on the server, so agents never retype HTML.
        if (is_array($value) && isset($value['markdown'])) {
            $markdown = Value::string($value['markdown']);
            $value = ($settings['format'] ?? 'plain') === 'html'
                ? (string) new GithubFlavoredMarkdownConverter(['html_input' => 'strip', 'allow_unsafe_links' => false])->convert($markdown)
                : $markdown;
        }

        $value = str_replace(["\r\n", "\r"], "\n", Value::string($value));

        if (($settings['format'] ?? 'plain') === 'html') {
            $value = $this->html->clean($value);
        }

        return trim($value) === '' ? '' : rtrim($value);
    }

    public function isHtml(array $settings): bool
    {
        return in_array($settings['format'] ?? 'plain', ['html', 'markdown'], true);
    }

    /** A SharedValue, or null when empty (PresentsSharedText). */
    public function phpType(Field $field): string
    {
        return '\\'.SharedValue::class.'|null';
    }
}
