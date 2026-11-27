# Plan: insights

Status: PR 1 (engine and `MissingTranslations`) built on 2026-11-27, branch `feat/insights`. Step 1 of the AI roadmap discussed on 2026-11-27 (periodic reports, then schema insights, then on-demand translations, then read-only agent access). Decided on 2026-11-27: the name is Insights; everything is free, in a new module `lynxcms/insights` in the monorepo; approval runs the whole plan; the only notification is a count on the admin home.

## Context

Lynx records every write as an action with a dry run, and the built-in agent already plans in a rolled-back transaction and replays the recorded calls on approval (ADR-0020, `AgentRun`). Nothing looks at the site on its own and proposes work.

The owner wants a report every period (30 days by default) that lists insights: suggested changes, each with its evidence. An editor approves the ones to make, dismisses the ones not wanted, or archives them to see them again, with fresh numbers, in the next report. Nothing changes without an approval.

Later steps reuse the report: schema insights (step 2) and languages worth adding (step 3) are new kinds of insight, so the report must not assume content changes only.

## Module

`packages/insights` (`LynxCms\Insights`), split to `LynxCms/insights` like the other modules (add it to `split.yml`). It requires `lynxcms/core` and suggests `lynxcms/admin` (the screen), `lynxcms/ai` (the AI source) and a metrics provider.

- **A module, not core.** Insights is a feature a site opts into, with its own tables, schedule and screen, as the page cache is. Core keeps the actions and the page model it builds on and gains nothing.
- **The AI source lives in the module,** registered only when `lynxcms/ai` is installed (the way the cache module adds its screen only when the admin is bound). Core still never depends on laravel/ai (AGENTS.md rule 9). As a Lynx package, the module may use `packages/ai`'s `@internal` code (`CmsAgent`, `ReadTool`, `AgentSession`), so nothing has to become `@api` for it.
- **Other modules add sources.** seo registers `MissingDescriptions` when insights is installed. A project or client module registers its own with `InsightSourceRegistry::add()`. A source from any origin is equally safe to install: it can only propose plans, and a plan runs as the approver, after a fresh dry run.
- **Insights and the doctor:** `lynx:doctor` stays for developers and the site's setup (views, routes, scheduler, unused fields). Insights are for editors and the content. A check fits one or the other, never both.
- **The contract is a promise:** `InsightSource`, `Insight` and `InsightPeriod` are `@api`, so from 1.0 they can't change without a major release. They stay small: kinds are strings, evidence is a list of label and value, and new needs go in new optional properties.

## Decisions

1. **The engine needs no AI.** An insight source is a class that reads the site and returns insights, as a doctor check returns findings (ADR-0109). The AI agent is one source among others.
2. **An insight carries a plan, never a result.** A plan is a list of action calls (action name and arguments). Approving runs the whole plan through `ActionRunner`, acting as the approver, so the approver's permissions, validation and hooks apply as for any write. There is no partial approval. An insight may have no plan (information only).
3. **The preview is computed at review time.** "Review changes" runs the plan as a dry run when the editor opens it, so the editor sees what would change now, never a stored change list from 30 days ago.
4. **An insight records what it was based on.** Its basis is the `modified` time of each page it names and, for schema insights later, a hash of the templates and fields involved. Approving refuses when the basis changed ("Page [x] changed since this report"); the insight stays open and the next report recomputes it.
5. **Metrics come through a contract.** `LynxCms\Contracts\Metrics\PageMetrics` (`@api`, in contracts) with `totals(list<int> $ids, string $event, int $days): array<int, int>` and `daily(...)`. Nothing binds it by default; sources that need metrics are skipped when it is unbound. lynx-homepage binds its `App\Stats\PageStats`, which proves the contract before a stats module exists.
6. **Dismissed insights stay quiet.** Each insight has a fingerprint (source key, kind and the sorted ids of its subjects). A dismissed fingerprint is not reported again for `lynx-insights.quiet_days` (180 by default), unless the source's weight for it has doubled since.
7. **No evidence, no insight.** Metric sources run only when the site has `lynx-insights.min_days` of data (14), and each insight lists the numbers it rests on. A report with nothing to suggest says so.
8. **No notifications.** The admin home shows the number of open insights. Email can come later as a listener on the report's event.

## Engine

- `LynxCms\Insights\InsightSource` (`@api`): `key()`, `label()`, `insights(InsightPeriod $period): list<Insight>`. Read only, like `Doctor\Check`. Registered with `InsightSourceRegistry::add()` from a service provider.
- `Insight` (`@api`, readonly): `kind` (`content`, later `schema`, `language`), `fingerprint`, `title`, `reason`, `evidence` (list of `{label, value}`), with title, reason and labels as `InsightText` (a translation key, its replacements and an optional count, translated where it is shown, so each editor reads a report in their own language; a plain string shows as it is), `plan` (list of `{action, arguments}`), `basis`, `weight` (float, used for ordering and for decision 6).
- Tables (the module's migration):
  - `lynx_insight_reports`: id, period_start, period_end, failures (json: source and error), created_by (actor), timestamps.
  - `lynx_insights`: id, report_id, source, fingerprint, kind, title (json), reason (json), evidence (json), plan (json), basis (json), weight, state (`open`, `approved`, `dismissed`, `archived`, `superseded`), carried_from (id of the archived insight it replaces), decided_by, decided_at, error. Index (fingerprint, state).
- Actions, registered from the module's provider through `ActionRegistry`, so MCP and the agent get them:
  - `insights.generate`: runs every source, drops quiet fingerprints, carries archived insights forward (a source that reports the same fingerprint again replaces it; one that doesn't leaves it closed as `archived`), writes one report and fires `InsightsReported` (`@api`). A source that throws is logged with its key; the report keeps the others and lists the failure. Runs in a queued job when the queue is not `sync` (from PR 5, for the Insights Agent; the deterministic sources run inline).
  - `insights.approve`: checks the basis, then runs the whole plan in one transaction through `$context->run()`. Records the changes and the insight id in the activity log. A failure rolls back the whole plan and leaves the insight open; the error goes back to the editor.
  - `insights.dismiss` and `insights.archive`: change the state, record who and when.
- Permissions: `insights.view` and `insights.review` (requires `pages.view`). Approving also needs the permissions of every action in the plan, through the runner as usual.
- `lynx:insights` command (generate now, `--dry-run` prints them) and a schedule from `lynx-insights.schedule` (null by default; `monthly` or a cron expression), the way the cache module schedules pruning.
- Plans are validated when an insight is stored: every action name must be registered. Approve refuses a plan whose changes include a deletion (`ChangeSet::isDestructive()`), after running it inside the transaction, so it is rolled back.
- Nested actions skip the action-level permission check, so approve checks every step's action permission for the approver before running any.

## Admin

Registered by the module when the admin is installed, as the cache module does (`Admin/` in the module, its CSS built from the admin's `module.css` if it needs any).

- An **Insights** screen: the latest report, ordered by weight, each insight with its title, reason, evidence (page links, numbers), and **Review changes**, **Approve**, **Dismiss** and **Archive**. Earlier reports are listed below with their states.
- The home screen shows the number of open insights and links to the screen.
- Wording in the module's `lang/en` and `lang/pt`, help text per the Writing rules. Titles and reasons from sources are printed escaped.

## Sources

### Without metrics

Each weighs its pages by the number of pages linking to them (the page references index, ADR-0145), so sites without metrics still see the most linked pages first.

- `MissingTranslations`: published pages with no translation in an active language. Plan: `pages.translate` for that language when a translation provider is bound, else information only.
- `StaleDrafts`: drafts nobody saved for 30 days. Information only: discarding a draft loses work, so it stays the editor's decision in the editor.
- `LinksToHiddenPages`: published pages whose rich text or page fields point at an unpublished or trashed page. Information only, with the links.
- `MissingDescriptions` (registered by `packages/seo`): published pages without a meta description.

### With metrics

- `UntranslatedPopularPages`: `MissingTranslations` weighted by views instead of links, used in its place when `PageMetrics` is bound.
- `NeglectedPopularPages`: pages in the top 20 by views that nobody modified in 12 months. Information only; the evidence shows views and the last change.

### Insights Agent (AI, on laravel/ai)

The module and its screen are called Insights, since most sites run them without AI. The AI source is the **Insights Agent**: its own switch in the settings ("Insights Agent", with help text saying it sends page text to the AI provider and uses its credits), and the name the docs use for it.

- `InsightsAgent` implements laravel/ai's `Agent`, `HasTools` and `HasStructuredOutput`. Tools: `CmsAgent`'s read tools (`describe_schema`, `get_template`, `find_pages`, `get_page`, all under `SelectorPolicy::forActor()`) plus a `page_metrics` tool (top pages, rising and falling pages over the period) when metrics are bound. Its schema returns at most `lynx-insights.ai.max` (5) insights: title and reason as plain text in the admin's default language (the model writes them, so there is no key), the evidence it used (numbers and page ids), and the pages involved. Attributes: `#[MaxSteps]` and `#[Timeout]`; provider and model from the module's config, so a site can pick a cheaper model for the analysis. Off unless `lynx-insights.ai.enabled` is true, since it costs money.
- For each insight, `LynxAi::ask()` plans the change in plan mode and returns an unapplied `AgentRun`, whose steps become the plan. A planning failure or timeout keeps the insight as information only.
- Cost: one run per report, capped by `ai.max` and by the pages it may read (the top 50 by views, or by links). The report shows the model used.
- Page text the agent reads may contain instructions. The agent cannot write (plan mode rolls back), every plan is shown as a fresh dry run before approval, and approval runs under the approver's permissions.
- Tests use laravel/ai's fakes (`InsightsAgent::fake()` with structured responses), so no test calls a provider.
- laravel/ai's tool approvals (`Approvable`, pending approvals) were considered for the approval step. They resume the conversation with the model after a decision, so approving would ask the model again and keep a conversation open for 30 days. Replaying recorded action calls (ADR-0020) runs exactly what the editor reviewed, without the model, and is kept.

## Database backup before an approval (after the backup module)

Waits for a backup module (`lynxcms/backup`, not planned in detail yet) that makes database backups. Content packages (ADR-0040) are not enough: they leave out activity, revisions, users and roles. Until then, nothing here is built.

- **The backup module owns the backup:** its queued job, format, storage and retention. It fires `BackupStarted`, `BackupCompleted` and `BackupFailed` (with the backup's reference and the reason it was made) for anyone who watches: notifications, the activity log, the Insights card.
- **Contract in `lynxcms/contracts`:** `DatabaseBackups` (`@api`): `isAvailable(): bool`, `create(string $reason): BackupReference` (makes the backup in the calling process, meant for a queued job, and fires the module's events) and `latest(): ?BackupReference` (id, when, size, a URL to its admin screen). The backup module binds it. Insights depends only on the contract, so it stays free of the module, as it is of laravel/ai.
- **No chain, every stage on the insight:** a `Bus::chain` keeps its later jobs inside the first job's payload, so Horizon shows one job and a failure drops the rest without a record of its own. Laravel's Pipeline runs in one process, so it cannot hold a backup and minutes of steps either. Instead, Insights' own job `BackUpBeforeApproval` marks a "Database backup" entry in the insight's step list as running, calls `create()`, keeps the reference, and then queues the steps' batch itself. The insight's step list is the record of the whole run, backup included, readable in the admin and in the database.
- **The question:** with a binding, Approve opens a dialog before anything runs: "Back up the database before these 20 changes?", with **Back up and approve**, **Approve without a backup** and **Cancel**. It names the latest backup and its age, so an editor who backed up ten minutes ago can skip it. Without a binding, no dialog; the review says the site has no database backups, as `lynx:migrate` asks.
- **Setting:** `lynx-insights.backup`: `ask` (default), `always` (no dialog, always backs up) or `never` (no dialog).
- **Action input:** `insights.approve` takes `backup` (bool). Its default comes from the setting: with `ask`, true when the binding is available. So MCP clients, the agent and `lynx:insights` get the same protection without a dialog, and can turn it off explicitly.
- **Failure:** a failed backup shows like a failed step: in the list, with the Details dialog (trace for superusers), `report()`ed to the log and in `failed_jobs`. The insight is open again and no step runs.
- **Record:** the insight keeps the backup's reference (a `backup` column, json). Its card and the step details link to it, so an editor who regrets an approval finds the backup that came right before it. The reference is what `create()` returned. Restoring stays in the backup module, never in Insights.
- **Who may back up:** the backup runs as the system, not as the approver: it only adds a file, and a reviewer without `backups.create` should still be protected. To keep storage in check, an approval reuses a backup newer than `lynx-insights.backup_reuse_minutes` (10) instead of making another, and says so in the dialog.

Open questions for that PR:

1. Whether plans that only write drafts (the missing translations plan) ask at all, or skip the question by default, since publishing is still a separate step.
2. Whether the same contract and dialog serve other bulk changes: `lynx:import`, Page Finder's bulk actions and `lynx:schema:apply --force`. If so, the dialog belongs in the admin package, not in Insights.
3. The backup module's own ADR decides the format, storage disk, retention and how a backup of a large PostgreSQL or MySQL database stays consistent.

## lynx-homepage

- Requires the module, binds `PageMetrics` to `App\Stats\PageStats`, and schedules a monthly report. Its seeded history (`stats:fake-history`) gives the demo a first report.

## ADRs

- **ADR-0155: Insights are reports of planned actions, approved as a whole.** The module, decisions 1 to 4 and 6 to 8, and how other modules add sources.
- **ADR-0156: Page metrics come through a contract.** Decision 5; Lynx stores no metrics.

## Pull requests

One at a time, each after the previous merges.

1. Module skeleton and engine: contract, tables, `InsightSource` API, the four actions, the event, command, schedule, `MissingTranslations` (the first real source, which drives the tests), ADR-0155. Storage and public API, so a pull request. `split.yml`, the install picker (`ModuleCatalog`) and the README wait for PR 2, once editors have a screen, and the `LynxCMS/insights` repository must exist before `split.yml` lists it.
2. Admin screen, home count, wording; `split.yml`, `ModuleCatalog`, README.
3. The other sources without metrics, and `MissingDescriptions` in seo.
4. Metric sources, with the `PageMetrics` contract and ADR-0156.
5. The AI source.
6. lynx-homepage: the module and the `PageMetrics` binding.
7. Docs: a page under Modules (insights, its sources, the metrics contract, the AI source and its cost) and a guide chapter on writing an insight source in a project module, with a tested snippet in `docs/snippets`.

## Tests to write first

- `insights.generate` stores a report with each source's insights, in weight order; a failing source is listed and the others are kept; dry run writes nothing.
- A dismissed fingerprint is not reported again within `quiet_days`, and is when its weight doubled.
- An archived insight comes back in the next report with new evidence, linked by `carried_from`.
- `insights.approve` runs the plan as the approver: refused without the plan's permissions, refused when a basis page changed since, rolled back with the insight left open when a step fails, or when the plan deletes.
- A plan naming an unknown or destructive action is refused when stored.
- The same through MCP (`insights_generate`, `insights_approve`).
- Each source without metrics reports the expected pages, ordered by inbound links; `MissingTranslations` plans `pages.translate` only with a provider bound.
- Metric sources are skipped when `PageMetrics` is unbound or has fewer than `min_days` of data.
- The AI source is not registered without `lynxcms/ai` or with `ai.enabled` off; with laravel/ai's fake, structured insights become stored insights with recorded plans, and a planning timeout leaves an information-only insight.
- A source registered by an app (the docs snippet) appears in the report, and its plan runs under the approver's permissions.
- The screen and home count appear only with the admin installed; the count shows open insights only.
- Architecture: core does not depend on the module; the module uses laravel/ai only in its AI source.
- On SQLite, PostgreSQL and the table prefix.
