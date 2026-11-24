<?php

declare(strict_types=1);

namespace LynxCms\Forms\Transfer;

/**
 * Texts of an imported form keyed by this site's languages (ADR-0146): the source's default
 * language becomes this site's default, codes this site has stay, and the rest are dropped and
 * remembered, so the import can list them. A plain string is a default language text.
 *
 * @internal
 */
final class FormTexts
{
    /** @var array<string, true> */
    private array $dropped = [];

    /**
     * @param  list<string>  $codes  this site's language codes
     */
    public function __construct(
        private readonly string $sourceDefault,
        private readonly string $targetDefault,
        private readonly array $codes,
    ) {}

    /**
     * @return array<string, string>|null null when no text is left
     */
    public function map(mixed $texts): ?array
    {
        if (is_string($texts)) {
            return $texts === '' ? null : [$this->targetDefault => $texts];
        }

        if (! is_array($texts)) {
            return null;
        }

        $mapped = [];

        foreach ($texts as $code => $text) {
            $code = $code === $this->sourceDefault ? $this->targetDefault : (string) $code;

            if (! in_array($code, $this->codes, true)) {
                $this->dropped[$code] = true;
            } elseif (is_string($text) && $text !== '') {
                $mapped[$code] = $text;
            }
        }

        return $mapped === [] ? null : $mapped;
    }

    /** @return list<string> the codes whose texts were dropped, in the order met */
    public function dropped(): array
    {
        return array_keys($this->dropped);
    }
}
