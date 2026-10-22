---
title: Sign-in and accounts
description: Sign in, password reset, invitations, two-factor authentication and passkeys.
order: 1
---

# Sign-in and accounts

Lynx signs people in with Laravel Fortify, under the admin prefix:

| URL | Screen |
| --- | --- |
| `/lynx/login` | Sign in, with email and password or a passkey. |
| `/lynx/forgot-password` | Email a reset link. |
| `/lynx/two-factor-challenge` | The code from an authenticator app, or a recovery code. |
| `/lynx/invitation/{token}` | An invited user chooses a password. |
| `/lynx/account/security` | Password, two-factor authentication and passkeys. |

There is no registration. Superusers invite people.

## Turning it off

`LYNX_ADMIN_AUTH` is `auto` by default: Lynx's sign-in is on unless your app has its own `config/fortify.php`, in which case your screens sign users in and Lynx leaves Fortify alone. Set it to `false` to turn Lynx's sign-in off, or `true` to force it on.

## Invitations

Under **Access > Users**, a superuser fills in a name, an email and roles, then **Send invitation**. Lynx creates the user with an unusable password, gives them the roles and emails a link to choose a password. The link works for 7 days (`lynx-admin.auth.invitation_days`). Once they choose a password, they are signed in.

Until they choose a password, the Users screen marks them **Invitation pending**. **Resend invitation** emails a new link, and the old one stops working.

The email needs a working mail setup in your app.

## Your user model

Users stay your app's: Lynx uses the model of the `users` auth provider. For each feature:

| Feature | Needs |
| --- | --- |
| Sign in, password reset | `Illuminate\Foundation\Auth\User` and the `Notifiable` trait. |
| Invitations | An Eloquent model with `name`, `email` and `password`. |
| Two-factor authentication | Fortify's `TwoFactorAuthenticatable` trait. |
| Passkeys | Fortify's `PasskeyAuthenticatable` trait and the `PasskeyUser` contract. |

```php
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

class User extends Authenticatable implements PasskeyUser
{
    use Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;
}
```

Lynx's migration adds the two-factor columns to `users` and creates the `passkeys` table when they are missing. Without the traits, the account screen shows only the password section.

## Two-factor authentication

On **Account security**, **Turn on** shows a QR code and a setup key. Two-factor authentication counts only once a code from the app confirms it. The recovery codes are shown then; each signs you in once if you lose your phone.

## Passkeys

**Add passkey** registers the device's passkey (fingerprint, face or PIN). The sign-in screen then offers **Sign in with a passkey**. Passkeys need HTTPS (or `localhost`) and `APP_URL` set to the site's address.
