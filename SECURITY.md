# Security Policy

## Reporting a vulnerability

Please report vulnerabilities privately, through [GitHub's advisory form](https://github.com/LynxCMS/lynxcms/security/advisories/new). Don't open a public issue, pull request or discussion for them.

Say which package and version you tested, what an attacker can do, and how to reproduce it. A short proof of concept helps.

Report everything here, in `LynxCMS/lynxcms`. The package repositories (`LynxCMS/core`, `LynxCMS/admin` and the others) are read-only copies of this one, and so are the starter apps' generated repositories.

## Supported versions

Lynx has not reached 1.0. Security fixes go into the latest release only, so stay on it.

| Version | Supported |
| --- | --- |
| Latest `0.x` release | Yes |
| Older releases | No |

Each release lists its security fixes in the [release notes](https://lynxcms.org/cms/docs/prologue/release-notes).

## What counts

Lynx's permissions decide what each role may do, so anything that lets someone go beyond them counts: a visitor reaching unpublished or protected content, a role gaining rights it wasn't given, script running in another person's admin session, or a read or write outside a user's permissions through MCP or the agent.

Superusers, and roles with `schema.manage`, are trusted with the site's schema, and people who can run Artisan commands are trusted with the server. Reports that need those rights to begin with are usually not vulnerabilities.

Problems in Laravel, Livewire or other dependencies belong to those projects, unless Lynx uses them in an unsafe way.
