---
title: Versioning and Support
description: What each Lynx version number promises, how long a version gets fixes, and how deprecations work.
order: 3
---

# Versioning and Support

Every `lynxcms/*` package shares one version number, and they are released together.

## Before 1.0

Lynx is at `0.x`. Any release may change APIs, database tables or configuration. Each release lists what changed in the [release notes](/prologue/release-notes) and what to do in the [upgrade guide](/prologue/upgrade). Pin an exact version on a site you depend on, and read both before you update.

Only the latest `0.x` release gets fixes, security fixes included.

## From 1.0 on

Lynx follows [semantic versioning](https://semver.org) for its public API:

- **Major** (`2.0`): may break the public API. The upgrade guide lists every break and what to do.
- **Minor** (`1.1`): new features. Nothing in the public API breaks.
- **Patch** (`1.1.1`): fixes only.

### What the public API is

Classes marked `@api` are the public API: their names, their public methods and properties, and what those accept and return. Classes and methods marked `@internal` may change in any release, so apps and modules shouldn't use them. Every class carries one of the two markers, and PhpStorm warns when code outside Lynx uses an `@internal` one.

Beyond PHP classes, these are public too, and change only in a major release:

- action names and their input, as `lynx()->run()`, MCP and agents use them;
- events and what they carry;
- Artisan commands and their options;
- configuration keys and environment variables;
- Blade components, directives and their attributes;
- field type and formatter names, and the settings each one takes;
- the selector syntax;
- URLs Lynx serves to visitors.

The admin's screens, their wording and their HTML are not public API: they can change in a minor release.

### Database changes

A minor or patch release may add migrations. They run with `php artisan lynx:migrate`, and the upgrade guide says so. They never need changes in your code.

### Deprecations

A part of the public API that is going away is first marked `@deprecated` in a minor release, with what to use instead, and listed in the release notes. It keeps working until the next major release, which removes it.

### How long a version is supported

| Version | Bug fixes | Security fixes |
| --- | --- | --- |
| The latest minor release of the current major | Yes | Yes |
| Older minor releases | No: update to the latest minor | No |
| The previous major version | No | For 12 months after the next major is released |

### PHP and Laravel

Each major version states the PHP and Laravel versions it supports. A minor release may add support for a new Laravel or PHP version. Dropping one happens only in a major release.

## Upgrading from 0.x to 1.0

Update to the latest `0.x` release first and run `php artisan lynx:migrate`. Then update to 1.0 as the upgrade guide describes. 1.0 keeps every migration of the `0.x` releases, so a site installed with any `0.x` version upgrades the same way as a new one.
