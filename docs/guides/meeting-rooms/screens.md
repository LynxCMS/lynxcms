---
title: Screens
description: Chapter 5 of the meeting rooms guide. Lobby and door screens on secret kiosk links, without sessions, polling a JSON state, with check in, end early and no-show release as actions.
---

# Screens

Each office has a TV in its lobby with every room, and a tablet at each door with that room's day. Nobody signs in on them: they open a kiosk link from the admin (chapter 4) and stay on it for months. A screen is a page (its kind, office, rooms and language are fields), and what it shows is computed from the bookings.

## Routes without a session

The kiosk routes are registered in the provider, outside the `web` middleware group, so they start no session, set no cookie and need no CSRF token. The secret in the address is the credential:

```php
Route::prefix('screens/{token}')->where(['token' => '[A-Za-z0-9]+'])->middleware('throttle:120,1')->group(function (): void {
    Route::get('/', [KioskController::class, 'show'])->name('kiosk');
    Route::get('state', [KioskController::class, 'state'])->name('kiosk.state');
    Route::post('check-in', [KioskController::class, 'checkIn'])->name('kiosk.check-in');
    Route::post('end-early', [KioskController::class, 'endEarly'])->name('kiosk.end-early');
});
```

The controller finds the screen by the token's hash and answers `no-store`, so neither the [page cache](/modules/page-cache) nor a browser keeps a copy:

```php
private const array NO_STORE = ['Cache-Control' => 'no-store, private', 'X-Robots-Tag' => 'noindex, nofollow'];

public function state(string $token): JsonResponse|Response
{
    $screen = $this->screen($token);

    return $screen instanceof Page ? response()->json(ScreenState::of($screen), 200, self::NO_STORE) : $this->invalid();
}
```

The hash lookup is a trait the app's secret links share (kiosk links here, calendar feeds in chapter 6): the token is random, shown once, and only its SHA-256 is stored.

```php
public static function findByToken(string $token): ?static
{
    $link = strlen($token) === static::TOKEN_LENGTH ? static::query()->where('token_hash', self::hash($token))->first() : null;

    return $link instanceof static ? $link : null;
}
```

## What a screen shows

`App\Screens\ScreenState` builds the JSON a screen polls: the office's local date and minute, each room's hours and meetings today, and the announcements running now. Private meetings read "Busy", with no organizer: a TV in a lobby is the most public place in the office. It reads past the Rooms password and in the screen's own language (Montreal's screens speak French):

```php
public static function of(Page $screen): array
{
    return app(PageVisibility::class)->withoutRules(
        fn (): array => lynx()->language(self::language($screen), fn (): array => self::build($screen)),
    );
}
```

`lynx()->language()` runs a closure in another language, so room names and announcements come out in French for that screen, while the rest of the request is untouched.

## The page: polling with Alpine

Screens run no Livewire, since Livewire needs a session. The page is rendered once by the server with the first state, and `resources/js/kiosk.js`, an Alpine component, polls `state` every `rooms.screen_poll_seconds` (20). Between polls the screen keeps its own clock from the server's minute, so "free until 14:00" and the progress bars move every second without asking again:

```js
init() {
    setInterval(() => this.tick++, 1000);
    setInterval(() => this.refresh(), config.poll * 1000);
    this.keepAwake();
    this.reloadNightly();
},

/** Seconds since midnight in the office, from the server's last answer plus the time since. */
now() {
    this.tick;
    const elapsed = (performance.now() - this.receivedAt) / 1000;

    return this.state.minute * 60 + this.state.second + elapsed;
},
```

A failed poll keeps the last state on screen and shows "Reconnecting…"; the next poll tries again. The screen asks the browser to stay awake (the Wake Lock API) and reloads itself at `rooms.screen_reload_hour` (3:00) to pick up new code. Polling needs nothing extra on the server, and a screen recovers by itself after the network drops, which matters more on an office TV than updates within the second.

## Check in and end early

A door screen has two buttons: **Check in**, from a few minutes before a meeting, and **End meeting now**. They post to the kiosk link, which runs the actions as the system, with the screen's id:

```php
lynx()->actingAs(Actor::system(), fn () => lynx()->run($action, ['booking' => $request->integer('booking'), 'screen' => (int) $screen->id]));
```

The system may act on any booking, so the action makes sure a screen acts on its own room only:

```php
if ($input->get('screen') !== null && $this->doorRoomId($input->get('screen')) !== $room->page_id) {
    throw ValidationException::withMessages(['screen' => 'This screen is not at that room\'s door.']);
}

if (! in_array($context->actor->kind, [Actor::SYSTEM, Actor::CLI], true)) {
    $this->authorizeChange($context, $booking);
}
```

The same `rooms.check_in` and `rooms.end_early` work for a signed-in organizer, or an assistant, with the usual checks. For a repeating meeting, the action first splits today's date out of the series, so ending today's meeting early leaves next week's alone.

## No-shows

In rooms whose page says meetings need a check in, a meeting nobody checked into within `rooms.check_in_minutes` (10) of its start is released: the rest of it is freed, and its organizer is mailed. The release is an action too, run every minute by the scheduler:

```php
Artisan::command('rooms:release-no-shows', function () {
    $released = lynx()->run(ReleaseNoShows::class)->value;
    $this->info(count(is_array($released) ? $released : []).' meeting(s) released.');
});

Schedule::command('rooms:release-no-shows')->everyMinute()->withoutOverlapping();
```

So each release is in the activity log, with the meeting it freed.

## What you get

- **The firm:** a lobby that says which rooms are free, doors that say until when, and rooms booked "just in case" released for others.
- **You:** screens with no accounts, no websockets and no state on the server, secured by a revocable link, and every button press running the same actions as the rest of the app.
