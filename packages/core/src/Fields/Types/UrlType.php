<?php

declare(strict_types=1);

namespace LynxCms\Core\Fields\Types;

use Closure;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Schema\Blueprint;
use LynxCms\Core\Fields\DescribesPhpType;
use LynxCms\Core\Fields\ProvidesSearchText;
use LynxCms\Core\Fields\Types\Concerns\ProvidesPlainSearchText;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Support\Value;

/**
 * A web address: http or https only, so a link never runs script (javascript:) when a template
 * prints it in an href. With "relative", a path on the site ("/contact/") is accepted too.
 * Can be translatable, for addresses that differ per language.
 */
final class UrlType extends BaseFieldType implements DescribesPhpType, ProvidesSearchText
{
    use ProvidesPlainSearchText;

    private const int MAX_LENGTH = 2048;

    public static function name(): string
    {
        return 'url';
    }

    public static function description(): string
    {
        return 'A web address starting with http:// or https://, e.g. a website. Setting "relative": true also accepts a path on the site, such as /contact/. Can be translatable.';
    }

    public function supportsTranslation(): bool
    {
        return true;
    }

    public function columns(Blueprint $table, array $settings): void
    {
        $table->string('data', self::MAX_LENGTH)->nullable();
    }

    public function settingsSchema(JsonSchema $schema): array
    {
        return [
            'relative' => $schema->boolean()->description('Also accept a path on the site, such as /contact/. Default false.'),
        ];
    }

    public function settingsRules(): array
    {
        return ['relative' => ['sometimes', 'boolean']];
    }

    public function rules(array $settings): array
    {
        $allowsPaths = ($settings['relative'] ?? false) === true;

        return ['nullable', 'string', 'max:'.self::MAX_LENGTH, function (string $attribute, mixed $value, Closure $fail) use ($allowsPaths): void {
            if (! $this->isWebAddress(Value::string($value)) && (! $allowsPaths || ! $this->isPath(Value::string($value)))) {
                $fail($allowsPaths ? 'Enter an address starting with https:// or a path starting with /.' : 'Enter an address starting with https://.');
            }
        }];
    }

    public function normalize(mixed $value, array $settings): mixed
    {
        $url = trim(Value::string($value));

        return $url === '' ? null : $url;
    }

    public function phpType(Field $field): string
    {
        return 'string|null';
    }

    private function isWebAddress(string $url): bool
    {
        // Not FILTER_VALIDATE_URL: it refuses accented domains and paths (açores.pt/formação/).
        $parts = parse_url($url);

        return is_array($parts) && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true)
            && ($parts['host'] ?? '') !== '' && preg_match('/[\s<>"]/u', $url) !== 1;
    }

    /** A path on this site; "//host" would leave it. */
    private function isPath(string $url): bool
    {
        return str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\') && preg_match('/\s/', $url) !== 1;
    }
}
