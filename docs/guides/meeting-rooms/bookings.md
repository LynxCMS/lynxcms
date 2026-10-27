---
title: Bookings Are Actions
description: Chapter 3 of the meeting rooms guide. Booking, changing, cancelling and blocking rooms as Lynx actions, with the office's rules, Zap's conflict check, dry runs and the activity log.
---

# Bookings Are Actions

A booking can come from the admin, the staff's booking page, a door screen or an AI assistant. If each wrote to Zap itself, each would need the rules, the permission check and a log, and they would drift apart. In Lynx every write is an [action](/deeper/actions-and-hooks): one class that validates its input, checks a permission, does the work in a transaction and records what changed. The app's booking actions are ordinary Lynx actions, so every caller gets the same rules for free.

| Action | Who | Does |
| --- | --- | --- |
| `rooms.book` | staff | Books a room for a period on a day, once or repeating, private or not, with people invited. |
| `rooms.change` | the organizer, or a manager | Moves or renames a booking, one date of a series or all of it. |
| `rooms.cancel` | the organizer, or a manager | Cancels a booking, a date of a series or the series. |
| `rooms.block` | managers | Blocks a room for maintenance or an event. |
| `rooms.check_in`, `rooms.end_early` | door screens | Mark a meeting as started, or free the rest of it (chapter 5). |

## Anatomy of `rooms.book`

An action has a name, a description, a permission, validation rules and a JSON schema. The description and schema are what an AI assistant reads in chapter 7, so they say what the action does in plain words:

```php
final class BookRoom extends Action
{
    use HandlesBookings;

    public static function name(): string
    {
        return 'rooms.book';
    }

    public static function description(): string
    {
        return 'Book a meeting room for a period on one day, with a title, once or repeating, and invite people by email. Private bookings show as "Busy" to others.';
    }

    public static function permission(): string
    {
        return 'rooms.book';
    }

    public static function rules(): array
    {
        return [
            'room' => ['required'], 'date' => ['required', 'date_format:Y-m-d'],
            'from' => ['required', 'date_format:H:i'], 'to' => ['required', 'date_format:H:i'],
            'title' => ['required', 'string', 'max:120'], 'private' => ['sometimes', 'boolean'],
            // repeat, until and attendees too
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'room' => $schema->string()->description('Path or id of the room page, e.g. "/rooms/atlas/".')->required(),
            'date' => $schema->string()->description('Day, as YYYY-MM-DD.')->required(),
            'from' => $schema->string()->description('Start, as HH:MM.')->required(),
            'to' => $schema->string()->description('End, as HH:MM.')->required(),
            'title' => $schema->string()->description('What the meeting is.')->required(),
            'private' => $schema->boolean()->description('Show only "Busy" to others.'),
        ];
    }
```

`handle()` checks the office's rules, asks Zap to save the appointment, and records the change:

```php
    public function handle(Input $input, ActionContext $context): Booking
    {
        $room = $this->room($input->get('room'));
        [$date, $from, $to] = [(string) $input->string('date'), (string) $input->string('from'), (string) $input->string('to')];
        BookingRules::check($room, $date, $from, $to);

        try {
            $schedule = Zap::for($room)->named(trim((string) $input->string('title')))->appointment()
                ->from($date)->addPeriod($from, $to)
                ->withMetadata(['organizer_id' => $context->actor->id, 'private' => (bool) $input->get('private', false)])
                ->save();
        } catch (ScheduleConflictException $exception) {
            throw BookingRules::conflict($room, $exception);
        }

        $booking = Booking::fromSchedule($schedule->load('periods'));
        $context->changes->created('booking', $booking->id, 'Book '.$booking->room()?->title.' on '.$booking->when().': '.$booking->publicTitle());

        return $booking;
    }
}
```

The organizer is `$context->actor`, whoever runs the action: a signed-in person on the booking page, a manager in the admin, or the person an assistant's token belongs to. The starter's version also handles repeating meetings and invitations; the shape stays the same.

Register the actions in the provider:

```php
$this->app->make(ActionRegistry::class)->register(BookRoom::class, ChangeBooking::class, CancelBooking::class, BlockRoom::class);
```

## The office's rules

`App\Bookings\BookingRules` holds what Zap does not know: a meeting lasts between `rooms.min_minutes` (15) and `rooms.max_minutes` (240), is not in the past in the room's time zone, and fits inside the room's opening hours that day. Zap's free slots follow availability, but it saves an appointment outside it if asked, so the rule is the app's.

Zap then refuses overlaps, with `rooms.buffer_minutes` (5) kept between meetings, which the provider hands to Zap's config so one setting serves both. Its refusal names the schedules in the way, which may be someone's private meeting, so the app turns it into a message about times only:

```php
public static function conflict(Room $room, ScheduleConflictException $exception): ValidationException
{
    $times = [];

    foreach ($exception->getConflictingSchedules() as $schedule) {
        $booking = Booking::fromSchedule($schedule->loadMissing('periods'));
        $times[] = "from {$booking->from} to {$booking->to}";
    }

    return ValidationException::withMessages(['from' => self::name($room).' is taken '.implode(' and ', array_unique($times)).'.']);
}
```

A `ValidationException` is the form's error on the booking page, a red line in the admin's dialog, and a structured refusal for an AI assistant.

## Who may change what

`rooms.change` and `rooms.cancel` need `rooms.book`, then check the booking itself: its organizer may change it, a manager may change anyone's, and blocked times are managers' only.

```php
private function authorizeChange(ActionContext $context, Booking $booking): void
{
    $isOwn = ! $booking->isBlocked && $booking->organizerId !== null && (string) $booking->organizerId === (string) $context->actor->id;

    if (! $isOwn && ! $this->isManager($context->actor)) {
        throw new AuthorizationException('Only its organizer or a manager may change this booking.');
    }
}
```

`rooms.cancel` says it is `destructive()`, so AI assistants are told to dry-run it and ask the person before running it.

## Run it from anywhere

Every caller runs the action the same way:

```php
lynx()->run(BookRoom::class, ['room' => '/rooms/lodge/', 'date' => '2026-11-02', 'from' => '10:00', 'to' => '11:00', 'title' => 'Otter habitat review']);
```

And gets, without writing them:

- **A dry run.** `lynx()->run(..., dryRun: true)` validates, runs and rolls back, returning the changes: "Book The Lodge on Monday 2 November 2026 from 10:00 to 11:00: Otter habitat review". Assistants show it before they book.
- **The activity log.** Every booking, change and cancellation, with who did it: a person, the system for a screen, or a person's MCP client.
- **Hooks.** Another package can run code before or after `rooms.book`, or refuse it, with `lynx()->hook(BookRoom::class)`.
- **A transaction.** A listener or hook that throws undoes the booking.

## Tests

Each action has the four tests Lynx asks of every action: the happy path, a validation failure, a rollback and a dry run, plus the rules. The starter's `tests/Feature/Bookings` runs them against an in-memory database:

```php
it('keeps 5 minutes between meetings', function (): void {
    book($this->ana);

    expect(fn (): Booking => book($this->ana, ['from' => '11:02', 'to' => '12:00']))->toThrow(ValidationException::class, '5 minutes between meetings')
        ->and(book($this->ana, ['from' => '11:05', 'to' => '12:00'])->from)->toBe('11:05');
});

it('writes nothing on a dry run, and nothing when the action rolls back', function (): void {
    $result = runAs($this->ana, BookRoom::class, ['room' => '/rooms/atlas/', 'date' => '2026-11-02', 'from' => '10:00', 'to' => '11:00', 'title' => 'Planning'], dryRun: true);
    expect(Schedule::query()->where('schedule_type', 'appointment')->count())->toBe(0);

    lynx()->hook(BookRoom::class)->after(fn () => throw new ActionHalted('No.'));
    expect(fn (): Booking => book($this->ana))->toThrow(ActionHalted::class)
        ->and(Schedule::query()->where('schedule_type', 'appointment')->count())->toBe(0);
});
```

## Repeating meetings

Zap repeats a schedule weekly, every two weeks or on "the second Monday of the month", but cannot change one date of a series. `App\Bookings\Series` does it by splitting: the series ends the day before, a copy carries on the day after, and a changed date becomes a meeting of its own, all sharing a series id in their metadata. `rooms.change` and `rooms.cancel` take an `occurrence` date and a `scope` of `one` or `series`. The starter's `ChangeBooking` and `CancelBooking` show the details.

## What you get

- **The firm:** the same rules whoever books, refusals that name times and never other people's meetings, and a log of every change.
- **You:** the rules in one place, tested once, and three more callers (the admin, screens, assistants) in the next chapters without writing them again.
