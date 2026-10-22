<?php

declare(strict_types=1);

namespace LynxCms\Cache\Fragments;

/**
 * Which copy of a fragment a visitor gets: its name, the language, the audience (guests, split by
 * cookies that change what they may see) and the template's own vary values.
 *
 * @internal
 */
final readonly class FragmentKey
{
    /**
     * @param  list<string>  $vary
     * @param  list<string>  $dependsOn
     */
    public function __construct(
        public string $name,
        public string $language,
        public string $audience,
        public array $vary,
        public array $dependsOn,
        public ?int $ttl,
    ) {}

    public function cacheKey(): string
    {
        return 'lynx-cache:fragment:'.sha1(json_encode([$this->name, $this->language, $this->audience, $this->vary], JSON_THROW_ON_ERROR));
    }
}
