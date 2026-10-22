<?php

declare(strict_types=1);

namespace LynxCms\Seo\JsonLd;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use LynxCms\Core\Fields\Choices\Choice;
use LynxCms\Core\Fields\Choices\ChoiceList;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageArray;
use LynxCms\Media\Values\MediaList;
use LynxCms\Media\Values\Pagefile;
use LynxCms\Seo\Support\AbsoluteUrl;
use LynxCms\Seo\Support\PageText;
use Stringable;
use Throwable;

/**
 * Turns a field value into the JSON-LD value a property's kind needs: plain text, a full URL, an
 * ISO 8601 date, a number, a schema.org enumeration member, or a list of URLs. Empty becomes null.
 *
 * @internal
 */
final readonly class ValueConverter
{
    private const string SCHEMA_ORG = 'https://schema.org/';

    public function __construct(private PageText $text) {}

    /** @param  string  $property  the full dotted property, for enumerations */
    public function convert(mixed $value, string $kind, string $property): mixed
    {
        $converted = match ($kind) {
            SchemaTypes::URL => $this->url($value),
            SchemaTypes::IMAGE => $this->image($value),
            SchemaTypes::DATE => $this->date($value),
            SchemaTypes::NUMBER => $this->number($value),
            SchemaTypes::ENUM => $this->member($value, $property),
            SchemaTypes::LINES => $this->lines($value),
            default => $this->plain($value),
        };

        return $converted === '' || $converted === [] ? null : $converted;
    }

    public function plain(mixed $value): string
    {
        return match (true) {
            $value instanceof Choice => $value->label(),
            $value instanceof ChoiceList => implode(', ', $value->labels()),
            $value instanceof PageArray => $this->plain($value->first()),
            $value instanceof Page => $this->text->of($value, 'title'),
            is_scalar($value), $value instanceof Stringable => $this->text->plain((string) $value),
            default => '',
        };
    }

    private function url(mixed $value): ?string
    {
        $page = $value instanceof PageArray ? $value->first() : $value;

        if ($page instanceof Page) {
            return AbsoluteUrl::of($page->localUrl(lynx()->languages->current()));
        }

        $url = $this->plain($value);

        return $url === '' ? null : AbsoluteUrl::of($url);
    }

    private function image(mixed $value): ?string
    {
        $file = class_exists(MediaList::class) && $value instanceof MediaList ? $value->first() : $value;

        if (class_exists(Pagefile::class) && $file instanceof Pagefile) {
            return AbsoluteUrl::of($file->url());
        }

        return is_string($file) && $file !== '' ? AbsoluteUrl::of($file) : null;
    }

    /** A date alone when it has no time of day, else the full date and time with its offset. */
    private function date(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            $date = CarbonImmutable::instance($value);
        } else {
            $text = $this->plain($value);

            try {
                $date = $text === '' ? null : CarbonImmutable::parse($text);
            } catch (Throwable) {
                return null;
            }
        }

        if ($date === null) {
            return null;
        }

        return $date->format('H:i:s') === '00:00:00' ? $date->toDateString() : $date->toIso8601String();
    }

    private function number(mixed $value): int|float|null
    {
        if (is_int($value) || is_float($value)) {
            return $value;
        }

        $text = $this->plain($value);

        if (! is_numeric($text)) {
            return null;
        }

        return str_contains($text, '.') ? (float) $text : (int) $text;
    }

    /** "cancelled" or "EventCancelled" for eventStatus gives https://schema.org/EventCancelled. */
    private function member(mixed $value, string $property): ?string
    {
        $written = $value instanceof Choice ? $value->value : $this->plain($value);
        $member = SchemaTypes::ENUMS[$property][strtolower($written)] ?? $written;

        return preg_match('/^[A-Z][A-Za-z]+$/', $member) === 1 ? self::SCHEMA_ORG.$member : null;
    }

    /** @return list<string> */
    private function lines(mixed $value): array
    {
        $lines = array_map(trim(...), preg_split('/\R/', is_string($value) ? $value : '') ?: []);

        return array_values(array_map(AbsoluteUrl::of(...), array_filter($lines, fn (string $line): bool => $line !== '')));
    }
}
