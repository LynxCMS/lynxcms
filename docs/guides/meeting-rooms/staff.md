---
title: Staff Pages, Mail and Calendars
description: Chapter 6 of the meeting rooms guide. The staff's booking page and My bookings as app routes in two languages, invitations with .ics mailed after the action commits, reminders, and calendar feeds.
---

# Staff Pages, Mail and Calendars

Staff should not need the admin to book a room. They get two pages of their own, `/book` and `/my-bookings`, in English and French, and the meetings reach their calendar without them doing anything.

## App routes, not pages

The booking pages are the app's own routes, not pages in the tree: they have no content for editors, and they must never appear in menus, search or the sitemap. Lynx's page routes answer every other address, so these come first in `routes/web.php`, once per language:

```php
Route::middleware('auth')->group(function (): void {
    Route::get('book', [StaffPagesController::class, 'book'])->defaults('language', 'en')->name('staff.book');
    Route::get('fr/reserver', [StaffPagesController::class, 'book'])->defaults('language', 'fr')->name('staff.book.fr');
    Route::get('my-bookings', [StaffPagesController::class, 'mine'])->defaults('language', 'en')->name('staff.mine');
    Route::get('fr/mes-reservations', [StaffPagesController::class, 'mine'])->defaults('language', 'fr')->name('staff.mine.fr');
});
```

The `auth` middleware sends people who are not signed in to Lynx's sign-in page and back. The controller lets in people with `rooms.book` and sets the page's language, so the layout, the site texts and the dates all follow:

```php
private function enter(string $language): void
{
    $id = auth()->id();
    abort_unless($id !== null && app(Authorizer::class)->allows(Actor::user($id), 'rooms.book'), 403);
    app(LanguageContext::class)->set($language);
}
```

## Livewire components that run actions

The pages are Livewire components. Livewire answers at its own address, without the `/fr` prefix, so a shared trait keeps the visitor's language between requests, checks the permission on every request, and turns an action's refusal into errors on the form:

```php
private function attemptAction(string $action, array $input): bool
{
    $this->resetErrorBag();

    try {
        lynx()->run($action, $input);
    } catch (ValidationException $exception) {
        foreach ($exception->errors() as $field => $messages) {
            $this->addError($field, $messages[0] ?? 'Not valid.');
        }

        return false;
    }

    return true;
}
```

**Book a room** asks for an office, a day, a length, seats and equipment, and lists each room that fits with its free start times every quarter hour, keeping the gap between meetings (`App\Rooms\FreeRooms` and `App\Rooms\Slots`). Picking a time and naming the meeting runs `rooms.book`. **My bookings** lists the meetings the person organizes, to move, rename or cancel with `rooms.change` and `rooms.cancel`.

## Mail after the commit

Booking, changing and cancelling a meeting mails each person concerned an `.ics` invite their calendar app reads. The mail must not go out for a dry run, nor for a booking a later hook rolls back. So the actions don't send it: they record an event with `$context->event()`, which Lynx dispatches once the action's transaction commits, and never for a dry run:

```php
$context->event(new MeetingChanged(
    kind: $after === null ? MeetingChanged::CANCELLED : $kind,
    summary: Summary::of($meeting, $date),
    language: $meeting->booking->language,
    organizerId: $meeting->booking->organizerId,
    invited: $invited,
    request: $after === null ? null : Ics::invite($after, Ics::REQUEST),
    dropped: $dropped,
    cancel: $before === null || $dropped === [] ? null : Ics::invite($before, Ics::CANCEL, $dropped),
));
```

A listener queues one mail per person, in the language the meeting was booked in:

```php
foreach (User::query()->whereIn('id', $event->invited)->get() as $person) {
    Mail::to($person)->locale($event->language)
        ->queue(new MeetingMail($event->kind, $event->summary, $this->isOrganizer($person, $event), $event->request, Ics::REQUEST));
}
```

The `.ics` (`App\Calendar\Ics`, built with spatie/icalendar-generator) gives each meeting one stable `UID` and a growing `SEQUENCE`, so Outlook, Google Calendar and Apple Calendar update the event in place when it moves. A series is one event with its `RRULE`, the dates taken out as `EXDATE` and the dates changed alone as `RECURRENCE-ID`.

## Reminders

`rooms:send-reminders` runs every minute and mails the organizer and the people invited `rooms.reminder_minutes` (15) before each meeting, once per date. A private meeting's reminder leaves its title out of the subject, since phones show subjects on locked screens.

## Calendar feeds

Each person can subscribe to their meetings, and managers to a room's, at a secret address: `/calendar/me/{token}.ics` and `/calendar/rooms/{token}.ics`. Like kiosk links, the tokens are random, shown once, stored as hashes, issued and revoked by actions (`rooms.issue_feed`, `rooms.revoke_feed`), and the routes run without a session, since calendar apps fetch them from anywhere. A room's feed shows private meetings as "Busy"; a person's feed shows theirs in full.

## What you get

- **The firm:** staff who book for themselves in their language, and meetings that land in everyone's calendar, updated when they move.
- **You:** two pages and a mail listener, with every rule still in the actions, and no mail that could go out for a change that never happened.
