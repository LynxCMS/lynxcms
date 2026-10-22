# Plan: drafts and revisions

Status: revisions (ADR-0038) and drafts with preview (ADR-0039) built in phase 12.

## Decisions

1. **Revisions on every save.** After a `pages.save` that changes field values, the page's values (every field, every language) are stored as a revision. The newest 50 per page are kept (`lynx.revisions.keep`). A revision can be compared with the current page in the admin's review table and restored; restoring saves its values through `pages.save`, which records a new revision, so nothing is lost.
2. **Drafts are page rows (Craft style).** A draft is a row in `lynx_pages` marked `draft_of` the live page, with its values in the same field tables, so field types, the page loader and the review table work on it unchanged. Drafts never appear in selectors, the tree, counts or URLs.
3. **One shared draft per page.** Any editor may work on it, under the edit lock (ADR-0037). The draft covers field values; names, status, template and place stay on the live page.
4. **Publishing a draft** saves its values onto the live page through `pages.save` (validation, hooks, activity and a revision), then deletes the draft. Discarding deletes it.

## Build order (TDD throughout)

1. Revisions: `lynx_revisions`, recording in `pages.save`, `pages.restore_revision`, pruning, the admin's Revisions list with Compare and Restore. ADR.
2. Drafts: the `draft_of` column and its exclusion everywhere pages are listed, `pages.save_draft`, `pages.publish_draft`, `pages.discard_draft`, the editor's draft mode. ADR.
3. Previewing a draft at the page's URL for editors (`?preview=draft`), and the tree's draft mark.
