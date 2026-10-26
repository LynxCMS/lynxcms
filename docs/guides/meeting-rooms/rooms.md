---
title: Rooms Are Pages
description: Chapter 2 of the meeting rooms guide. Room, screen and announcement templates, and a Zap model the app keeps in step with each room page.
---

# Rooms Are Pages

A room has a name in two languages, a photo, seats, a floor, equipment and opening hours. That is content: office managers change it, want a preview and a revision history, and expect it in the admin with the rest of the site. So a room is a Lynx page. What Zap needs from it, a schedulable model with availability, is derived from the page and never edited by hand.

## The fields

The seeder (`database/seeders/RoomsSchemaSeeder.php`) adds the fields once, then the templates that use them. Opening hours are a repeater of a weekday and two times, so a room can open late on Fridays or close for lunch:

```php
lynx()->fields->add('capacity', 'integer', label: 'Seats');
lynx()->fields->add('equipment', 'choice', label: 'Equipment', settings: ['multiple' => true, 'options' => [
    ['value' => 'screen', 'label' => 'Screen'],
    ['value' => 'video', 'label' => 'Video calls'],
    ['value' => 'whiteboard', 'label' => 'Whiteboard'],
]]);
lynx()->fields->add('opens', 'time', label: 'Opens');
lynx()->fields->add('closes', 'time', label: 'Closes');
lynx()->fields->add('opening_hours', 'repeater', label: 'Opening hours', settings: [
    'fields' => [['name' => 'weekday', 'required' => true], ['name' => 'opens', 'required' => true], ['name' => 'closes', 'required' => true]],
    'max' => 14,
    'item_label' => '{weekday} {opens} to {closes}',
    'add_label' => 'Add a day',
]);
lynx()->fields->add('check_in', 'toggle', label: 'Meetings need a check in');
```

A screen's room is a page reference limited to rooms, shown as a select:

```php
lynx()->fields->add('room', 'page', label: 'Room', settings: ['multiple' => false, 'templates' => ['room'], 'input' => 'select']);
```

Then the templates. Screens have no URL, so they never reach the website:

```php
lynx()->templates->add('room', fields: [
    ['name' => 'title', 'required' => true], ['name' => 'office', 'required' => true], 'photo', ['name' => 'capacity', 'required' => true], 'floor',
    'equipment', 'opening_hours', 'check_in', 'color',
], label: 'Room');

lynx()->templates->add('screen', fields: [
    ['name' => 'title', 'required' => true], ['name' => 'kind', 'required' => true], ['name' => 'office', 'required' => true], 'room', 'rooms', 'show_announcements', 'screen_language',
], label: 'Screen', hasUrl: false);
```

Once the site is live, the schema moves between environments as YAML with `lynx:schema:export` and `lynx:schema:apply`, as [Schema and Content Packages](/deeper/schema-and-content) explains.

## A Zap model for each room page

Zap hangs schedules on an Eloquent model with its `HasSchedules` trait. Lynx pages are domain objects, not Eloquent models, so the app keeps a small `rooms` table, one row per room page, and reads everything else from the page:

```php
final class Room extends Model
{
    use HasSchedules;

    protected $fillable = ['page_id'];

    public static function forPage(Page $page): ?self
    {
        return self::query()->where('page_id', $page->id)->first();
    }

    /** The room's page, read past visibility rules: the Rooms password is for visitors, not for the app. */
    public function page(): ?Page
    {
        return app(PageVisibility::class)->withoutRules(fn (): ?Page => lynx()->pages->get($this->page_id));
    }
}
```

`withoutRules()` matters here. The Rooms section is behind a page password, a [visibility rule](/deeper/visibility) for visitors. The app's own work, such as a screen that runs without a session, must read rooms whatever the visitor may see.

## Keeping it in step

Lynx fires events after a page action commits. One listener passes them on:

```php
Event::listen([PageSaved::class, PageRestored::class, PageTemplateSwitched::class, PageTrashed::class, PageDeleted::class], SyncRoomWithPage::class);
```

`App\Rooms\RoomSync` creates the room's row when a room page is saved, removes it with its schedules when the page goes to the trash, and brings it back on restore. On every save it rewrites the room's availability from the opening hours, one Zap availability schedule per group of weekdays that share hours:

```php
private function rewriteAvailability(Room $room, Page $page): void
{
    $room->availabilitySchedules()->each(fn ($schedule) => $schedule->delete());

    foreach (OpeningHours::of($page) as $group) {
        Zap::for($room)->named('Opening hours')->availability()
            ->from(today()->toDateString())
            ->addPeriod($group['opens'], $group['closes'])
            ->weekly($group['days'])
            ->save();
    }
}
```

Editors never see this. They change the Friday hours, save, and the booking page offers the new times. Because the events fire after the commit, a save that fails validation or is rolled back leaves Zap's tables alone.

## A room's page

The room's template shows its next open days with their meetings and free times. A view composer prepares the data, so the Blade view only prints:

```php
View::composer('templates.room', RoomSchedule::class);
```

`App\Rooms\FreeTimes` computes a day's free times from the opening hours less the bookings and blocked times. The booking page's start times (`App\Rooms\Slots`) and the AI tools build on it.

## What you get

- **The firm:** rooms they add, photograph and reschedule in the admin, in English and French, with previews and revisions.
- **You:** Zap's calendar engine without a second admin for rooms, and one direction of truth: pages own the content, the app derives the schedule.
