# Public API audit, November 2026

Done on 2026-11-19, on `main` after 0.1.52, as item 1 of the road to 1.0 (`documentation/Implementation plan.md`). Every class marked `@api` becomes a promise at 1.0, so each one should be needed by apps or modules. Nothing is changed yet: this report is the list to approve.

## How it was done

300 classes are marked `@api`. For each, a script looked for:

1. **Outside use:** the fully qualified name in the documentation, in the guides' tested code (`docs/snippets`), in the three starters, or in the paid `lynxcms/translate` module (which may only use `@api` code).
2. **Extension points:** interfaces and abstract classes.
3. **The class's short name in code examples** in the documentation.
4. **Public signatures:** a class that appears in a public method, property or `@property` of a class already public is public too, even when nobody imports it (how `PagesApi` reaches apps through `lynx()->pages`).
5. **Use by other Lynx packages only.**

Classes with none of these are proposed for `@internal`, with a reason. Judgement was added on top: events, exceptions the API throws, values that pages return and the module APIs (`lynx()->menus()`, `lynx()->forms()`) stay public even when no example names them.

## Result

| Verdict | Classes |
|---|---|
| Keep `@api` | 169 |
| Demote to `@internal` | 95 |
| Decide | 36 |

The demotions are mostly service providers (Composer discovers them), the admin's Livewire screens, Blade component classes (the tag is the API, not the class), table and schema helpers, and the inner parts of Page Finder, schema drafts and search.

## Recommendations for the "decide" group

* **Admin extension points used by Lynx's own modules:** keep `TemplateChoices`, `LinkFiles`, `TreeMarkers` and `BacklinkFilter` and document them in "Extending the admin", since a third-party module needs them as much as Lynx's do. Demote `ChoicesForm`, `ShortValue` and `SpreadsheetCell`, which are helpers.
* **Core classes only Lynx's packages use** (`AgentToolkit`, `PageHistory`, `PageUrl`, `PageUrls`, `PageName`, `NotFoundPage`, `TemplateUsage`, `PageAttributes`, `PermissionHolders`, `Role`, `RoleGrant`, `ChoiceOptions`, `ItemProperty`, `SubfieldGroups`, `LinkRel`, `ActivityEvent`): demote. All `lynxcms/*` packages share one version and are released together, so they may use each other's `@internal` code. Roles stay public through their actions (`roles.save`, `roles.assign`).
* **Classes public only through a signature** (`PageFinder`, `PageScope`, `PageLoader`, `PathResolver`, the selector parser's `ParsedSelector`, `SelectorParser`, `Clause`, `OrGroupSet`): narrow the public signatures that expose them (such as the `$scope` parameter of `PagesApi::find()`, which the admin and Page Finder use), then demote. Keeping them would freeze the selector engine's internals.
* **Later:** `SeoResolver` is demoted now; the headless API (`documentation/plans/headless.md`) will need SEO data and should expose it through its own public class.

## After approval

1. Change the markers, package by package, with the full test suite and the translate module's own `@api` test.
2. Add a test that every class in `packages/*/src` carries `@api` or `@internal`, and that the translate module and the guides' code use only `@api` classes.
3. Then the backward compatibility check in CI (item 2 of the road to 1.0), on the smaller list.

## Demote (95)


### admin

| Class | Why |
|---|---|
| `LynxCms\Admin\AdminPath` | behind lynx:admin-path |
| `LynxCms\Admin\Appearance` | admin internals |
| `LynxCms\Admin\Auth\Invitation` | admin auth model |
| `LynxCms\Admin\Auth\InvitationNotification` | admin auth mail |
| `LynxCms\Admin\Auth\TemporaryPassword` | admin auth model |
| `LynxCms\Admin\Editor\ChangeReview` | page editor internals |
| `LynxCms\Admin\Editor\FieldView` | page editor internals |
| `LynxCms\Admin\Editor\ReviewEntry` | page editor internals |
| `LynxCms\Admin\InvalidAdminPath` | thrown inside lynx:admin-path |
| `LynxCms\Admin\Livewire\AccountSecurity` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\FieldForm` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\FieldList` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\LanguageForm` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\LanguageList` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\PageCreator` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\PageHistory` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\PagePicker` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\PageRevisions` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\PageSchedule` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\PageSettings` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\PageTree` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\RoleForm` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\RoleList` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\TemplateForm` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\TemplateList` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Livewire\UserRoles` | an admin screen's component: apps use the screen, not the class |
| `LynxCms\Admin\Locale\AdminLocale` | admin internals |
| `LynxCms\Admin\Locale\SaveAdminLocale` | admin action nobody runs from code |

### ai

| Class | Why |
|---|---|
| `LynxCms\Ai\PlanTookTooLong` | agent internals |

### cache

| Class | Why |
|---|---|
| `LynxCms\Cache\CacheServiceProvider` | service provider: Composer discovers it, apps never call it |
| `LynxCms\Cache\Fragments\Fragments` | behind the fragment cache directive |
| `LynxCms\Cache\PageCacheSchema` | the package's own schema |
| `LynxCms\Cache\View\IslandComponent` | Blade component class; the tag is the API |

### core

| Class | Why |
|---|---|
| `LynxCms\Core\Content\ContentExporter` | behind lynx:export and the export action |
| `LynxCms\Core\Schema\Draft\CompiledDraft` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\Draft\DraftBuilder` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\Draft\DraftCompiler` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\Draft\DraftError` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\Draft\DraftJsonSchema` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\Draft\DraftOptions` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\Draft\DraftPage` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\Draft\DraftPaths` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\Draft\DraftPlan` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\Draft\DraftReader` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\Draft\DraftResult` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\Draft\DraftShorthand` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\Draft\DraftWriter` | schema draft internals; the draft actions are the API |
| `LynxCms\Core\Schema\SchemaSync` | behind lynx:schema:apply |
| `LynxCms\Core\Schema\SchemaSyncResult` | behind lynx:schema:apply |
| `LynxCms\Core\Search\Plurals\DutchPlurals` | built-in reducers; the reducer interface is the API |
| `LynxCms\Core\Search\Plurals\EnglishPlurals` | built-in reducers; the reducer interface is the API |
| `LynxCms\Core\Search\Plurals\GermanPlurals` | built-in reducers; the reducer interface is the API |
| `LynxCms\Core\Search\Plurals\ItalianPlurals` | built-in reducers; the reducer interface is the API |
| `LynxCms\Core\Search\Plurals\PortuguesePlurals` | built-in reducers; the reducer interface is the API |
| `LynxCms\Core\Search\Plurals\SpanishPlurals` | built-in reducers; the reducer interface is the API |
| `LynxCms\Core\Templates\Layout\LayoutBlock` | templates set their layout as arrays |
| `LynxCms\Core\Templates\Layout\LayoutSection` | templates set their layout as arrays |
| `LynxCms\Core\View\ComponentRenderer` | rendering behind Blade directives |
| `LynxCms\Core\View\FieldRenderer` | rendering behind Blade directives |

### finder

| Class | Why |
|---|---|
| `LynxCms\Finder\Catalog\ConditionCatalog` | Page Finder internals |
| `LynxCms\Finder\Catalog\Kind` | Page Finder internals |
| `LynxCms\Finder\Catalog\Operator` | Page Finder internals |
| `LynxCms\Finder\Catalog\Subject` | Page Finder internals |
| `LynxCms\Finder\FinderTables` | table names, used inside the package |
| `LynxCms\Finder\Models\SavedSearch` | Page Finder internals |
| `LynxCms\Finder\Search\Condition` | Page Finder internals |
| `LynxCms\Finder\Search\ConditionGroup` | Page Finder internals |
| `LynxCms\Finder\Search\FinderQuery` | Page Finder internals |
| `LynxCms\Finder\SelectorWriter` | Page Finder internals |

### forms

| Class | Why |
|---|---|
| `LynxCms\Forms\Builder\PageForm` | builder internals |
| `LynxCms\Forms\Definition\FormValidator` | form internals |
| `LynxCms\Forms\Fields\OptionsType` | registered field type; the type name is the API |
| `LynxCms\Forms\FormsServiceProvider` | service provider: Composer discovers it, apps never call it |
| `LynxCms\Forms\FormsTables` | table names, used inside the package |
| `LynxCms\Forms\SubmissionPresenter` | admin presentation |
| `LynxCms\Forms\View\FormTag` | Blade component class; the tag is the API |

### media

| Class | Why |
|---|---|
| `LynxCms\Media\MediaPaths` | media internals |
| `LynxCms\Media\View\ImageComponent` | Blade component class; the tag is the API |

### menus

| Class | Why |
|---|---|
| `LynxCms\Menus\MenuSchema` | the package's own schema |
| `LynxCms\Menus\MenusServiceProvider` | service provider: Composer discovers it, apps never call it |
| `LynxCms\Menus\Support\LinkRules` | menu internals |
| `LynxCms\Menus\View\IconRenderer` | rendering behind a Blade component |
| `LynxCms\Menus\View\MenuComponent` | Blade component class; the tag is the API |

### page-password

| Class | Why |
|---|---|
| `LynxCms\PagePassword\Fields\PasswordType` | registered field type; the type name is the API |
| `LynxCms\PagePassword\StoredPassword` | page password internals |

### seo

| Class | Why |
|---|---|
| `LynxCms\Seo\JsonLd\Mapping` | JSON-LD internals |
| `LynxCms\Seo\JsonLd\SchemaTypes` | JSON-LD internals |
| `LynxCms\Seo\SeoAuditor` | behind the SEO screen |
| `LynxCms\Seo\SeoResolver` | no use outside its package |
| `LynxCms\Seo\SeoServiceProvider` | service provider: Composer discovers it, apps never call it |
| `LynxCms\Seo\View\SeoComponent` | Blade component class; the tag is the API |

### sitemap

| Class | Why |
|---|---|
| `LynxCms\Sitemap\SitemapSchema` | the package's own schema |
| `LynxCms\Sitemap\SitemapServiceProvider` | service provider: Composer discovers it, apps never call it |

### static-site

| Class | Why |
|---|---|
| `LynxCms\StaticSite\Export\ExportReport` | behind lynx:static |
| `LynxCms\StaticSite\StaticSiteServiceProvider` | service provider: Composer discovers it, apps never call it |

## Decide (36)


### admin

| Class | Why |
|---|---|
| `LynxCms\Admin\Editor\BacklinkFilter` | used by menus: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Admin\Editor\ChoicesForm` | used by forms: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Admin\Editor\LinkFiles` | used by media: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Admin\Editor\TemplateChoices` | used by cache, forms, menus, seo, sitemap: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Admin\Fields\ShortValue` | used by finder: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Admin\Http\SpreadsheetCell` | used by finder, forms: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Admin\Tree\TreeMarkers` | used by page-password: an extension point to document, or internal to Lynx's own packages |

### core

| Class | Why |
|---|---|
| `LynxCms\Core\Activity\ActivityEvent` | used by admin: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Agents\AgentToolkit` | used by ai, mcp: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Agents\PageHistory` | used by ai, mcp: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Fields\ChoiceOptions` | used by admin, finder, forms: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Fields\Items\ItemProperty` | used by media: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Fields\Items\SubfieldGroups` | used by admin: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Fields\LinkRel` | used by admin, menus: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Http\PageAttributes` | used by cache: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Pages\NotFoundPage` | used by admin: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Pages\PageLoader` | reached only through a public signature: narrow the signature, or keep |
| `LynxCms\Core\Pages\PageName` | used by admin: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Pages\PageUrl` | used by admin, cache, static-site: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Pages\PageUrls` | used by cache, static-site: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Pages\PathResolver` | reached only through a public signature: narrow the signature, or keep |
| `LynxCms\Core\Pages\TemplateUsage` | used by admin: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Permissions\PermissionHolders` | used by admin, forms: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Permissions\Role` | used by admin: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Permissions\RoleGrant` | used by admin: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Query\Clause` | reached only through a public signature: narrow the signature, or keep |
| `LynxCms\Core\Query\OrGroupSet` | reached only through a public signature: narrow the signature, or keep |
| `LynxCms\Core\Query\PageFinder` | reached only through a public signature: narrow the signature, or keep |
| `LynxCms\Core\Query\PageScope` | reached only through a public signature: narrow the signature, or keep |
| `LynxCms\Core\Query\ParsedSelector` | reached only through a public signature: narrow the signature, or keep |
| `LynxCms\Core\Query\SelectorParser` | reached only through a public signature: narrow the signature, or keep |
| `LynxCms\Core\Templates\EditorSettings` | used by admin: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Templates\FamilyRules` | used by admin: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Templates\Layout\LayoutTab` | used by admin, seo: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\Templates\Layout\TemplateLayout` | used by admin, seo: an extension point to document, or internal to Lynx's own packages |
| `LynxCms\Core\View\HreflangBuilder` | used by seo: an extension point to document, or internal to Lynx's own packages |

## Keep (169)


### admin

| Class | Why |
|---|---|
| `LynxCms\Admin\Admin` | used in docs, guide_code, starters, translate |
| `LynxCms\Admin\AdminAccess` | used in docs, guide_code, starters, translate |
| `LynxCms\Admin\AdminAssets` | used in docs, translate |
| `LynxCms\Admin\AdminBranches` | used in docs, translate |
| `LynxCms\Admin\AdminServiceProvider` | used in starters, translate |
| `LynxCms\Admin\AdminSlots` | used in docs, translate |
| `LynxCms\Admin\Auth\UserInvited` | public by design |
| `LynxCms\Admin\Branding\SaveBranding` | used in docs, starters |
| `LynxCms\Admin\EditorLinks` | named in the docs |
| `LynxCms\Admin\EditorTabs` | used in guide_code, starters |
| `LynxCms\Admin\Editor\FieldInputs` | used in docs, guide_code |
| `LynxCms\Admin\Editor\FieldKinds` | used in docs |
| `LynxCms\Admin\Editor\FieldTypeChoices` | used in docs |
| `LynxCms\Admin\Editor\FormConverter` | extension point (interface) |
| `LynxCms\Admin\Editor\PreparesDisplay` | extension point (interface) |
| `LynxCms\Admin\Editor\SchemaOwners` | named in the docs |
| `LynxCms\Admin\Fields\IconType` | used in docs |
| `LynxCms\Admin\Home\HomeScreen` | used in docs |
| `LynxCms\Admin\Icons\IconSet` | used in docs |
| `LynxCms\Admin\Livewire\PageEditor` | used in translate |
| `LynxCms\Admin\Livewire\PageStrings` | named in the docs |
| `LynxCms\Admin\Navigation` | used in docs, guide_code, starters, translate |
| `LynxCms\Admin\Tree\TreeFilterRegistry` | used in docs, translate |
| `LynxCms\Admin\Tree\TreePresenter` | used in docs |
| `LynxCms\Admin\Users\LynxUsers` | extension point (interface) |

### contracts

| Class | Why |
|---|---|
| `LynxCms\Contracts\Actions\Action` | extension point (interface) |
| `LynxCms\Contracts\Actions\ActionHalted` | named in the docs |
| `LynxCms\Contracts\Actions\Actor` | used in docs, guide_code, starters, translate |
| `LynxCms\Contracts\Fields\Comparison` | used in guide_code |
| `LynxCms\Contracts\Fields\FieldType` | extension point (interface) |
| `LynxCms\Contracts\Fields\FormatContext` | in the signature of a public class (Formatter) |
| `LynxCms\Contracts\Fields\Formatter` | extension point (interface) |
| `LynxCms\Contracts\Translation\TranslationProvider` | used in translate |

### core

| Class | Why |
|---|---|
| `LynxCms\Core\Actions\Action` | used in guide_code, starters, translate |
| `LynxCms\Core\Actions\ActionRegistry` | used in starters, translate |
| `LynxCms\Core\Actions\Runner\ActionContext` | used in guide_code, starters |
| `LynxCms\Core\Actions\Runner\ActionResult` | used in translate |
| `LynxCms\Core\Actions\Runner\ActionRunner` | used in starters |
| `LynxCms\Core\Actions\Runner\Change` | in the signature of a public class (ChangeSet) |
| `LynxCms\Core\Actions\Runner\ChangeSet` | in the signature of a public class (ActionContext) |
| `LynxCms\Core\Actions\Runner\HookBuilder` | in the signature of a public class (Lynx) |
| `LynxCms\Core\Actions\Runner\HookRegistry` | in the signature of a public class (ActionRunner) |
| `LynxCms\Core\Actions\Runner\Input` | used in docs, guide_code, starters |
| `LynxCms\Core\Agents\AgentReadTool` | used in guide_code, starters |
| `LynxCms\Core\Agents\ReadToolRegistry` | used in guide_code, starters |
| `LynxCms\Core\Api\Concerns\TrashesPages` | public by design |
| `LynxCms\Core\Api\Concerns\VersionsPages` | public by design |
| `LynxCms\Core\Api\FieldsApi` | public by design |
| `LynxCms\Core\Api\LanguagesApi` | public by design |
| `LynxCms\Core\Api\PagesApi` | public by design |
| `LynxCms\Core\Api\TemplatesApi` | public by design |
| `LynxCms\Core\Components\ComponentRegistry` | in the signature of a public class (Lynx) |
| `LynxCms\Core\Components\RegisteredComponent` | in the signature of a public class (ComponentRegistry) |
| `LynxCms\Core\Content\PageFiles` | extension point (interface) |
| `LynxCms\Core\Doctor\Check` | used in docs |
| `LynxCms\Core\Doctor\DoctorChecks` | used in docs |
| `LynxCms\Core\Doctor\Finding` | used in docs |
| `LynxCms\Core\Doctor\Severity` | used in docs |
| `LynxCms\Core\Events\ContentChanged` | used in docs |
| `LynxCms\Core\Events\LanguageCreated` | public by design |
| `LynxCms\Core\Events\LanguageDeleted` | public by design |
| `LynxCms\Core\Events\LanguagePrefixChanged` | public by design |
| `LynxCms\Core\Events\LanguageUpdated` | public by design |
| `LynxCms\Core\Events\PageDeleted` | used in starters |
| `LynxCms\Core\Events\PageMoved` | public by design |
| `LynxCms\Core\Events\PageRestored` | used in starters |
| `LynxCms\Core\Events\PageSaved` | used in guide_code, starters |
| `LynxCms\Core\Events\PageSorted` | public by design |
| `LynxCms\Core\Events\PageStatusChanged` | public by design |
| `LynxCms\Core\Events\PageTemplateSwitched` | used in starters |
| `LynxCms\Core\Events\PageTrashed` | used in starters |
| `LynxCms\Core\Events\SiteStringsSaved` | public by design |
| `LynxCms\Core\Events\TemplateUpdated` | public by design |
| `LynxCms\Core\Facades\Lynx` | used in docs |
| `LynxCms\Core\Fields\CarriesFiles` | extension point (interface) |
| `LynxCms\Core\Fields\ChecksSettings` | extension point (interface) |
| `LynxCms\Core\Fields\Choices\Choice` | in the signature of a public class (ChoiceList) |
| `LynxCms\Core\Fields\Choices\ChoiceList` | in the signature of a public class (FieldsApi) |
| `LynxCms\Core\Fields\DescribesPhpType` | used in guide_code |
| `LynxCms\Core\Fields\FieldRegistry` | in the signature of a public class (ItemFieldType) |
| `LynxCms\Core\Fields\FieldTypeRegistry` | named in the docs |
| `LynxCms\Core\Fields\FormatterRegistry` | in the signature of a public class (Lynx) |
| `LynxCms\Core\Fields\HasDefaultValue` | extension point (interface) |
| `LynxCms\Core\Fields\HasTranslatableTexts` | extension point (interface) |
| `LynxCms\Core\Fields\HtmlCleaner` | used in translate |
| `LynxCms\Core\Fields\Items\HasItemProperties` | extension point (interface) |
| `LynxCms\Core\Fields\Items\ItemFieldType` | extension point (abstract class) |
| `LynxCms\Core\Fields\Items\NestsInItems` | extension point (interface) |
| `LynxCms\Core\Fields\Items\StoresItems` | extension point (interface) |
| `LynxCms\Core\Fields\Items\Subfield` | in the signature of a public class (ItemFieldType) |
| `LynxCms\Core\Fields\Items\SubfieldGroup` | in the signature of a public class (Subfield) |
| `LynxCms\Core\Fields\MergesInput` | extension point (interface) |
| `LynxCms\Core\Fields\NeedsSavedPage` | extension point (interface) |
| `LynxCms\Core\Fields\NormalizesSettings` | extension point (interface) |
| `LynxCms\Core\Fields\PresentsValue` | extension point (interface) |
| `LynxCms\Core\Fields\ProvidesSearchText` | extension point (interface) |
| `LynxCms\Core\Fields\ReferencesPages` | extension point (interface) |
| `LynxCms\Core\Fields\SettingFormats` | named in the docs |
| `LynxCms\Core\Fields\SettingsPerUse` | extension point (interface) |
| `LynxCms\Core\Fields\Types\BaseFieldType` | used in guide_code |
| `LynxCms\Core\Http\Middleware\WithoutPageVisibility` | public by design |
| `LynxCms\Core\Languages\LanguageContext` | used in starters |
| `LynxCms\Core\Languages\LanguageNotFound` | public by design |
| `LynxCms\Core\Languages\LanguageRegistry` | in the signature of a public class (LanguageContext) |
| `LynxCms\Core\Livewire\UsesLynxPage` | used in docs, starters |
| `LynxCms\Core\Lynx` | used in translate |
| `LynxCms\Core\Models\Field` | used in guide_code |
| `LynxCms\Core\Models\Language` | used in docs, starters, translate |
| `LynxCms\Core\Models\Template` | in the signature of a public class (AdminAccess) |
| `LynxCms\Core\Pages\FieldValue` | extension point (interface) |
| `LynxCms\Core\Pages\Items\Item` | public by design |
| `LynxCms\Core\Pages\Items\ItemList` | public by design |
| `LynxCms\Core\Pages\OldPath` | public by design |
| `LynxCms\Core\Pages\Page` | used in docs, guide_code, starters, translate |
| `LynxCms\Core\Pages\PageArray` | used in starters |
| `LynxCms\Core\Pages\PageGates` | used in guide_code, starters |
| `LynxCms\Core\Pages\PageStatus` | used in guide_code |
| `LynxCms\Core\Pages\Publication` | named in the docs |
| `LynxCms\Core\Pages\SharedValue` | public by design |
| `LynxCms\Core\Pages\TranslatableValue` | public by design |
| `LynxCms\Core\Pages\Visibility\PageVisibility` | used in guide_code, starters |
| `LynxCms\Core\Pages\Visibility\VariesByVisitor` | used in guide_code |
| `LynxCms\Core\Pages\Visibility\VisibilityRule` | used in guide_code |
| `LynxCms\Core\Permissions\Authorizer` | used in starters |
| `LynxCms\Core\Permissions\PermissionRegistry` | used in translate |
| `LynxCms\Core\Permissions\RoleAssignment` | named in the docs |
| `LynxCms\Core\Query\SelectorException` | public by design |
| `LynxCms\Core\Schema\SchemaEditing` | in the signature of a public class (SchemaOwners) |
| `LynxCms\Core\Schema\SchemaOwners` | used in docs |
| `LynxCms\Core\Search\Plurals\EndingRules` | extension point (abstract class) |
| `LynxCms\Core\Search\Plurals\FrenchPlurals` | used in guide_code |
| `LynxCms\Core\Search\Plurals\WordReducer` | used in guide_code |
| `LynxCms\Core\Search\Plurals\WordReducers` | used in guide_code |
| `LynxCms\Core\Strings\PageStrings` | used in translate |
| `LynxCms\Core\Strings\StringDefinition` | in the signature of a public class (PageStrings) |
| `LynxCms\Core\Support\LocalizedText` | in the signature of a public class (FormStep) |
| `LynxCms\Core\Support\Value` | used in docs, starters, translate |
| `LynxCms\Core\Templates\FieldCondition` | in the signature of a public class (TemplateField) |
| `LynxCms\Core\Templates\FieldDisplay` | in the signature of a public class (TemplateField) |
| `LynxCms\Core\Templates\TemplateField` | in the signature of a public class (Page) |
| `LynxCms\Core\Templates\TemplateRegistry` | in the signature of a public class (TemplatesApi) |
| `LynxCms\Core\Translation\TranslationProviders` | used in translate |
| `LynxCms\Core\View\LanguageSwitcher` | in the signature of a public class (LanguagesApi) |

### forms

| Class | Why |
|---|---|
| `LynxCms\Forms\Definition\Form` | used in docs, guide_code, starters |
| `LynxCms\Forms\Definition\FormField` | used in docs, guide_code, starters |
| `LynxCms\Forms\Definition\FormStep` | used in docs, guide_code, starters |
| `LynxCms\Forms\Events\FormSubmitted` | used in docs, guide_code, starters |
| `LynxCms\Forms\FormRegistry` | public by design |
| `LynxCms\Forms\FormSchema` | used in guide_code, starters |
| `LynxCms\Forms\Livewire\FormComponent` | used in docs, starters |
| `LynxCms\Forms\Models\Submission` | used in docs, guide_code, starters |
| `LynxCms\Forms\Models\SubmissionFile` | in the signature of a public class (Submission) |
| `LynxCms\Forms\Results\FormResults` | used in docs |

### media

| Class | Why |
|---|---|
| `LynxCms\Media\Values\MediaList` | public by design |
| `LynxCms\Media\Values\Pagefile` | used in starters |
| `LynxCms\Media\Values\Pageimage` | public by design |

### menus

| Class | Why |
|---|---|
| `LynxCms\Menus\Menus` | public by design |
| `LynxCms\Menus\Reading\Menu` | public by design |
| `LynxCms\Menus\Reading\MenuItem` | public by design |

### page-password

| Class | Why |
|---|---|
| `LynxCms\PagePassword\PagePasswordSchema` | used in starters |

### seo

| Class | Why |
|---|---|
| `LynxCms\Seo\JsonLd\JsonLd` | used in docs |
| `LynxCms\Seo\JsonLd\JsonLdGraph` | used in docs |
| `LynxCms\Seo\RobotsTxt` | used in docs |
| `LynxCms\Seo\SeoData` | in the signature of a public class (JsonLd) |
| `LynxCms\Seo\SeoSchema` | used in docs, guide_code, starters |

### sitemap

| Class | Why |
|---|---|
| `LynxCms\Sitemap\Sitemap` | used in docs |
| `LynxCms\Sitemap\SitemapFilters` | used in docs |
| `LynxCms\Sitemap\SitemapSettings` | in the signature of a public class (Sitemap) |

### static-site

| Class | Why |
|---|---|
| `LynxCms\StaticSite\StaticSiteBuilt` | public by design |
