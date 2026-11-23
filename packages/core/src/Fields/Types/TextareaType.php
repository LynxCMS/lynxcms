<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Validation\Rule;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\HtmlCleaner;
use LynxCms\Core\Fields\PageLinks;
use LynxCms\Core\Fields\PresentsValue;
use LynxCms\Core\Fields\ProvidesSearchText;
use LynxCms\Core\Fields\ReferencesPages;
use LynxCms\Core\Fields\Types\Concerns\PresentsSharedText;
use LynxCms\Core\Fields\Types\Concerns\ProvidesPlainSearchText;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Pages\SharedValue;
use LynxCms\Core\Support\Value;

/**
 * Multi-line text: plain, HTML (rich text) or Markdown. Translatable.
 *
 * @internal
 */
final class TextareaType extends BaseFieldType implements DescribesPhpType, PresentsValue, ProvidesSearchText, ReferencesPages
{
    use PresentsSharedText;
    use ProvidesPlainSearchText;

    /** Rich text tools a field can turn on (setting "tools"), all off by default (ADR-0107). */
    public const array TOOLS = ['superscript', 'subscript', 'underline', 'highlight', 'code', 'code_block', 'horizontal_rule', 'text_align'];

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
            'tools' => $schema->array()->items($schema->string()->enum(self::TOOLS))->description('Rich text only: extra editor tools, off unless listed.'),
            'max_length' => $schema->integer()->description('Maximum length in characters.'),
        ];
    }

    public function settingsRules(): array
    {
        return [
            'format' => ['sometimes', Rule::in(['plain', 'html', 'markdown'])],
            'tools' => ['sometimes', 'array'],
            'tools.*' => ['string', Rule::in(self::TOOLS)],
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

    /**
     * Whether values are rich text, whose links may name a page (PageLinks, ADR-0145).
     *
     * @param  array<string, mixed>  $settings
     */
    public static function holdsPageLinks(array $settings): bool
    {
        return ($settings['format'] ?? 'plain') === 'html';
    }

    /** The pages links in rich text name; none for plain text and Markdown. */
    public function pageIds(mixed $value, array $settings): array
    {
        return self::holdsPageLinks($settings) && is_string($value) ? PageLinks::ids($value) : [];
    }

    /** Each link's data-page mapped: to a page key on export, back to an id on import; dropped when null. */
    public function mapPageIds(mixed $value, Closure $map, array $settings): mixed
    {
        if (! self::holdsPageLinks($settings) || ! is_string($value) || ! str_contains($value, PageLinks::ATTRIBUTE.'=')) {
            return $value;
        }

        return preg_replace_callback('/<a\s[^>]*>/i', fn (array $tag): string => (string) preg_replace_callback(
            '/\s'.PageLinks::ATTRIBUTE.'="([^"]*)"/',
            function (array $attribute) use ($map): string {
                $reference = html_entity_decode($attribute[1]);
                $mapped = $map(ctype_digit($reference) ? (int) $reference : $reference);

                return $mapped === null ? '' : ' '.PageLinks::ATTRIBUTE.'="'.e((string) $mapped).'"';
            },
            $tag[0],
        ), $value) ?? $value;
    }

    public function whereReferences(Builder $query, int $pageId, array $settings): void
    {
        self::holdsPageLinks($settings)
            ? $query->where('data', 'like', '%'.PageLinks::ATTRIBUTE.'="'.$pageId.'"%')
            : $query->whereRaw('1 = 0');
    }

    /** A SharedValue, or null when empty (PresentsSharedText). */
    public function phpType(Field $field): string
    {
        return '\\'.SharedValue::class.'|null';
    }
}
