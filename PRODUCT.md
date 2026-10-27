# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

**Primary: editors in the admin.** Non-technical people, often the client who owns the site or their content staff, who sign in at `/lynx` to keep a site's pages up to date. Their jobs: find a page in the tree, change its fields, translate it into the site's other languages, preview a draft, publish now or on a schedule, and recover from mistakes through history and the trash. They should never need to understand templates, field types or selectors.

**Secondary: developers and superusers.** Laravel developers who install Lynx, define templates and fields (in the admin, PHP or YAML), manage roles and users, and brand the admin for their client. In the admin they see extra developer details (template names, field types, string keys) that editors never see.

Design work in this project targets the Livewire admin first. The docs site, lynxcms.org and the workbench demo are out of scope unless a later request names them.

## Product Purpose

Lynx is a multilingual CMS packaged for Laravel 13. It adds a page tree, fields defined at runtime without migrations, and an admin to a Laravel app the developer already owns. Languages are a core concept, and every write (from the admin, PHP, Artisan, the MCP server or the built in AI agent) goes through the same validated actions, with permissions, dry runs and an activity log.

The admin succeeds when an editor finishes a content task without help, without fear of breaking something, and without noticing the machinery underneath.

## Positioning

- ProcessWire's page tree, runtime fields and selector API inside any Laravel app, rather than a separate platform.
- Languages are rows, not columns: a language is added to a running site in one step, and each field falls back along a language chain at read time, so a partly translated page is normal and visible.
- People and AI agents edit through the same actions. Agents plan as a dry run and apply only what a person approves.

## Operating Context

- The admin is a Livewire 4 application inside the host Laravel app, served at `/lynx` (`LYNX_ADMIN_PREFIX`).
- Screens: page tree, page editor (fields per template, a tab per language, Settings, Texts, drafts, schedule, history, revisions), menus, SEO, sitemap, templates, fields, languages, roles, users, account security, and Settings > General for branding.
- A top bar search (Cmd or Ctrl+K) takes plain text or a selector.
- Each user picks light, dark or system theme in the sidebar user menu.
- A site with one language never mentions translations in the admin.
- Each editor sees only what their role grants. Superusers see developer details; editors see plain labels.
- Pages may be locked while someone else edits them (edit locks), and drafts are shared per page.

## Capabilities and Constraints

- Built on BlatUI (ADR-0034): owned copies of components in `packages/admin/resources/views/components/ui`, BlatUI tokens (`--primary`, `--background`, `--muted` and the rest), presets chosen in `lynx-admin.appearance`, and extra stylesheets in `lynx-admin.stylesheets`. Only the page tree keeps `--lynx-*` tokens, each defaulting to a BlatUI token. Design changes must keep this token contract working for host apps.
- Client branding (ADR-0084): superusers set a name, logo, sign-in background and accent colour; the accent re-colours BlatUI's primary tokens in light and dark mode, with text colour picked by contrast. Any design must look right under an arbitrary client accent and logo, not only the defaults.
- Stack: Livewire 4, Alpine (with anchor, collapse and focus plugins), Tailwind CSS 4 CLI, esbuild, Tiptap 3 for rich text, Lucide icons through `mallardduck/blade-lucide-icons`, Floating UI. Assets are built into `packages/admin/dist` and never committed (ADR-0071).
- The admin, the menus module and paid modules (in their own private repositories) are built from the same components, so they look alike. Paid modules use only `@api` code.
- Lynx is pre release: APIs and screens may change before 1.0, and design decisions that change an accepted ADR need a new ADR.
- Undecided: whether the admin ships translated interface strings for editors in other languages.

## Brand Commitments

- Name: Lynx (Lynx CMS).
- Logo: the Lynx mark, a faceted lynx head in three flat teal tones (deep `#004459`, mid `#007595` (Lynx Teal), light `#56BAD5`) with a white eye. Sources in `design/logo`: the full mark (24px and up), a 16px drawing on a pixel grid, dark versions, a one colour version (`currentColor`) and the favicon set. The wordmark is the name set in the interface font, not a drawn logotype.
- The admin is white label by design: a client's name, logo and accent take over the sign-in screens, sidebar header and browser title. The Lynx mark and favicon appear only while a site has no name or logo of its own (ADR-0089); an accent never recolours the mark.
- Voice: plain words for editors, with no jargon (the docs describe the admin as "in plain words"). Developer vocabulary appears only in superuser views.

## Evidence on Hand

- Demo site in the workbench: a small multilingual course site with demo users (`composer build && composer serve`, admin at `/lynx/pages`; `/demo/sign-in/editor` and `/demo/sign-in/courses` show other roles). The workbench data belongs to the user; do not rebuild it without asking.
- Documentation of every admin screen in `docs/admin/`, design decisions in `documentation/ADRs.md`.
- No testimonials, customers, usage numbers, pricing or press exist yet. Future work must not invent them.

## Product Principles

1. **Editors first, machinery hidden.** Every screen speaks the editor's language; template names, field types and selectors stay behind the superuser view.
2. **Safe to try.** Drafts, previews, dry runs, history and the trash mean nothing an editor does is final by surprise; the interface should make that safety visible.
3. **Languages are first class.** Translation state and fallback are always legible per page and per field, never an afterthought or a hidden mode.
4. **The client's admin, not ours.** The design holds up under any client logo and accent, and under a host app's token overrides.
5. **One system everywhere.** Core screens, the menus module and paid modules share components and tokens, so new screens extend the system rather than fork it.

## Accessibility & Inclusion

WCAG 2.2 AA is required across the admin, in light and dark mode, and under any client accent (contrast is picked per accent, per ADR-0084). Keyboard operation must cover the page tree, drag to reorder (with a non drag alternative), dialogs and the command search.
