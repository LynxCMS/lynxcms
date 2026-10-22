<?php

declare(strict_types=1);

namespace LynxCms\Admin\Auth;

use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Database\Eloquent\Model;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\TwoFactorAuthenticatable;
use LynxCms\Core\Support\Value;

/**
 * A user's two-factor authentication as Fortify stores it, read through the model's attributes so
 * the admin works with any user model that uses Fortify's TwoFactorAuthenticatable (ADR-0041).
 *
 * @internal
 */
final class TwoFactorState
{
    private const int QR_SIZE = 192;

    /** The user, when the app's model can use two-factor authentication. */
    public static function user(): ?Model
    {
        $user = auth()->user();

        return Features::canManageTwoFactorAuthentication() && $user instanceof Model
            && in_array(TwoFactorAuthenticatable::class, class_uses_recursive($user), true) ? $user : null;
    }

    public static function isEnabled(Model $user): bool
    {
        return $user->getAttribute('two_factor_secret') !== null && $user->getAttribute('two_factor_confirmed_at') !== null;
    }

    public static function setupKey(Model $user): string
    {
        return Value::string(Fortify::currentEncrypter()->decrypt(Value::string($user->getAttribute('two_factor_secret'))));
    }

    public static function qrCodeSvg(Model $user): string
    {
        $url = app(TwoFactorAuthenticationProvider::class)->qrCodeUrl(
            Value::string(config('app.name'), 'Lynx'),
            Value::string($user->getAttribute(Fortify::username())),
            self::setupKey($user),
        );
        $svg = new Writer(new ImageRenderer(
            new RendererStyle(self::QR_SIZE, 0, null, null, Fill::uniformColor(new Rgb(255, 255, 255), new Rgb(45, 55, 72))),
            new SvgImageBackEnd,
        ))->writeString($url);

        return trim(substr($svg, (int) strpos($svg, "\n") + 1));
    }

    /** @return list<string> */
    public static function recoveryCodes(Model $user): array
    {
        $codes = $user->getAttribute('two_factor_recovery_codes');

        return $codes === null ? [] : Value::strings(json_decode(Value::string(Fortify::currentEncrypter()->decrypt(Value::string($codes))), true));
    }
}
