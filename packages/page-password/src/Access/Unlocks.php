<?php

declare(strict_types=1);

namespace LynxCms\PagePassword\Access;

use LynxCms\Core\Support\Value;

/**
 * The pages a browser has unlocked: an encrypted cookie holding, per protected page, the
 * fingerprint of the password it was unlocked with. A changed password no longer matches.
 *
 * @internal
 */
final readonly class Unlocks
{
    public const string COOKIE = 'lynx_page_password';

    /** Unlocks kept in one cookie; the oldest go first. */
    private const int MAX_PAGES = 50;

    public function __construct(private ProtectedRoots $roots) {}

    public function isUnlocked(int $pageId): bool
    {
        $password = $this->roots->passwords()[$pageId] ?? null;
        $fingerprint = $this->fingerprints()[$pageId] ?? null;

        return $password !== null && $password->isSet() && $fingerprint !== null && hash_equals($password->fingerprint(), $fingerprint);
    }

    /**
     * The cookie value that keeps these pages unlocked, with the ones already unlocked.
     *
     * @param  list<int>  $pageIds
     */
    public function cookieValue(array $pageIds): string
    {
        $fingerprints = $this->fingerprints();

        foreach ($pageIds as $pageId) {
            $password = $this->roots->passwords()[$pageId] ?? null;

            if ($password !== null) {
                unset($fingerprints[$pageId]);
                $fingerprints[$pageId] = $password->fingerprint();
            }
        }

        return json_encode(array_slice($fingerprints, -self::MAX_PAGES, preserve_keys: true), JSON_THROW_ON_ERROR);
    }

    /** @return array<int, string> page id => fingerprint, from the request's cookie */
    private function fingerprints(): array
    {
        $decoded = json_decode(Value::string(request()->cookie(self::COOKIE)), true);
        $fingerprints = [];

        foreach (Value::map($decoded) as $pageId => $fingerprint) {
            $fingerprints[(int) $pageId] = Value::string($fingerprint);
        }

        return $fingerprints;
    }
}
