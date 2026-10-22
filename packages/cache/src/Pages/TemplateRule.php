<?php

declare(strict_types=1);

namespace LynxCms\Cache\Pages;

/**
 * How pages of one template are cached: for how long, with which query parameters, what a change
 * to one of them clears, and whether pages with forms are stored.
 *
 * @internal
 */
final readonly class TemplateRule
{
    public const string CLEAR_ALL = 'all';

    /**
     * @param  int  $lifetime  seconds, 0 for until content changes
     * @param  list<string>  $parameters
     * @param  list<string>  $clear  scopes: self, parents, children, siblings, home or all
     * @param  bool  $refreshesToken  pages holding the CSRF token are stored with a placeholder
     *                                that a script replaces with a fresh token (TokenRefresh)
     */
    public function __construct(public int $lifetime, public array $parameters, public array $clear, public bool $refreshesToken = false) {}

    /** Whether a normalized query uses only allowed parameters. */
    public function allowsQuery(string $query): bool
    {
        return array_all($query === '' ? [] : explode('&', $query), fn ($pair): bool => in_array(rawurldecode(explode('=', $pair, 2)[0]), $this->parameters, true));
    }

    public function clearsEverything(): bool
    {
        return in_array(self::CLEAR_ALL, $this->clear, true);
    }
}
