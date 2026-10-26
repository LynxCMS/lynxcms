---
title: The Admin
description: Chapter 4 of the meeting rooms guide. A Bookings screen with the week of every room, a Bookings tab on room pages, and a Kiosk link tab on screens, in Lynx's admin.
---

# The Admin

Office managers already edit rooms and screens in Lynx's admin. Their bookings belong there too, next to the pages, not in a second back office. The admin is a Livewire app with extension points for exactly this: screens of your own in its sidebar, and tabs of your own in the page editor. See [Extending the admin](/admin/extending) for all of them.

## A Bookings screen

`Admin::routes()` adds the admin's prefix, middleware and access gate; `Admin::screen()` renders a view for users with a permission and answers 403 to others. `Navigation` adds the sidebar link:

```php
Admin::routes(function (): void {
    Admin::screen('bookings', 'admin.bookings', 'rooms.manage')->name('bookings');
});

$canManage = fn (): bool => app(AdminAccess::class)->allows('rooms.manage');
app(Navigation::class)->add('Bookings', 'lynx.admin.bookings', order: 15, visible: $canManage, icon: 'calendar-days');
```

The view wraps a Livewire component in the admin's layout:

```blade
<x-lynx-admin::layout title="Bookings" :crumbs="['Pages' => route('lynx.admin.pages')]">
    <x-lynx-admin::ui.page-header title="Bookings" description="Each office's rooms, week by week. Book, block, change or cancel from here." />
    <livewire:admin.rooms-week />
</x-lynx-admin::layout>
```

`App\Livewire\Admin\RoomsWeek` shows an office's rooms against the working week, in the office's time zone. A click on a day opens a form to book or block; a click on a booking opens it to change or cancel. Saving runs the actions from chapter 3, and a refusal lands on the form:

```php
$this->attempt(fn () => match (true) {
    $form['booking'] !== null => lynx()->run(ChangeBooking::class, $this->changeInput($form, $period)),
    $form['mode'] === 'block' => lynx()->run(BlockRoom::class, ['room' => $form['room'], ...$period, 'reason' => $form['title']]),
    default => lynx()->run(BookRoom::class, ['room' => $form['room'], ...$period, 'title' => $form['title'], 'private' => $form['private']]),
}, 'Saved.');
```

The form has no rules of its own. "Kingfisher is taken from 10:00 to 11:00" comes from the action, as it does on the staff's page and for assistants.

Livewire answers clicks at its own address, outside the admin's routes and their middleware, so each component checks access itself in a boot hook, which runs on every request:

```php
public function bootEditsBookings(): void
{
    Gate::authorize(AdminServiceProvider::GATE);
    abort_unless(app(AdminAccess::class)->allows('rooms.manage'), 403);
}
```

## Tabs on room and screen pages

A room's page editor gets a **Bookings** tab with that room's week, and a **Calendar feed** tab (chapter 6). A screen's gets a **Kiosk link** tab. Each tab is a Livewire component, shown when its closure says so:

```php
Livewire::component('admin.room-bookings', RoomBookingsTab::class);
Livewire::component('admin.kiosk-link', KioskLinkTab::class);

app(EditorTabs::class)->add('bookings', 'Bookings', 'admin.room-bookings', fn (Page $page): bool => (string) $page->template()->name === 'room' && $canManage());
app(EditorTabs::class)->add('kiosk', 'Kiosk link', 'admin.kiosk-link', fn (Page $page): bool => (string) $page->template()->name === 'screen' && $canManage());
```

The tab receives the page's id as `$pageId`. Editors write the room's description in one tab and see its meetings in the next.

## Kiosk links

A screen needs a way to read bookings without anyone signing in on the TV. The **Kiosk link** tab issues a secret address for it, through an action, so issuing and revoking are logged like everything else:

```php
public function handle(Input $input, ActionContext $context): string
{
    $screen = $this->screen($input->get('screen'));
    [$token, $hash] = KioskLink::newToken();
    $replaced = KioskLink::forPage($screen)?->delete() === true;

    $link = KioskLink::query()->create(['page_id' => (int) $screen->id, 'token_hash' => $hash]);
    $context->changes->created('kiosk_link', $link->id, ($replaced ? 'Replace' : 'Issue').' the kiosk link of '.$screen->title);

    return route('kiosk', ['token' => $token]);
}
```

Only the token's hash is stored, in the app's own `kiosk_links` table and never in a field, so it never reaches a content package or an export. The tab shows the address once, with a QR code to scan from the screen's own browser, and **Revoke** cuts the screen off.

## The admin's look for app views

The admin ships its CSS already built, so Tailwind classes it never used are missing from it. The app's admin views get their own small stylesheet, built by Vite from the admin's colour tokens, and added to the admin's pages:

```php
config(['lynx-admin.stylesheets' => [...Value::strings(config('lynx-admin.stylesheets', [])), Vite::asset('resources/css/admin.css')]]);
```

## What you get

- **The firm:** one admin for the rooms' content and their bookings, with a week view per office, and screens set up from their own page.
- **You:** three admin features in a provider and a few components, with the admin's sign-in, two-factor, roles and look already done.
