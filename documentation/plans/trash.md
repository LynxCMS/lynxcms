# Plan: trash

Status: core and admin built in phase 12 (ADR-0035). The rules were decided on 2026-10-08; design questions 1 to 3 are decided below, question 4 is still open.

## Why

Today `pages.delete` removes a branch for good. Phase 12 adds a trash, as in ProcessWire: deleting moves the branch to the trash, where it can be restored or deleted for good.

A trash done naively can trap pages. If trashing moves a branch without looking at locks, and emptying the trash then refuses because of those locks, a locked page deep in the branch keeps the whole branch in the trash: it cannot be deleted, and if the trash offers no way to unlock, it cannot leave either. The rules below make that state impossible.

## Rules (decided)

1. **Trashing checks what deleting checks.** A branch that holds a locked or system page cannot be trashed. The refusal names the page ("Page [lesson] below [alpha] is locked. Unlock it first."), and nothing moves. The check runs on the stored status of every page in the branch, as `PageGuard::assertDeletable()` does today, and `pages.delete` is needed for every page in it (ADR-0033).
2. **Locked pages can always be unlocked, in the trash too.** Unlocking needs `pages.status` and nothing else: no rule of the trash (a hidden branch, a system parent, a branch limited grant) may hide or refuse Unlock on a trashed page. The trash screen offers Unlock wherever it offers Delete.
3. **Emptying the trash says exactly what blocks it.** If a trashed page is locked (locked after it was trashed, or by another session), deleting it for good is refused with a list of every blocking page and a link to unlock each; the rest of the trash is still deleted. Never a general failure.
4. **Restoring always works.** Restore puts a branch back where it was. When the original parent is gone or itself in the trash, Restore asks for a new parent instead of failing. Locks do not block Restore.

## Tests to write first

* Trashing a branch with a locked page deep inside is refused, the message names that page, and every page stays where it was.
* Trashing a branch with a system page is refused.
* A page locked after being trashed shows Unlock in the trash, and unlocking it needs only `pages.status`.
* Emptying a trash that holds one locked branch deletes everything else and reports that branch's locked pages, each with a link.
* Restoring a branch whose parent was deleted asks for a new parent; restoring a branch with locked pages works.
* A user with `pages.delete` limited to one branch can trash only that branch and empty only its pages from the trash.
* The same through the API, MCP and the agent (`pages_trash`, `pages_restore`, `pages_delete` on trashed pages).

## Design

1. **Decided:** trashed pages live under a system page `/_trash/` (template `trash`, hidden, unpublished, no URL), created the first time something is trashed. Each branch stays whole. A `lynx_trash` row per branch records its original parent, position, names, the ancestors of that place, and who trashed it when.
2. **Decided:** URLs. The trash template has no URL, so trashed pages have none, and the trash is unpublished, so their old URLs answer 404 instead of redirecting into it (the publication check runs before redirects). Restore makes the old paths active again.
3. **Decided:** names. The top page of a trashed branch is renamed `{id}-{name}` in every language; Restore puts the names back, and refuses (asking for another parent) when a sibling took one meanwhile.
4. **Open:** emptying old trash automatically (after N days), as a scheduled command.

Permissions inside the trash follow the original place: a page there also counts the ancestors recorded when it was trashed, so grants limited to a branch still reach it (unlock, restore, empty).

## Admin (built)

* The Trash is the last child of Home in the page tree (it always stays last), with a trash icon.
* In the site's tree, Delete becomes Trash ("Move to trash", after a confirmation with the number of pages). Trees rooted elsewhere, such as a menu, still delete directly.
* Per trashed branch: Restore, Delete for good (after a confirmation) and the status toggles, so Unlock is always offered. Dragging a trashed branch into the tree restores it there.
* Empty trash on the Trash row, after a second click. A report says how many branches were deleted and lists each kept branch with links to its locked pages.

## Build order (TDD throughout)

1. Core: `pages.trash`, `pages.restore`, delete for good from the trash, the rules above, ADR.
2. Paths and names of trashed pages (design questions 2 and 3).
3. Admin: Trash in the page tree, Restore, Unlock, Empty trash.
4. Agents: tools and the usage guide.
5. Automatic emptying (question 4), if decided.
