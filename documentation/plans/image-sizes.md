# Plan: image sizes in a queue

Status: built on 2026-11-24 (ADR-0152). Roadmap item "Image sizes made in a queue instead of on first request". Uploads are scaled down in the browser first (separate PR), which this plan does not depend on.

## Problem

A template asks for a size with `$image->size(800, 600)`, `resize()`, `preset()`, `srcset()` or `<x-lynx-image>`. The size is named at once (`Variations::make()`), and its file is written the first time its URL is printed: `Pageimage::url()` calls `file()`, which calls `Variations::write()`. So the first render of a page waits for every new size on it.

- `<x-lynx-image>` without both sizes prints a srcset of three widths. A gallery of 24 images is 72 resizes in one request.
- Originals are at most 2560 pixels on a side (`StoreMedia` scales larger uploads down), so one resize is quick, but 72 of them add up to seconds and can pass PHP's or the proxy's time limit.
- It happens again after every upload, every focus point change (the name holds the focus) and every new preset.
- With the page cache on, the slow render is the one that gets stored, so only one visitor pays. Sites without the cache pay on every first view.

## Decisions (2026-11-24)

1. **A queue.** Missing sizes are made by queued jobs, not during the render.
2. **Optional, off by default.** `lynx-media.queue_sizes` (`LYNX_MEDIA_QUEUE_SIZES`) is false: sizes are made during the render, as today. A site turns it on when it runs a queue worker.
3. **No stand-in route.** Sizes are only ever made by the render (setting off) or by a job (setting on).

## Design, with the setting on

1. **Printing a missing size queues it.** `Pageimage::url()` for a variation whose file is missing dispatches `MakeImageSize` (the original's page and file, and the variation's file name, which holds its whole spec; ADR-0046) and returns a stand-in URL. The job is unique per file name (`ShouldBeUnique`), so a gallery printed by ten visitors queues each size once.
2. **The stand-in is the nearest image that exists.** The smallest existing size of the same original that is at least as large, else the original. A `resize()` keeps its aspect ratio, so the stand-in looks right, only heavier. A `size()` crop has another aspect ratio: `<x-lynx-image>` prints `width` and `height` and adds `object-fit: cover` and the focus point as `object-position`, so the stand-in is cropped by the browser at the same place. A template that prints `url()` into its own `<img>` gets the original's aspect until the job runs; the docs say to use the component or set `object-fit`.
3. **The job makes the size.** `MakeImageSize` writes the file with `Variations::write()`, which already skips files that exist. Remote media (ADR-0082) is fetched first, as now. A failure (a broken original, a missing file) is logged with the page and file, and the job does not retry: the page keeps showing the stand-in.
4. **The next render prints the size.** Once the file exists, `url()` returns its disk URL, as today.
5. **Page cache.** A page that printed a stand-in is not stored, so the cache never keeps HTML with stand-ins. Media marks the request with the page cache's opt-out attribute; its name moves from `LynxCms\Cache\Pages\Eligibility::OPT_OUT` to `LynxCms\Contracts` so media does not depend on the cache module. The first render after the jobs ran is stored.
6. **Code that needs the file.** `file()` still writes the size at once, so code that reads the bytes (a PDF, an email attachment, the static site build) always gets them. Only `url()`, `srcset()` and `<x-lynx-image>` queue.
7. **A worker that is not running.** With the setting on and no worker, pages keep their stand-ins: correct, only heavier. `lynx:doctor` warns when the setting is on and the queue connection is `sync` (each job would then run inside the render, as with the setting off). A warning for jobs waiting too long was left out: Lynx can't see every queue driver's backlog.

## Later, if a site needs it

- **Sizes made ahead.** A table `lynx_media_sizes` (field, spec, last used) records each spec a template printed; after `media.store` saves an image, a job makes that field's recorded specs for it. `php artisan lynx:media-sizes` makes every recorded spec for every image, for deploys, imports and `lynx:media:pull`. This adds a table and a migration, so it waits for a site that shows the need.

## Tests to write first

- With the setting off, a page printing a new size writes the file during the render, as today.
- With the setting on, it does not: it queues one `MakeImageSize` per missing size and prints the stand-in.
- The stand-in is the smallest existing larger size, else the original; `<x-lynx-image>` prints the crop's width and height with `object-fit: cover` and the focus point.
- Printing the same missing size twice queues one job.
- The job writes the file, and the next render prints its URL.
- A broken original: the job logs the page and file, does not retry, and the page renders with the stand-in.
- `file()` writes at once with the setting on.
- The page cache does not store a page with a stand-in, and stores the render after the job ran.
- Remote media: the job downloads a missing original first.
- `lynx:doctor` warns about the `sync` connection with the setting on.

## Files

- `packages/media/config/lynx-media.php`: `queue_sizes`.
- `packages/media/src/Values/Pageimage.php`: `url()` queues and returns the stand-in when the setting is on and the file is missing.
- `packages/media/src/Images/Variations.php`: `exists()`, `standIn()`.
- New `packages/media/src/Images/MakeImageSize.php` (the job).
- `packages/media/src/View/ImageComponent.php` and its view: `object-fit` and `object-position` for crops.
- `packages/contracts`: the page cache opt-out attribute name; `packages/cache/src/Pages/Eligibility.php` reads it from there.
- `packages/media/src/Doctor`: the queue check.
- Docs: `basics/images-and-files.md` (sizes in a queue), `modules/page-cache.md` (pages with stand-ins are not stored), configuration reference, release notes, ADR.
