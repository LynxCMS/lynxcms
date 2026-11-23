---
title: Page References
description: An admin screen with reports on the links between pages (broken links, pages nothing links to, and the most linked pages) and the health of the page references index.
order: 13
---

# Page References

The `lynxcms/page-references` module adds **Page references** to the admin: reports on how pages link to each other through page fields and menus. Editors find links that lead to a deleted, trashed, or unpublished page, pages that nothing links to, and the pages most others point at.

The reports read the index of page references that Lynx keeps as pages are saved, so each one takes a few queries on a site of any size. See [the page field](/basics/field-types#page) for how the index works.

## Setting up

```bash
composer require lynxcms/page-references
```

**Page references** appears in the sidebar under **Settings**. Users see it when their role lets them view pages, and each report lists only the pages they may view.

## Reports

- **Broken links** lists page fields that point at a page that was deleted, is in the trash, or is unpublished. Visitors don't see these links. Each row links to the field (and the repeater or matrix item) that holds the reference.
- **Not linked** lists pages that no page field or menu links to. Visitors reach them only through lists, search, or their address. The home page, the trash, and pages a module manages, such as menus, are left out.
- **Most linked** lists the 20 pages linked from the most pages, with how many.

Broken links and Not linked show 50 pages at a time.

## Index

Users whose role has the **Rebuild the index of page references** permission (`page_references.manage`) also see the index's health. **Check index** runs the rebuild as a [dry run](/deeper/actions-and-hooks#dry-runs) and says how many pages' rows differ from their stored values. **Rebuild index** runs `page_references.rebuild` on the queue, as the user who asked, so a large site's rebuild does not hold up the admin. With the `sync` queue it runs at once.

Saves keep the index current, so it only falls behind when something writes field tables outside Lynx's actions. `php artisan lynx:page-references:rebuild` does the same from the command line.
