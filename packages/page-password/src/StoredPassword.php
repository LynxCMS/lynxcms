<?php

declare(strict_types=1);

namespace LynxCms\PagePassword;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use LynxCms\Core\Support\Value;

/**
 * A page's password as templates and the module read it: encrypted with Laravel's Crypt (the
 * app key), so editors can read it again to send it to guests, and compared in constant time.
 *
 * @api
 */
final readonly class StoredPassword
{
    public function __construct(private ?string $stored) {}

    /** The stored form of a password typed by an editor. */
    public static function encrypt(string $password): string
    {
        return Crypt::encryptString($password);
    }

    /**
     * Whether $value is already a stored password, so saving it again keeps it instead of
     * encrypting it twice: Crypt can read it with this app key. A typed password never decrypts.
     */
    public static function isStored(string $value): bool
    {
        return new self($value)->reveal() !== null;
    }

    public function isSet(): bool
    {
        return $this->reveal() !== null;
    }

    public function matches(string $password): bool
    {
        $own = $this->reveal();

        return $own !== null && hash_equals($own, $password);
    }

    /** The password, for editors; null when none is set or the app key changed. */
    public function reveal(): ?string
    {
        if ($this->stored === null || $this->stored === '') {
            return null;
        }

        try {
            return Crypt::decryptString($this->stored);
        } catch (DecryptException) {
            return null;
        }
    }

    /** Changes whenever the password is set again, so unlocks of an older password stop working. */
    public function fingerprint(): string
    {
        return substr(hash_hmac('sha256', (string) $this->stored, Value::string(config('app.key'))), 0, 32);
    }
}
