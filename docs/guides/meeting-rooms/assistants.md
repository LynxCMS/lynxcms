---
title: AI Assistants
description: Chapter 7 of the meeting rooms guide. Staff connect Claude or another MCP client with a personal token; read tools find free rooms and read schedules, and the booking actions are already its tools.
---

# AI Assistants

Staff ask their assistant for "a room for six with video tomorrow at 10", and it books one. Lynx's [MCP server](/ai/) turns every registered action into a tool, so the booking actions of chapter 3 are already there, with their rules, permissions and dry runs. This chapter adds what an assistant needs to read, and a safe way for each person to connect theirs.

## Each person's token

An assistant must act as the person who uses it: their bookings are theirs, and a staff member's assistant must not cancel someone else's meeting. Lynx's MCP server over HTTP sits behind Sanctum, and acts as the user a token belongs to, with that user's roles. Turn it on in `.env`:

```bash
LYNX_MCP_WEB=true
```

Add Sanctum's `HasApiTokens` trait to `App\Models\User` and run its migration. Lynx only accepts tokens made for the MCP server, with the `lynx:mcp` ability, so a token the app makes for something else does not open it.

Staff get their token on My bookings, under **Your AI assistant**. Issuing and revoking are actions too, so they are logged, but they are kept off the MCP server: an assistant never mints a key or sees one.

```php
final class IssueAssistantToken extends Action
{
    use HandlesAssistantTokens; // permission rooms.book, exposedAsTool() false

    public function handle(Input $input, ActionContext $context): string
    {
        $user = $this->person($context);
        $replaced = $this->current($user)?->delete() === true;
        $token = $user->createToken(self::TOKEN_NAME, [Value::string(config('lynx.mcp.web.ability'), 'lynx:mcp')]);

        $context->changes->created('assistant_token', $token->accessToken->getKey(), ($replaced ? 'Replace' : 'Issue').' the assistant token of '.$user->name);

        return $token->plainTextToken;
    }
}
```

The page shows the token once, with the command for Claude Code and the JSON other clients take:

```bash
claude mcp add --transport http rooms https://firm.example/mcp/lynx --header "Authorization: Bearer 3|…"
```

One token per person: a new one replaces the old, and **Stop the token** revokes it.

## Read tools

Writes are actions; reads are [read tools](/ai/#your-own-read-tools), classes that extend `AgentReadTool`. The app adds three, registered in the provider:

```php
$this->app->make(ReadToolRegistry::class)->register(FindFreeRoomsTool::class, RoomScheduleTool::class, MyBookingsTool::class);
```

| Tool | Answers |
| --- | --- |
| `find_free_rooms` | Rooms that suit a meeting (a day, a length, seats, equipment, an office, a start time), with the windows it can start in. |
| `room_schedule` | A room's day: opening hours, meetings with their booking ids, and free times. |
| `my_bookings` | The meetings the person organizes, with the people invited, to change or cancel. |

The model reads nothing but a tool's name, description and schema before calling it, so the description says what comes back and which tool to use next:

```php
final class FindFreeRoomsTool extends AgentReadTool
{
    public static function name(): string
    {
        return 'find_free_rooms';
    }

    public static function description(): string
    {
        return 'Find meeting rooms free for a meeting on a day: long enough, with enough seats and the equipment asked for, optionally at a start time. '
            .'Returns each room that suits (path, office, capacity, equipment, today\'s date and time zone there) with the windows the meeting can start in: '
            .'any quarter hour from earliest to latest, keeping the gap between meetings. With a start time, rooms free then come first and the others suggest other times. '
            .'Then book with rooms_book (room path, date, from, and to = from plus the length), after a dry run.';
    }

    public static function permission(): string
    {
        return 'rooms.book';
    }
```

`handle()` reuses the booking page's own search (`App\Rooms\FreeRooms`), so the assistant offers exactly the times the page would. Lynx checks `permission()` as the token's owner before `handle()` runs. Each room in the answer carries today's date in its office, so "tomorrow" resolves in the right time zone.

Read tools answer what the person may see. `room_schedule` shows a private meeting's title and organizer only to the person who organizes it, someone invited, or a manager; others read "Busy", as on the screens.

## A conversation

Asked for "a room for six with video tomorrow at 10", an assistant calls:

1. `find_free_rooms` with `{"date": "2026-10-27", "minutes": 60, "from": "10:00", "seats": 6, "equipment": ["video"]}`, and gets Kingfisher and The Lodge.
2. `rooms_book` with `{"room": "/rooms/kingfisher/", "date": "2026-10-27", "from": "10:00", "to": "11:00", "title": "Otter habitat review", "dry_run": true}`, and shows the change: "Book Kingfisher on Tuesday 27 October 2026 from 10:00 to 11:00".
3. `rooms_book` again without `dry_run`, once the person agrees.

The booking is the person's, under the same rules, mailed to the people invited and in the activity log as made through MCP. A staff member's assistant that tries to cancel someone else's meeting gets the action's refusal: "Only its organizer or a manager may change this booking."

## What you get

- **The firm:** booking by asking, in the assistant staff already use, with nothing new to trust: it can do exactly what the person can.
- **You:** an AI integration made of three small read classes and two token actions. The writes, their rules and their safety were done in chapter 3.
