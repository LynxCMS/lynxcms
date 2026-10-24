# Plan: Castor & Otters, Inc., a law firm site with meeting room screens

## Goal

An office runs a site on its own server that shows its meeting rooms' schedules on screens: a TV in the lobby with every room, and a tablet at each door with that room's day. Staff book rooms in the admin, on a booking page, or by asking an AI assistant, and the screens update within seconds. It is built for real use first, then becomes the second Lynx guide and starter (after the training site), showing Lynx as the base of an application rather than a content site.

Calendar logic comes from [Laravel Zap](https://www.laravel-zap.com/) (`laraveljutsu/zap`, PHP 8.5 and Laravel 12+, so it fits Lynx): rooms are its schedulables, bookings its appointments, opening hours its availability, maintenance its blocked times, and it finds conflicts and free slots.

## The firm (added after phase 3)

To show more of Lynx, the app becomes the website of **Castor & Otters, Inc.**, a law firm specialized in wildlife, based in Vancouver with an office in Montreal. The meeting rooms and their screens stay, behind a page password. The humor is in the firm itself: its lawyers are animals of lakes and their shores.

| Question | Decision |
| --- | --- |
| Sections | Practice areas, our team, cases and news, contact with a consultation request, and the two offices. |
| The team | Animals: Castor the beaver and the Otter siblings as founding partners, with a heron, a pike, a loon, a dragonfly paralegal and others. The jokes live in their bios, titles and case names. |
| Languages | English and Canadian French (`fr`, locale `fr-CA`, under `/fr`), written by hand in the seeder. Translation by the agent is out of scope for now. |
| Tone and look | A credible firm: a lakeside palette and clean type, with the puns in the copy and small illustrations. Fonts are bundled by Vite, so pages make no requests elsewhere. |
| Pictures | SVG illustrations drawn for the site, as Blade components: a lawyer or practice area picks one in an `illustration` field. Media fields never accept SVG (Lynx 0.1.17), so editors may upload a photo instead, which then wins. |
| Rooms | Behind one page password on the Rooms section (`lynxcms/page-password`), which covers every room below it and is remembered for 30 days. |
| Offices | Both offices have rooms and a lobby screen. Each office page has a time zone (Vancouver `America/Vancouver`, Montreal `America/Toronto`); a room belongs to an office, and its bookings, rules and screens use that zone. |
| Lynx shown off | Menus, forms (the consultation request), SEO with structured data and hreflang, the sitemap, the page cache for public pages, and search, in both languages. |
| Order | The site comes before the booking admin; the admin and the screens then take the site's look. |

## Decisions taken

| Question | Decision |
| --- | --- |
| What it is | A real app for an office, then a guide and starter like the training site. |
| Where bookings come from | The admin, a self-service booking page for signed-in staff, and MCP or the agent. All three go through the same Lynx actions. |
| Screens | One lobby screen with every room, and one door screen per room, from the same data. |
| Privacy | Each booking can be marked private; screens then show "Busy" instead of its title and organizer. |
| How screens sign in | A secret, revocable kiosk link per screen, made in the admin. No accounts on screens. |
| Live updates | Polling (Livewire `wire:poll`, every 15 to 30 seconds). Nothing extra to run on the server; a screen recovers by itself after a network drop. Websockets are not planned. |
| Editors manage in Lynx | Rooms as pages (name, photo, capacity, floor, equipment, opening hours), lobby announcements with start and end dates, and branding (logo, colors). |
| Languages | English only. |
| Who books | Every signed-in staff account books any room. Staff with a role edit or cancel anyone's booking. |
| Recurring meetings | Zap's patterns (weekly, biweekly, first Monday of the month…), plus cancelling or moving one occurrence. |
| Door screen actions | Check in, and release the room when nobody checks in within 10 minutes; end a meeting early. No booking from the door. |
| Besides the screens | A confirmation email with an `.ics` invite (and updates and cancellations), a calendar feed per room and per person, and reminders before a meeting. |
| Booking rules | Room opening hours, a shortest and longest booking, a buffer between meetings, and blocked times. |
| Accounts | Lynx invitations (its own sign-in, two-factor and passkeys). No registration. |
| Hosting | The office's own server, on the intranet. |
| Size | Small: up to about 10 rooms, a few screens, under 100 staff. SQLite is enough. |

## The site's content

| Template | Fields (main) | Under |
| --- | --- | --- |
| `home` | hero, highlights, featured lawyers and practice areas, latest news | the root |
| `practice-areas` / `practice-area` | summary, body, illustration, lawyers (page references) | `/practice-areas/` |
| `team` / `lawyer` | role (founding partner, partner, associate, paralegal), species, illustration or photo, bio, practice areas, office, fun fact | `/team/` |
| `news` / `post` | kind (case or news), published on, summary, body, practice areas | `/news/` |
| `offices` / `office` | city, address, phone, opening hours, time zone | `/offices/` |
| `contact` | intro, the consultation form | `/contact` |
| `page` | body | legal pages |

Rooms move under the protected Rooms section as before, each with an `office` reference. Screens get an office too, and a lobby shows its office's rooms.

## The data

- **Rooms are Lynx pages** of a `room` template under `/rooms/`: `title`, `photo` (images), `capacity` (integer), `floor` (text), `equipment` (choice, several: screen, video call, whiteboard), `opening_hours` (a repeater of weekday, from, to), `check_in` (toggle: needs check in, on by default), `color` (for the screens).
- **Zap needs an Eloquent model** with `HasSchedules`, and Lynx pages are not Eloquent models. So the app keeps a small `rooms` table (`id`, `page_id` unique) with an `App\Models\Room` that uses `HasSchedules` and reads its content from its page (`$room->page()`). A listener on `PageSaved` and `PageDeleted` for the `room` template keeps the table in step, so editors only ever touch the page.
- **Opening hours become Zap availability**: saving a room page rewrites its availability schedule from the repeater, so bookable slots follow the page.
- **Bookings are Zap appointments** on a room, with metadata: `organizer_id`, `private` (bool), `checked_in_at`, `series` (for recurrences). Titles come from `named()`.
- **Blocked times are Zap blocked schedules**, made by admins (maintenance, an event).
- **Screens are Lynx pages** of a `screen` template under a hidden `/screens/` branch: `kind` (lobby or door), `room` (page reference, for doors), `rooms` (page references, for a lobby; empty is all), `announcements` (on or off), plus a kiosk token kept in the app's `screens` table, never in a field, so it never reaches a content package or an export.
- **Announcements are Lynx pages** of an `announcement` template: `title`, `body`, `image`, `starts`, `ends`.
- **Branding** is a settings page: `logo`, `accent_color`, `background`.

## Bookings as actions

Every write is a Lynx action, so the admin, the booking page, MCP and the agent share validation, permissions, dry runs and the activity log:

| Action | Does |
| --- | --- |
| `rooms.book` | Books a room for a period, once or recurring, with title, privacy and attendees. Checks the rules, then Zap's conflicts. |
| `rooms.change` | Moves or renames a booking, or one occurrence of a series. |
| `rooms.cancel` | Cancels a booking, a series, or one occurrence. Destructive for series. |
| `rooms.block` | Blocks a room for a period (admins). |
| `rooms.check_in` | Checks a booking in (from the door screen's kiosk link). |
| `rooms.end_early` | Ends a booking now, freeing the rest (door screen). |

Permissions: `rooms.book` for every staff account (a `staff` role, given by invitation), `rooms.manage` to change or cancel anyone's booking and to block rooms. A booking's organizer may always change or cancel their own. Door screen actions run as the screen, which may only check in and end early on its own room.

Read tools for MCP and the agent: `find_free_rooms` (date, duration, capacity, equipment) and `room_schedule` (a room's day), so "book a room for six people with video tomorrow at 10" works.

## Rules

Checked in `rooms.book` and `rooms.change`, before Zap's own conflict check:

- inside the room's opening hours: Zap's free slots follow availability, but creating an appointment does not check it (the spike booked 07:00 in a room that opens at 09:00), so `rooms.book` checks `isBookableAtTime` itself;
- at least `rooms.min_minutes` (15) and at most `rooms.max_minutes` (240);
- `rooms.buffer_minutes` (5) after each meeting, as Zap's `conflict_detection.buffer_minutes` (the spike refused 12:02 after a meeting ending at 12:00, and accepted 12:05) and as the buffer passed to its free slot methods;
- not in a blocked time, and not in the past.

## Recurrence with one-off changes

Zap repeats a schedule with patterns and has no way to cancel or move one occurrence (checked in the spike, Zap 1.15.7). A series is split instead:

- a series is one recurring Zap appointment, with `series` in its metadata;
- cancelling one date ends the schedule the day before and starts a copy the day after, both with the same `series`;
- moving one date does the same, and adds a one-off appointment at the new time;
- "the whole series" means every schedule with that `series`.

The spike confirmed that Zap's conflict checks and free slots stay right after a split: the freed date can be booked, and the other dates stay taken. No table of exceptions is needed.

## Screens

- **Kiosk links**: `/screens/{token}`. The token is random, stored hashed, shown once in the admin with a QR code, and revocable. The route sets no session cookie and answers `Cache-Control: no-store`, so neither the page cache nor a browser keeps it.
- **Lobby**: every room as a row or card, "now" and "next" with a progress bar, or a timeline of the day; announcements rotate in a band. Private bookings show "Busy".
- **Door**: the room's name and photo, a large free or busy state with the time until it changes, the day's list, and the Check in and End early buttons when the room needs them.
- **Updates**: a Livewire component with `wire:poll.20s`; the clock and progress move every second in Alpine, between polls. If a poll fails, the screen keeps the last state and shows a small "reconnecting" mark.
- **Built for TVs**: a dark, high contrast theme from the branding, large type, no hover, a full page reload every night to pick up new code, and prevention of screen sleep where the browser allows it (Wake Lock API).

## Self-service booking page

For signed-in staff, at `/book`: pick a date, a length and a capacity or equipment, see the free rooms and slots (Zap's `getBookableSlots`), book in one step. "My bookings" lists, changes and cancels the person's own bookings, with "this occurrence" or "the series".

## Notifications and calendars

- **Confirmation** on booking, change and cancellation, as a queued mail with an `.ics` attachment (`METHOD:REQUEST` or `CANCEL`, a stable `UID` per booking), so Outlook, Google and Apple Calendar update the event instead of adding a new one.
- **Reminders**: a scheduled command sends one 15 minutes before (configurable), skipping cancelled and private ones' details.
- **Calendar feeds**: `/calendar/rooms/{token}.ics` per room and `/calendar/me/{token}.ics` per person, tokens like kiosk links. Private bookings show as busy in room feeds and in full in their organizer's feed.
- On an intranet, mail needs the office's SMTP relay, and feeds work only for calendar apps on the office network. The guide says so.

## No-shows

A scheduled command every minute releases bookings of rooms that need check in, when nobody checked in within `rooms.check_in_minutes` (10) of the start: the rest of the booking is freed and the organizer gets an email. Releases are actions too, so they show in the activity log.

## Hosting on an office server

SQLite, a queue worker and the scheduler's cron entry; `APP_URL` on the intranet name. The guide covers a small Linux box or a VM (the load test VMs are a good rehearsal), backups with `lynx:backup` plus a copy of the SQLite file, and a time server, since screens and bookings depend on the clock. One timezone for the office (`APP_TIMEZONE`), shown on every screen.

## Lynx gaps

Found so far, each fixed in Lynx first with its own PR, as for the training guide:

- None blocking yet. To confirm while building: a page reference field filtered by template for the screen's rooms (exists), a repeater of weekday and times for opening hours (exists), and admin screens of the app's own (`Admin::screen`, exists). The page cache must leave kiosk routes alone (they send `no-store`, which it respects).

## Order of work (one PR at a time, no stacked PRs)

Each step is a phase: it ends with something to try, and the next one starts only once it is approved.

1. **Spike** (done): Zap 1.15.7 checks conflicts and buffers, follows availability for free slots but not for new appointments, understands recurring schedules in conflicts, has no single-occurrence changes (a series is split instead), and stores dates with wall-clock times in `app.timezone`.
2. **Skeleton** (done): a fresh Laravel app with Lynx, the templates (room, screen, announcement, settings), the `rooms` sync and availability from opening hours, seeded with four rooms.
3. **Booking actions and rules** (done), with tests for each action (happy path, validation, rollback, dry run, conflicts, buffers, opening hours).
4. **The firm's foundations**: English and French, the offices with their time zones, rooms belonging to an office (rules and free times in the room's zone), the site's templates, and the page password on Rooms.
5. **Design and content**: the layout and pages in the firm's look, the SVG illustrations, and the content in both languages: practice areas, the team, cases and news, the offices.
6. **Lynx features**: menus, the consultation form, SEO with structured data and hreflang, the sitemap, search, and the page cache.
7. **Admin**: a bookings screen per room and a week view, blocking, screens with kiosk links and QR codes.
8. **Screens**: lobby and door, polling, offline mark, check in, end early, no-show release.
9. **Booking page** and "My bookings".
10. **Recurrence with one-off changes.**
11. **Mail, .ics, reminders and feeds.**
12. **MCP and agent**: the read tools, and the booking actions as tools.
13. **Hosting notes**, then the guide chapters and the starter repo, built and tested on tags like `starter-training`.

## Verification

- Every PR: the app's tests (Pest), Pint and PHPStan, and for Lynx changes the monorepo's full checks.
- Screens: a real TV browser (or Chromium in kiosk mode) on the office network for a day: updates within the poll interval, recovery after unplugging the network, no sleep, readability across the room.
- Calendars: invites and cancellations in Outlook, Google Calendar and Apple Calendar; feeds subscribed in each.

## Open questions

- Hosting: a public website must be reachable from the internet, while the plan put the app on the office's server. Either the site is hosted publicly and the screens reach it through their kiosk links, or the public site and the rooms run apart. To decide before the hosting notes.

- Should a door screen also allow "Book now" later? Left out for now.
- Attendees: invite other staff (they get the `.ics` too), or organizer only?
- Do screens need to show the next day after hours, or a "closed" state?
