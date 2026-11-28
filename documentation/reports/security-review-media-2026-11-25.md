# Security review of the media package, 2026-11-25

Reviewed on 2026-11-25, on `main` at `8a1439d8`. The review was read-only: no code changed and no test ran. Two small checks ran in PHP without touching the site: `PublicAddress::is()` against a list of addresses, and the path normalizer against `..`. Every other finding was traced in the code only, and the file and line are given so each can be checked.

## Scope

Covered: all of `packages/media` (`src`, `config`, `resources/views`), about 3,200 lines. The questions were: what an upload may contain and where it lands, who may download a private file, whether downloading from a URL can reach the server's own network, and whether the admin's upload component trusts the browser.

Not covered: the admin's JavaScript (the upload zone and the image shrinking in the browser), Livewire's own temporary upload endpoint, the web server's configuration for the public disk, cloud disks (S3 and similar), and testing against a running server. The earlier report (`security-review-2026-11.md`) looked at private media and signed links. This one goes further into stored values and downloads.

## Summary

No SQL injection, path traversal or server-side request forgery was found. The download guard is careful and passed every address tried.

The main finding is in what a media field accepts when a page is saved. `media.store` checks a file thoroughly, but the field then trusts whatever the saved value names, so the checks can be walked around.

| Id | Severity | Finding | Where |
|---|---|---|---|
| M1 | Medium | A media value may name any stored file, and its size, type and dimensions are taken as sent | `Fields/MediaFieldType.php:207` |
| L1 | Low | With the Imagick driver, an upload is decoded before its format is checked | `Actions/StoreMedia.php:193` |
| L2 | Low | A private file stays downloadable after it is removed, hidden, or uploaded to a draft | `Http/ServePrivateMedia.php:42` |
| L3 | Low | The list of refused extensions misses types that browsers run as XML, and files with no known type | `Fields/FileTypes.php:19` |
| L4 | Low | A compressed download can grow past the size limit in memory | `Downloads/RemoteFile.php:68` |
| L5 | Low | `media.store` does not check the field's own access or lock | `Actions/StoreMedia.php:90` |
| L6 | Low | Public files of unpublished pages are protected only by a six-character suffix | `Actions/StoreMedia.php:108` |

## Findings

### M1. A media value may name any stored file

`media.store` checks the extension against the field's list, the size, the pixel count and, for images, that the bytes decode. It returns an entry (`file`, `bytes`, `mime`, `width`, `height`). The page's value changes later, when `pages.save` receives the entry back.

`MediaFieldType::item()` (`Fields/MediaFieldType.php:202`) is what checks that entry on save. It asks one thing of `file`: that it matches `^\d+\/(?!\.)[A-Za-z0-9._-]+$` (`:207`). It does not ask whether the file exists, whether it sits in this page's folder, whether it was stored for this field, or whether its extension is one the field allows. `bytes`, `mime`, `width` and `height` are kept as sent (`:220`, `ImagesType.php:71`). In the admin the items are a plain Livewire property (`Admin/Livewire/MediaField.php:39`), so the browser can change them, and MCP or the API can send any value directly.

**Who:** anyone with `pages.edit` on a page that has a media field.

**Effect:**

- **Upload checks are skipped.** Store `bomb.txt` through a files field (no decoding happens for files), then name it in an images field. When a template asks for a size, `Variations::render()` (`Images/Variations.php:71`) decodes it with no pixel limit. A small file that declares a huge canvas then exhausts memory on every view of the page, since the size is never written and each visit tries again. The extension list of the second field is skipped the same way.
- **Declared dimensions are false.** `width` and `height` drive `fit()` and the focus crop. They can be anything.
- **Another page's file.** A value may name `99/name.pdf`. For a private field, `url()` builds the route for page 99, which asks page 99's rules, so nothing leaks there. `temporaryUrl()` is different: it signs whatever the value names (`MediaPaths.php:96`). A template that hands out temporary links for a field's files would sign a link to another page's private file for an editor who knows its name.
- **Files outside the page's folder are written.** A size made from `99/photo.jpg` is written into page 99's folder.

`MovePrivateMedia` already guards against this for the one case it knows (`:82`, "a value naming another page's file never makes that file change disks"), which shows the gap is known in one place and open in the others.

**Fix:** in `item()`, with the page known, require that the file is in the page's own folder (or the folder of the page a draft belongs to), exists on the field's disk, and has an extension the field allows. Read `bytes`, `mime`, `width` and `height` from the file instead of the input, or keep what `media.store` recorded and refuse changes to them. `normalize()` receives only the value and the settings today, so the page has to reach it another way, or the check moves to where the page is known. This also removes the trailing newline that the pattern's `$` accepts (`"42/a.jpg\n"` matches).

### L1. Imagick decodes before the format is checked

`StoreMedia::image()` calls `getimagesizefromstring()` only to refuse a huge canvas (`Actions/StoreMedia.php:193`). When that function does not recognize the bytes, the code goes on and hands them to `Image::fromBytes()`. With the default GD driver that is safe: GD reads a handful of raster formats. With `IMAGE_DRIVER=imagick`, Intervention calls `readImageBlob()` with no format given (`vendor/intervention/image/src/Drivers/Imagick/Decoders/BinaryImageDecoder.php:58`), and ImageMagick picks the decoder from the content. Depending on the server's ImageMagick policy that includes SVG, MVG, PostScript and others, which have a long record of reading local files and reaching the network. A file named `x.png` is enough, since only the name's extension is checked.

The pixel limit is skipped in the same case: a format `getimagesizefromstring()` does not know is decoded at whatever size it declares.

**Who:** anyone with `pages.edit`, on a site that uses the Imagick driver.

**Fix:** before decoding, require `getimagesizefromstring()` to succeed and its type to be one of JPEG, PNG, GIF, WebP or AVIF, and to agree with the extension. Say in the docs that a site on Imagick should restrict coders in `policy.xml`.

### L2. A private file outlives its place in the page

`ServePrivateMedia` (`Http/ServePrivateMedia.php:38`) serves `{page}/{file}` when the file exists on the private disk and the visitor may open the page. It never asks whether the page's published value still names the file. So these stay downloadable by anyone who may open the page and has the address:

- a file an editor removed from the field, for as long as a revision names it, since `media.prune` keeps what revisions use;
- a file switched off in the field ("Hide");
- a file uploaded to a draft that is not published yet, because drafts share the page's folder;
- every size made from any of them.

The address holds a random suffix, so a visitor has to have seen it before. The usual case is an editor who published the wrong document, removed it, and believes it is gone.

**Fix:** serve a file to visitors only when the page's published value (in a media field or inside repeater and matrix items) names it and has it switched on, or is a size of one that is. Staff with `lynx.pages.view` keep access to everything, which the editor needs for drafts.

### L3. Extensions a browser runs

`FileTypes::NEVER` refuses `html`, `svg`, `xml`, `xsl` and others whatever a field's settings say. Two groups are missing:

- **Other XML types.** Browsers treat any `+xml` type as an XML document, and an XML document with an XHTML `<script>` element runs it. A web server that maps `xslt`, `rss`, `atom`, `rdf`, `gpx`, `kml`, `mml` or `xhtm` to such a type serves an uploaded file as a script on the site's own address. Allowing `gpx` or `kml` for route downloads is a plausible setting.
- **Extensions with no type.** The setting accepts any `[a-z0-9]+`. Apache sends no `Content-Type` for an extension it does not know, and browsers then guess from the content, which makes an HTML file with an unknown extension a page. Also missing from the list: `shtm`, `stm`, `mht`, `mhtml`, `jspx`, `asa`, `ashx`, `asmx`, `cfm`.

Private files are safe from both: `ServePrivateMedia` sends `nosniff`, a sandboxing CSP, and shows only images, video, audio, PDF and plain text in the browser. The risk is on the public disk, which the web server serves.

**Who:** needs `schema.manage` to allow the extension, then any editor of a page with the field.

**Fix:** turn the list around for what may be shown in the browser. Either keep an allowlist of extensions known to be inert, or tell site owners to serve the media folder with `X-Content-Type-Options: nosniff` and `Content-Security-Policy: sandbox`, and add a Doctor check that asks for a known file and reads the headers. Add the extensions above to `NEVER` in any case.

### L4. A compressed download can exceed the limit in memory

`RemoteFile::request()` stops a transfer through Guzzle's `progress` callback once more than the field's limit arrives (`Downloads/RemoteFile.php:71`). Curl counts the bytes on the wire. Guzzle asks for and decodes compressed responses by default, so a few megabytes of gzip can become gigabytes in `$response->body()` before `strlen($contents) > $maxBytes` is checked (`:121`). The request then dies at PHP's memory limit.

**Who:** anyone with `pages.edit`, with a server they control.

**Fix:** pass `'decode_content' => false`, or stream the body to a sink that stops at the limit.

### L5. `media.store` ignores the field's access and lock

`StoreMedia::handle()` authorizes `pages.edit` on the page (`Actions/StoreMedia.php:90`). It does not ask `FieldAccess::canEdit()` or whether the field is locked. `pages.save` does, so the value cannot change, but the file is written to the field's disk and stays until `media.prune` removes it. An actor without access to the field can fill the disk in that field's name, and for a public field the file is reachable at its address for a day.

**Fix:** refuse in `StoreMedia` what `ValueWriter` would refuse.

### L6. Public files rely on a short random suffix

A file is stored as `{page id}/{slug}-{6 characters}.{extension}` (`Actions/StoreMedia.php:108`). The suffix is six characters of `[a-z0-9]`, about 31 bits. For a public field, nothing else stands between a visitor and the files of an unpublished, trashed, password-protected or draft page: the web server serves the public disk without asking Lynx. The page id and a slug of the original file name are often guessable, and the web server has no limit on attempts.

This is how public disks work, and private fields exist for this reason. Two things would help: a longer suffix (16 characters costs nothing), and a sentence in the docs that a public field's files are public from the moment they are uploaded, whatever the page's status.

## Observations

- **`..` as a file name.** The routes accept `[A-Za-z0-9._-]+`, which includes `..`. `lynx/42/..` normalizes to `lynx`, a folder, so `exists()` is true and the response then fails with a 500 instead of a 404. Nothing is read. `(?!\.)` in the route pattern, as the stored name pattern has, would make it a 404.
- **Remote route in production.** `registerRemoteRoute()` registers the route whenever the public disk's URL has a path, though the comment on `ServeRemoteMedia` says "only while remote media is on". In production it serves a file that exists and answers 404 otherwise, so nothing is exposed. Registering it only when `RemoteMedia::isEnabled()` would match the comment.
- **Pinning needs curl.** `RemoteFile` pins the connection to the checked address with `CURLOPT_RESOLVE`. Without the curl extension, Guzzle's stream handler ignores that option and resolves the name again, which reopens the gap between check and use. Refusing to download without curl would close it.
- **Existence of private files.** `ServePrivateMedia` answers 404 for a missing file before it asks the page's gate, so a visitor facing a password form can tell whether a file name exists.
- **Memory while decoding.** The 50 million pixel limit allows about 200 MB per decode, and a resize decodes twice (`StoreMedia.php:200`, `:215`). With Imagick an animated image multiplies that by its frames.
- **Temporary links** work for whoever holds them until they expire, by design. They carry `no-store` and always download.

## Checked and found sound

- **Downloads from a URL** (`Downloads/RemoteFile.php`, `PublicAddress.php`). Only `http` and `https` on their default ports. Every address a host resolves to must be public, and the connection is pinned to the address checked. Redirects are followed by hand, at most three, with the same checks at each hop. A size limit and a 20 second timeout apply. Tested as refused: loopback, private ranges, link-local (`169.254.169.254`), carrier NAT (`100.64.0.0/10`), `0.0.0.0`, multicast, reserved, IPv4-mapped and IPv4-compatible IPv6, unique local and link-local IPv6, NAT64, 6to4 and Teredo. Hosts written as numbers (`2130706433`, `0x7f.1`, `127.1`) resolve to `127.0.0.1` and are refused.
- **Stored names.** `Str::slug` on the base name removes dots, slashes and anything else that could change a path, so `evil.php.jpg` becomes `evil-php-xxxxxx.jpg`. The extension is lowercased and checked against the field's list, never against `NEVER`-listed types even when an old setting holds one.
- **Private files** (`Http/ServePrivateMedia.php`). Staff need the `lynx.pages.view` gate on the page. Visitors need the page published, no page gate answering instead, and the visibility rules allowing it. Responses carry `private`, `noindex`, `nosniff` and a sandboxing CSP. SVG never opens in the browser.
- **Signed links** (`Http/ServeSignedMedia.php`). Laravel's `signed` middleware checks them, and the `private` flag is part of what is signed.
- **Pulling from production** (`Remote/RemoteMedia.php`). Off in production, `https` only, stored names only (no leading dot, no slashes beyond the page folder), and through the same download guard.
- **Moving between disks** (`MovePrivateMedia.php`). Only files in the folder of the page that holds the value move. Copies happen inside the action's transaction and originals go after commit.
- **The admin component** (`Admin/Livewire/MediaField.php`). `pageId`, `field` and `disabled` are locked. The screen is authorized on every request. Uploads go through `media.store` as contents, never as a path on the server. Views escape every value, and values placed in Alpine expressions go through `Js::from`.
- **SQL.** Query builder only, with table names from the field registry. The two raw fragments are constants.
- **Pruning and deleting.** `media.prune` is not exposed as a tool and keeps anything a value or revision names. A page's folder is deleted only after the delete commits.

## Suggested order

1. M1. It is the gap the other upload checks depend on. Add tests that save a value naming another page's file, a file of a type the field refuses, and false dimensions.
2. L1 and L4. Each is a few lines in one method.
3. L2, which needs a lookup of the page's published media values.
4. L3 and L6 together: the extension list, a longer suffix, and the docs for the public disk.
5. L5, then the observations.

## Resolution

Fixed on 2026-11-25 on the branch `fix/media-security-review`, with tests that fail without each fix.

| Id | Fix |
|---|---|
| M1 | While `pages.save` checks values, `SavingPage` (core) names the page. For editors, MCP clients and the agent, `StoredFileCheck` requires each file to be in the page's folder (or the folder of the page a draft belongs to), stored on the field's disk, with an extension the field allows, and a picture for an images field. Size, type and dimensions come from what the page already stores for the file, or, for a file the save adds, from the file itself, so a save reads the disk only for new files. Superusers and the site's code may save any value, as an import without files keeps the source site's names. The stored-name pattern no longer accepts a trailing newline. |
| L1 | An images field stores only JPEG, PNG, GIF, WebP and AVIF, read from the header before anything decodes the upload. A picture under the wrong extension is stored under its real one. The docs ask Imagick sites to restrict coders in `policy.xml`. |
| L2 | Visitors get a private file, or a size of it, only when the live page's values name it, switched on (`PublishedFiles`). Staff keep every file. |
| L3 | An extension is refused when it is on `FileTypes::NEVER` (now with the XML and server types listed above), has no known MIME type, or has a type browsers run. The docs show the headers to send for the public media folder. |
| L4 | Downloads keep the body as sent (`decode_content: false`), so the limit counts what arrives. |
| L5 | `media.store` refuses fields the actor may not edit and locked fields, as `pages.save` would. |
| L6 | Stored names end in 16 random characters. The docs say public files are public from upload. |

Observations: downloads now need the curl extension; media routes refuse names starting with a dot; the private file route asks the page before the disk. Not changed: the remote route's registration (it is set up at boot, while remote media can be turned on later through config, and it exposes nothing), memory while decoding, and temporary links, which work as designed.
