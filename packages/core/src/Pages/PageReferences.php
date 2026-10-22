<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Languages\LanguageRegistry;
use LynxCms\Core\Query\PageFinder;

/**
 * Turns a page reference string into a page id, as ProcessWire's $pages->get() does (ADR-0029):
 * all digits is an id, anything with a slash is a default-language path, and a bare page name is
 * the first page with that name in the default language at any depth, whatever its status.
 *
 * @internal
 */
final readonly class PageReferences
{
    public function __construct(
        private PathResolver $paths,
        private LanguageRegistry $languages,
        private LanguageContext $context,
        private PageFinder $finder,
        private RootPage $root,
    ) {}

    public function id(string $reference): ?int
    {
        return $this->resolve($reference, fn (string $name): ?int => $this->named($name, 1)[0] ?? null);
    }

    /**
     * Like id(), for writes: a bare name must belong to exactly one page.
     *
     * @param  string  $key  the input key an ambiguity error belongs to
     */
    public function single(string $reference, string $key): ?int
    {
        return $this->resolve($reference, function (string $name) use ($key): ?int {
            $ids = $this->named($name, 2);

            if (count($ids) > 1) {
                throw ValidationException::withMessages([$key => "Several pages are named [{$name}]; give the page's path or id."]);
            }

            return $ids[0] ?? null;
        });
    }

    /**
     * @param  callable(string): ?int  $byName
     */
    private function resolve(string $reference, callable $byName): ?int
    {
        $reference = trim($reference);
        $path = trim($reference, '/');

        return match (true) {
            $path === '' => $this->root->id(),
            ctype_digit($reference) => (int) $reference,
            str_contains($reference, '/') => $this->paths->resolve($this->languages->default(), $path)[0] ?? null,
            default => $byName($reference),
        };
    }

    /**
     * Pages with $name in the default language, first in sort order. Values that are not page
     * names (spaces, capitals) match nothing.
     *
     * @return list<int>
     */
    private function named(string $name, int $limit): array
    {
        if (PageName::from($name) !== $name) {
            return [];
        }

        return $this->context->with(
            $this->languages->default(),
            fn (): array => $this->finder->find("name={$name}, include=all, limit={$limit}")->ids(),
        );
    }
}
