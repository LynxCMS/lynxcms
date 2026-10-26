---
title: Staff Pages, Mail and Calendars
description: Chapter 6 of the meeting rooms guide. The staff's booking page and My bookings as Lynx pages behind a gate, refusals as the page's texts, invitations with .ics mailed after the action commits, reminders, and calendar feeds.
---

# Staff Pages, Mail and Calendars

Staff should not need the admin to book a room. They get two pages of their own, `/book` and `/my-bookings`, in English and French, and the meetings reach their calendar without them doing anything.

## Pages behind a gate

The booking pages are Lynx pages: `/book` (`/fr/reserver`) with a `book` template, and `/my-bookings` (`/fr/mes-reservations`) with a `my-bookings` template. Editors write their titles and summaries in both languages, Lynx gives them their French addresses, and their status is `hidden`, so they never appear in menus, search or the sitemap. Each template's view places a Livewire component and passes it the page:

```blade
<x-layout :title="(string) $page->title" robots="noindex, nofollow">
    <x-page-header :title="(string) $page->title" :summary="(string) $page->summary" />
    <livewire:book-a-room :page-id="(int) $page->id" />
</x-layout>
```

Only staff may open them. A [gate](/deeper/visibility) decides what happens when someone opens a page: it sends people who are not signed in to sign in and back, and refuses people who may not book:

```php
app(PageGates::class)->add(fn (Page $page, Request $request): ?Response => $this->staffOnly($page, $request));

private function staffOnly(Page $page, Request $request): ?Response
{
    if (! in_array((string) $page->template()->name, ['book', 'my-bookings'], true)) {
        return null;
    }

    $id = $request->user()?->getAuthIdentifier();

    if (! is_int($id) && ! is_string($id)) {
        return redirect()->guest(route('login'));
    }

    abort_unless(app(Authorizer::class)->allows(Actor::user($id), 'rooms.book'), 403);

    return null;
}
```

Signed-in users never get pages from the page cache, and guests only ever get the redirect, so nothing personal is stored.

## Livewire components that run actions

Livewire answers at its own address, without the `/fr` prefix, so a shared trait keeps the visitor's language between requests, checks the permission on every request, and runs actions. **Book a room** asks for an office, a day, a length, seats and equipment, and lists each room that fits with its free start times every quarter hour, keeping the gap between meetings (`App\Rooms\FreeRooms` and `App\Rooms\Slots`). Picking a time and naming the meeting runs `rooms.book`. **My bookings** lists the meetings the person organizes, to move, rename or cancel with `rooms.change` and `rooms.cancel`.

## Refusals as the page's texts

When the office's rules refuse a booking, the person should read why in their language, in words the firm chose. The actions serve the admin and AI assistants too, which read English messages, so they don't translate. They say why instead: `BookingRules` throws a `BookingRefused`, a `ValidationException` that also carries a reason and the values it names:

```php
throw BookingRefused::because('from', 'taken', ['room' => $name, 'times' => '11:15–12:15', 'buffer' => 5], "{$name} is taken from 11:15 to 12:15.");
```

The component keeps the reason and values, and its view shows them as one of the booking page's [editable texts](/basics/views#editable-template-wide-or-site-wide-texts), with the values filled into placeholders:

```blade
@case('taken')
    @string('refusal.taken', ['en' => ':room is taken during :times.', 'fr' => 'La salle :room est prise pendant :times.'],
        label: 'Refused: room taken (times)', replace: ['room' => $values['room'], 'times' => $values['times']])
    @break
```

Lynx finds these texts through the component's view ([Texts in Livewire components](/basics/views#texts-in-livewire-components)), so the booking page's **Texts** tab lists all ten refusals, in English and French, with the placeholders each must keep. My bookings shows the same texts, read from the booking page, so the firm words each refusal once.

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
- **You:** two pages, a gate and a mail listener, with every rule still in the actions, refusals editors can word, and no mail that could go out for a change that never happened.
