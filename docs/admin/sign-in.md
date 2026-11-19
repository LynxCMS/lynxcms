---
title: Sign-in and accounts
description: Sign in, password reset, invitations, two-factor authentication and passkeys.
order: 1
---

# Sign-in and accounts

The admin comes with everything people need to sign in: passwords and password resets, two-factor authentication, passkeys and invitations for new users. It is built on [Laravel Fortify](https://laravel.com/docs/fortify), Laravel's sign-in package, and its screens live under the admin prefix:

| URL | Screen |
| --- | --- |
| `/lynx/login` | Sign in, with email and password or a passkey. |
| `/lynx/forgot-password` | Email a reset link. |
| `/lynx/two-factor-challenge` | The code from an authenticator app, or a recovery code. |
| `/lynx/invitation/{token}` | An invited user chooses a password. |
| `/lynx/account/security` | Password, two-factor authentication and passkeys. |

There is no registration. Superusers invite people. The first superuser comes from `php artisan lynx:install`, which asks for their name, email and password, or from `php artisan lynx:superuser` at any time (see [Installation](/getting-started/installation#running-the-installer)).

## Who signs users in

`LYNX_ADMIN_AUTH` decides:

| Value | Sign-in | Invitations and Account security |
| --- | --- | --- |
| `auto` (default) | Lynx's screens above, unless your app has its own `config/fortify.php`; then as `app`. | Yes |
| `app` | Your app's Fortify screens, for everyone. Lynx registers no Fortify routes of its own. | Yes |
| `false` | Neither: your app signs users in its own way, such as single sign-on. | No |

### A site where visitors sign up

A site with a members area, bookings or surveys lets visitors create accounts. Use your app's Fortify for that, with registration on, and Lynx switches to `app` by itself once `config/fortify.php` exists:

- Everyone signs in at your app's `/login`, staff included. A staff member who opens `/lynx` while signed out goes to `/login` and comes back to the admin afterwards.
- People who sign up get the `guest` role, so they see the site and never the admin (see [People who sign up](/admin/permissions#people-who-sign-up)).
- Superusers still invite staff from **Access > Users**. The invitation link opens Lynx's screen to choose a password, which then signs them in.
- **Account security** stays in the admin's user menu. It offers two-factor authentication only when your Fortify config turns `Features::twoFactorAuthentication()` on, since your sign-in is what asks for the code. Passkeys follow `Features::passkeys()` the same way.

## Invitations

Under **Access > Users**, a superuser fills in a name, an email and roles under **Add user**, chooses **Send an invitation by email**, then **Send invitation**. Lynx creates the user with an unusable password, gives them the roles and emails a link to choose a password. The link works for 7 days (`lynx-admin.auth.invitation_days`). Once they choose a password, they are signed in.

Until they choose a password, the Users screen marks them **Invitation pending**. **Resend invitation** emails a new link, and the old one stops working.

The email needs a working mail setup in your app. Without one, use temporary passwords.

## Password rules

Every form that sets a password (reset, invitation, new password, **Account security**) follows your app's `Password::defaults()` and lists its rules under the inputs. Each rule is ticked as the user types, and a last line says whether both passwords match. The server checks the rules again when the form is sent; "not found in known data breaches" is checked only then.

Without `Password::defaults()`, Laravel asks for 8 characters. Set your own in a service provider:

```php
use Illuminate\Validation\Rules\Password;

Password::defaults(fn () => app()->isProduction()
    ? Password::min(12)->uncompromised()
    : Password::min(12));
```

## Temporary passwords

A superuser can create a user with a temporary password instead of sending an invitation (ADR-0126). Under **Add user**, choose **Create a temporary password**, then **Create user**. Lynx creates the user with their roles and shows a random password of 20 letters, digits and symbols, once, with a button to copy it. Lynx stores only its hash and can't show it again. The **Copy** button needs HTTPS, as browsers allow copying only on secure pages. Without it, the button is disabled and the password can be selected and copied by hand.

The user is required to set a new password on first sign-in, before any other admin screen opens. The new password must differ from the temporary one. The temporary password expires after 3 days (`lynx-admin.auth.temporary_password_days`). Until the user sets a new password, the Users screen marks them **Temporary password**.

**New temporary password**, on each user's row, replaces the password of a user who forgot theirs. Their current password stops working, and a pending invitation is cancelled. It isn't offered on your own row: change your own on the **Account security** screen, in your menu.

In code, both are actions, for superusers only and never offered to agents. The password is in the result, and the activity log never holds it:

```php
use LynxCms\Admin\Auth\CreateUser;
use LynxCms\Admin\Auth\SetTemporaryPassword;

$created = lynx()->run(CreateUser::class, ['name' => 'Bruno Costa', 'email' => 'bruno@example.com', 'roles' => ['editor']])->value;
// ['user' => '12', 'password' => '...', 'expires_at' => '2026-11-22T10:00:00+00:00']

$given = lynx()->run(SetTemporaryPassword::class, ['user' => '12'])->value;
```

### A site that sends no email

Set `LYNX_ADMIN_AUTH_MAIL=false`. The Users screen then creates users with temporary passwords only and offers no invitations, and Lynx's sign-in screens have no **Forgot password** link. A superuser creates a new temporary password instead. When the app's own Fortify signs people in, its password reset is the app's to turn off.

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
