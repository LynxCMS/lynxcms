<?php

declare(strict_types=1);

namespace LynxCms\Cache\Islands;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use JsonException;
use LynxCms\Core\Support\Value;

/**
 * A part of a stored page rendered for each visitor (ADR-0064): a Blade view or a Livewire
 * component, its parameters, and the page and language it belongs to. It travels encrypted with
 * the app key, so visitors cannot ask for views or components the page does not hold.
 *
 * @internal
 */
final readonly class Island
{
    public const string VIEW = 'view';

    public const string LIVEWIRE = 'livewire';

    /** @param array<string, mixed> $with */
    public function __construct(public string $type, public string $name, public array $with, public ?int $pageId, public string $language) {}

    public function encrypt(): string
    {
        return Crypt::encryptString(json_encode([$this->type, $this->name, $this->with, $this->pageId, $this->language], JSON_THROW_ON_ERROR));
    }

    /** The island in a payload, or null when it was changed or made with another key. */
    public static function decrypt(string $payload): ?self
    {
        try {
            $data = json_decode(Crypt::decryptString($payload), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            return null;
        }

        $data = Value::list($data);
        $type = Value::string($data[0] ?? null);

        return in_array($type, [self::VIEW, self::LIVEWIRE], true)
            ? new self($type, Value::string($data[1] ?? null), Value::map($data[2] ?? null), Value::nullableInt($data[3] ?? null), Value::string($data[4] ?? null))
            : null;
    }
}
