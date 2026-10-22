# Lynx class complexity

Measured on main (8aba3a6), 2026-10-19, over packages/*/src. Interfaces left out.

- **Total**: cyclomatic complexity of the class, the sum over its methods (each method counts 1 plus its if, elseif, loops, case, catch, ternary, ??, &&, ||, match arms and ?-> calls; closures count toward their method).
- **Max**: the most complex method, named in **Worst method**.

## The 10 most complex classes

Across all 528 classes the median total is 10, and nine in ten score 30 or less.

| # | Class | Total | Max (worst method) | Methods | Lines |
| ---: | --- | ---: | --- | ---: | ---: |
| 1 | core `Strings\StringScanner` | 66 | 18 `literal()` | 8 | 204 |
| 2 | media `Fields\MediaFieldType` | 66 | 17 `normalize()` | 21 | 235 |
| 3 | core `Permissions\Authorizer` | 66 | 12 `allows()` | 14 | 217 |
| 4 | admin `Livewire\Concerns\EditsTemplateLayout` | 65 | 11 `layoutFieldList()` | 17 | 253 |
| 5 | core `Schema\SchemaSync` | 59 | 26 `applyLanguages()` | 10 | 243 |
| 6 | forms `Builder\PageFormCompiler` | 58 | 11 `typeSettings()` | 13 | 210 |
| 7 | core `Actions\Pages\SavePage` | 57 | 13 `authorize()` | 12 | 213 |
| 8 | core `Query\SelectorCompiler` | 55 | 21 `where()` | 6 | 211 |
| 9 | core `Pages\Saving\ValueWriter` | 54 | 10 `missingRequired()` | 10 | 201 |
| 10 | forms `Definition\FormField` | 52 | 14 `isShown()` | 33 | 264 |

## Reading it

- A high total spread over many small methods is fine: `FormField` reaches 52 over 33 methods.
- The candidates for splitting are single methods above 15: `SchemaSync::applyLanguages()` and `TranslatePage` (26 each), `SchemaReader` (22), `SelectorCompiler::where()` (21, the switch over the built in selector fields) and `StringScanner::literal()` (18).
- Half of the top 10 is on the save and schema path (`SavePage`, `ValueWriter`, `SchemaSync`, `Authorizer`, the selector compiler). Bugs there cost the most, so their branches deserve tests before any refactor.

## All classes

Sections are the package and its first folder under src, in alphabetical order; classes within a section from most to least complex.

## admin

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Appearance` | class | 15 | 7 | `fontsUrl()` | 4 | 103 |
| `AdminAccess` | class | 10 | 3 | `treeScope()` | 7 | 66 |
| `AdminBranches` | class | 7 | 5 | `editUrlFor()` | 3 | 35 |
| `AdminServiceProvider` | class | 7 | 3 | `boot()` | 4 | 96 |
| `Navigation` | class | 3 | 2 | `items()` | 2 | 30 |
| `EditorLinks` | class | 2 | 1 | `add()` | 2 | 21 |
| `Admin` | class | 1 | 1 | `routes()` | 1 | 13 |

## admin/Auth

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Auth\AdminAuth` | class | 10 | 3 | `configure()` | 5 | 93 |
| `Auth\InviteUser` | class | 10 | 4 | `handle()` | 7 | 63 |
| `Auth\TwoFactorState` | class | 10 | 4 | `user()` | 5 | 46 |
| `Auth\ResendInvitation` | class | 9 | 4 | `handle()` | 6 | 44 |
| `Auth\Invitations` | class | 7 | 3 | `configureBroker()` | 4 | 37 |
| `Auth\InvitationNotification` | class | 3 | 1 | `__construct()` | 3 | 26 |
| `Auth\ResetUserPassword` | class | 2 | 2 | `reset()` | 1 | 14 |
| `Auth\ClearInvitation` | class | 1 | 1 | `handle()` | 1 | 7 |
| `Auth\Invitation` | class | 1 | 1 | `casts()` | 1 | 13 |
| `Auth\SendInvitation` | class | 1 | 1 | `handle()` | 1 | 8 |
| `Auth\UserInvited` | class | 1 | 1 | `__construct()` | 1 | 9 |

## admin/Editor

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Editor\Converters\ItemsConverter` | class | 44 | 13 | `fromForm()` | 8 | 137 |
| `Editor\ItemHeaders` | class | 39 | 11 | `texts()` | 4 | 103 |
| `Editor\EditorForm` | class | 27 | 6 | `changes()` | 9 | 130 |
| `Editor\SchemaForm` | class | 26 | 14 | `cast()` | 3 | 81 |
| `Editor\LinkPages` | class | 19 | 7 | `anchors()` | 6 | 93 |
| `Editor\FieldViews` | class | 18 | 8 | `view()` | 5 | 85 |
| `Editor\FieldInputs` | class | 16 | 5 | `register()` | 6 | 69 |
| `Editor\Converters\PageConverter` | class | 15 | 6 | `toForm()` | 3 | 33 |
| `Editor\ActorNames` | class | 14 | 9 | `label()` | 2 | 29 |
| `Editor\Converters\DateConverter` | class | 13 | 4 | `toForm()` | 4 | 28 |
| `Editor\PageChoices` | class | 13 | 4 | `style()` | 5 | 69 |
| `Editor\TemplateChoices` | class | 13 | 6 | `for()` | 4 | 52 |
| `Editor\FieldUsage` | class | 12 | 6 | `all()` | 4 | 59 |
| `Editor\SchemaOwners` | class | 12 | 2 | `ownerOfTemplate()` | 8 | 54 |
| `Editor\ComponentSettings` | class | 11 | 6 | `choices()` | 4 | 51 |
| `Editor\Converters\ChoiceConverter` | class | 11 | 4 | `toForm()` | 4 | 24 |
| `Editor\LocalizedForm` | class | 11 | 6 | `fromForm()` | 3 | 63 |
| `Editor\ChangeReview` | class | 10 | 6 | `entries()` | 3 | 50 |
| `Editor\ChoicesForm` | class | 10 | 6 | `toForm()` | 2 | 34 |
| `Editor\FieldConditions` | class | 10 | 5 | `states()` | 3 | 43 |
| `Editor\Converters\IntegerConverter` | class | 9 | 7 | `fromForm()` | 2 | 21 |
| `Editor\Converters\ComponentConverter` | class | 8 | 4 | `fromForm()` | 3 | 19 |
| `Editor\LinkFiles` | class | 7 | 6 | `for()` | 2 | 34 |
| `Editor\Converters\DecimalConverter` | class | 6 | 3 | `toForm()` | 2 | 14 |
| `Editor\Converters\TextConverter` | class | 5 | 3 | `fromForm()` | 2 | 12 |
| `Editor\FormValues` | class | 5 | 2 | `toForm()` | 3 | 27 |
| `Editor\PageSearch` | class | 5 | 4 | `find()` | 2 | 35 |
| `Editor\BacklinkFilter` | class | 3 | 2 | `selector()` | 2 | 16 |
| `Editor\FieldView` | class | 3 | 2 | `field()` | 2 | 26 |
| `Editor\LanguageValue` | class | 3 | 2 | `isEmpty()` | 2 | 13 |
| `Editor\Converters\ToggleConverter` | class | 2 | 1 | `toForm()` | 2 | 12 |
| `Editor\ReviewEntry` | class | 1 | 1 | `__construct()` | 1 | 11 |

## admin/Http

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Http\AssetController` | class | 7 | 2 | `scriptVersion()` | 5 | 42 |
| `Http\InvitationController` | class | 7 | 5 | `store()` | 2 | 47 |
| `Http\EditLanguageController` | class | 4 | 3 | `edit()` | 2 | 18 |
| `Http\CreatePageController` | class | 3 | 3 | `__invoke()` | 1 | 10 |
| `Http\EditFieldController` | class | 3 | 3 | `__invoke()` | 1 | 10 |
| `Http\EditPageController` | class | 3 | 3 | `__invoke()` | 1 | 21 |
| `Http\EditTemplateController` | class | 3 | 3 | `__invoke()` | 1 | 10 |
| `Http\EditRoleController` | class | 2 | 2 | `__invoke()` | 1 | 10 |
| `Http\SuperuserScreenController` | class | 2 | 1 | `roles()` | 2 | 16 |

## admin/Livewire

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Livewire\Concerns\EditsTemplateLayout` | trait | 65 | 11 | `layoutFieldList()` | 17 | 253 |
| `Livewire\PageSettings` | class | 44 | 8 | `previewUrls()` | 16 | 204 |
| `Livewire\LinkDialog` | class | 43 | 13 | `render()` | 16 | 203 |
| `Livewire\FieldForm` | class | 41 | 9 | `mount()` | 16 | 213 |
| `Livewire\TemplateForm` | class | 40 | 11 | `settingsOf()` | 13 | 195 |
| `Livewire\PageSchedule` | class | 35 | 7 | `load()` | 11 | 164 |
| `Livewire\PageStrings` | class | 32 | 14 | `save()` | 9 | 143 |
| `Livewire\PageTableInput` | class | 32 | 7 | `mount()` | 7 | 124 |
| `Livewire\Concerns\PageQuickActions` | trait | 31 | 7 | `confirmDelete()` | 13 | 135 |
| `Livewire\PageTree` | class | 30 | 8 | `mount()` | 14 | 155 |
| `Livewire\PageEditor` | class | 28 | 5 | `render()` | 13 | 208 |
| `Livewire\RoleForm` | class | 27 | 8 | `grantsInput()` | 9 | 130 |
| `Livewire\PageCreator` | class | 26 | 7 | `visibleFields()` | 9 | 121 |
| `Livewire\Concerns\ManagesItems` | trait | 25 | 6 | `addItem()` | 11 | 114 |
| `Livewire\LanguageForm` | class | 25 | 7 | `mount()` | 10 | 155 |
| `Livewire\Concerns\MovesPages` | trait | 22 | 3 | `indent()` | 14 | 108 |
| `Livewire\PagePicker` | class | 18 | 4 | `add()` | 9 | 115 |
| `Livewire\PageHistory` | class | 12 | 3 | `render()` | 7 | 78 |
| `Livewire\AdminSearch` | class | 11 | 3 | `selector()` | 5 | 72 |
| `Livewire\Concerns\ManagesTwoFactor` | trait | 11 | 4 | `mountManagesTwoFactor()` | 6 | 65 |
| `Livewire\PageRevisions` | class | 11 | 4 | `render()` | 7 | 69 |
| `Livewire\Concerns\ManagesPasskeys` | trait | 10 | 3 | `deletePasskey()` | 4 | 36 |
| `Livewire\Concerns\HoldsEditLock` | trait | 9 | 3 | `takeEditLock()` | 4 | 57 |
| `Livewire\UserRoles` | class | 9 | 4 | `loadRoles()` | 4 | 64 |
| `Livewire\AccountSecurity` | class | 8 | 7 | `render()` | 2 | 32 |
| `Livewire\Concerns\ScopedToRoot` | trait | 7 | 4 | `movablePage()` | 3 | 22 |
| `Livewire\Concerns\ManagesTemplateFamily` | trait | 6 | 2 | `confirmDelete()` | 5 | 70 |
| `Livewire\Concerns\ReviewsChanges` | trait | 6 | 2 | `review()` | 4 | 43 |
| `Livewire\FieldList` | class | 6 | 5 | `render()` | 2 | 43 |
| `Livewire\TemplateList` | class | 6 | 4 | `render()` | 3 | 42 |
| `Livewire\Concerns\EditsDraft` | trait | 5 | 2 | `saveDraft()` | 3 | 41 |
| `Livewire\Concerns\InvitesUsers` | trait | 4 | 2 | `sendInvitation()` | 3 | 43 |
| `Livewire\Concerns\Notifies` | trait | 4 | 3 | `exceptionNotifies()` | 2 | 20 |
| `Livewire\LanguageList` | class | 4 | 3 | `render()` | 2 | 25 |
| `Livewire\RoleList` | class | 3 | 2 | `render()` | 2 | 27 |
| `Livewire\Concerns\ChangesPassword` | trait | 2 | 2 | `updatePassword()` | 1 | 30 |
| `Livewire\Concerns\RequiresSuperuser` | trait | 2 | 1 | `currentUserIsSuperuser()` | 2 | 13 |

## admin/Tree

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Tree\TreeNode` | class | 32 | 5 | `canChangeStatus()` | 18 | 126 |
| `Tree\TreeLoader` | class | 19 | 5 | `isComplete()` | 7 | 91 |
| `Tree\TreeMover` | class | 14 | 4 | `drop()` | 6 | 75 |
| `Tree\TreeLevel` | class | 10 | 6 | `pagerLinks()` | 5 | 61 |
| `Tree\DefaultPresenter` | class | 4 | 3 | `title()` | 2 | 14 |
| `Tree\TreeUi` | class | 3 | 2 | `icon()` | 2 | 32 |
| `Tree\TreeBranch` | class | 2 | 1 | `__construct()` | 2 | 9 |

## admin/Users

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Users\EloquentUsers` | class | 9 | 3 | `create()` | 4 | 53 |

## admin/View

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `View\TailwindMergeMacros` | class | 7 | 4 | `register()` | 2 | 52 |

## ai

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `AgentRun` | class | 22 | 10 | `remap()` | 7 | 105 |
| `LynxAi` | class | 12 | 8 | `ask()` | 3 | 59 |
| `AiServiceProvider` | class | 4 | 2 | `register()` | 2 | 18 |
| `AgentSession` | class | 3 | 1 | `__construct()` | 3 | 32 |

## ai/Agents

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Agents\CmsAgent` | class | 3 | 1 | `__construct()` | 3 | 34 |

## ai/Console

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Console\AskCommand` | class | 8 | 6 | `handle()` | 2 | 52 |

## ai/Tools

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Tools\ReadTool` | class | 16 | 10 | `all()` | 6 | 72 |
| `Tools\ActionTool` | class | 8 | 4 | `handle()` | 5 | 45 |

## ai/Translation

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Translation\LaravelAiTranslationProvider` | class | 8 | 7 | `translate()` | 2 | 34 |
| `Translation\TranslatorAgent` | class | 3 | 1 | `__construct()` | 3 | 27 |

## contracts/Actions

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Actions\Actor` | class | 6 | 1 | `__construct()` | 6 | 52 |
| `Actions\ActionHalted` | class | 0 | 0 |  | 0 | 1 |

## contracts/Fields

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Fields\FormatContext` | class | 1 | 1 | `__construct()` | 1 | 9 |
| `Fields\Comparison` | enum | 0 | 0 |  | 0 | 9 |

## core

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `LynxServiceProvider` | class | 20 | 8 | `registerGate()` | 5 | 157 |
| `Lynx` | class | 16 | 5 | `__get()` | 12 | 97 |
| `CoreRegistrations` | class | 0 | 0 |  | 0 | 85 |

## core/Actions

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Actions\Pages\SavePage` | class | 57 | 13 | `authorize()` | 12 | 213 |
| `Actions\Content\ImportContent` | class | 51 | 10 | `create()` | 13 | 223 |
| `Actions\Pages\SchedulePage` | class | 37 | 13 | `times()` | 12 | 164 |
| `Actions\Roles\SaveRole` | class | 33 | 10 | `assertGrant()` | 10 | 129 |
| `Actions\Pages\TranslatePage` | class | 32 | 26 | `handle()` | 7 | 142 |
| `Actions\Runner\ActionRunner` | class | 28 | 15 | `run()` | 9 | 181 |
| `Actions\Languages\UpdateLanguage` | class | 26 | 12 | `handle()` | 8 | 129 |
| `Actions\Pages\RestorePage` | class | 24 | 6 | `handle()` | 11 | 118 |
| `Actions\Fields\CleanHtmlValues` | class | 22 | 5 | `clean()` | 11 | 118 |
| `Actions\Strings\SaveStrings` | class | 20 | 8 | `handle()` | 8 | 112 |
| `Actions\Templates\UpdateTemplate` | class | 20 | 14 | `handle()` | 7 | 126 |
| `Actions\Fields\CreateField` | class | 19 | 9 | `handle()` | 7 | 105 |
| `Actions\Languages\CreateLanguage` | class | 17 | 9 | `handle()` | 8 | 100 |
| `Actions\Pages\MovePage` | class | 17 | 6 | `assertCanMoveInto()` | 9 | 101 |
| `Actions\Pages\SwitchTemplate` | class | 17 | 8 | `handle()` | 8 | 100 |
| `Actions\Pages\TrashPage` | class | 17 | 5 | `assertTrashable()` | 10 | 99 |
| `Actions\Templates\CreateTemplate` | class | 17 | 11 | `handle()` | 7 | 108 |
| `Actions\Pages\PurgeOrphanedValues` | class | 16 | 9 | `handle()` | 8 | 85 |
| `Actions\Pages\SetPageStatus` | class | 16 | 10 | `handle()` | 7 | 96 |
| `Actions\Pages\EmptyTrash` | class | 15 | 4 | `handle()` | 10 | 93 |
| `Actions\InstallLynx` | class | 14 | 5 | `handle()` | 8 | 74 |
| `Actions\Languages\SetCurrentLanguage` | class | 13 | 5 | `handle()` | 9 | 65 |
| `Actions\Pages\RunSchedules` | class | 13 | 4 | `apply()` | 8 | 68 |
| `Actions\Runner\Input` | class | 13 | 1 | `__construct()` | 13 | 76 |
| `Actions\Pages\DeletePage` | class | 12 | 5 | `handle()` | 8 | 81 |
| `Actions\Runner\HookRegistry` | class | 12 | 5 | `runBefore()` | 5 | 54 |
| `Actions\Fields\DeleteField` | class | 11 | 3 | `handle()` | 9 | 80 |
| `Actions\Pages\LockEdit` | class | 11 | 3 | `handle()` | 9 | 61 |
| `Actions\Pages\RestoreRevision` | class | 11 | 5 | `handle()` | 7 | 55 |
| `Actions\Pages\UnlockEdit` | class | 11 | 4 | `handle()` | 8 | 55 |
| `Actions\Runner\ChangeSet` | class | 11 | 2 | `summary()` | 10 | 81 |
| `Actions\Languages\DeleteLanguage` | class | 10 | 3 | `handle()` | 8 | 72 |
| `Actions\Pages\DeleteRedirect` | class | 10 | 4 | `handle()` | 7 | 66 |
| `Actions\Pages\PublishDraft` | class | 10 | 4 | `handle()` | 7 | 57 |
| `Actions\Fields\UpdateField` | class | 9 | 3 | `handle()` | 7 | 61 |
| `Actions\Pages\DiscardDraft` | class | 9 | 2 | `handle()` | 8 | 48 |
| `Actions\Pages\SaveDraft` | class | 9 | 3 | `handle()` | 7 | 48 |
| `Actions\Roles\AssignRoles` | class | 9 | 4 | `handle()` | 6 | 56 |
| `Actions\Search\RebuildSearchIndex` | class | 9 | 3 | `handle()` | 7 | 45 |
| `Actions\Templates\DeleteTemplate` | class | 9 | 2 | `handle()` | 8 | 50 |
| `Actions\ActionRegistry` | class | 8 | 3 | `register()` | 4 | 48 |
| `Actions\Pages\SortPage` | class | 8 | 2 | `handle()` | 7 | 56 |
| `Actions\Roles\DeleteRole` | class | 8 | 2 | `handle()` | 7 | 45 |
| `Actions\Runner\ActionContext` | class | 8 | 2 | `authorize()` | 6 | 61 |
| `Actions\Runner\ActionResult` | class | 8 | 2 | `valueAs()` | 5 | 60 |
| `Actions\Action` | class | 5 | 1 | `permission()` | 5 | 34 |
| `Actions\Roles\LastSuperuser` | class | 3 | 3 | `assertKept()` | 1 | 17 |
| `Actions\Runner\HookBuilder` | class | 3 | 1 | `__construct()` | 3 | 21 |
| `Actions\Runner\Change` | class | 2 | 1 | `__construct()` | 2 | 37 |

## core/Activity

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Activity\ActivityRecorder` | class | 11 | 11 | `__invoke()` | 1 | 28 |
| `Activity\ActivityEvent` | class | 1 | 1 | `casts()` | 1 | 19 |

## core/Agents

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Agents\AgentToolkit` | class | 48 | 16 | `guardLanguages()` | 12 | 245 |
| `Agents\ReadToolRegistry` | class | 8 | 4 | `register()` | 3 | 46 |
| `Agents\PageHistory` | class | 7 | 2 | `for()` | 4 | 62 |
| `Agents\AgentReadTool` | class | 5 | 1 | `name()` | 5 | 26 |

## core/Api

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Api\PagesApi` | class | 33 | 6 | `get()` | 16 | 191 |
| `Api\Concerns\VersionsPages` | trait | 24 | 7 | `schedule()` | 11 | 114 |
| `Api\LanguagesApi` | class | 17 | 2 | `chain()` | 15 | 122 |
| `Api\Concerns\ManagesPageHistory` | trait | 13 | 6 | `redirects()` | 4 | 65 |
| `Api\FieldsApi` | class | 12 | 2 | `add()` | 9 | 79 |
| `Api\TemplatesApi` | class | 10 | 2 | `name()` | 9 | 79 |
| `Api\Concerns\TrashesPages` | trait | 7 | 3 | `emptyTrash()` | 4 | 41 |

## core/Components

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Components\RegisteredComponent` | class | 5 | 2 | `settingsSchema()` | 3 | 32 |
| `Components\ComponentRegistry` | class | 4 | 2 | `find()` | 3 | 31 |

## core/Console

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Console\ImportCommand` | class | 12 | 12 | `handle()` | 1 | 45 |
| `Console\InstallCommand` | class | 8 | 8 | `handle()` | 1 | 32 |
| `Console\SchemaApplyCommand` | class | 8 | 8 | `handle()` | 1 | 38 |
| `Console\FindCommand` | class | 7 | 4 | `title()` | 2 | 47 |
| `Console\ScanStringsCommand` | class | 6 | 6 | `handle()` | 1 | 41 |
| `Console\BackupCommand` | class | 5 | 3 | `prune()` | 2 | 35 |
| `Console\BenchCommand` | class | 5 | 5 | `handle()` | 1 | 41 |
| `Console\CleanHtmlCommand` | class | 5 | 5 | `handle()` | 1 | 29 |
| `Console\LanguagesCommand` | class | 4 | 4 | `handle()` | 1 | 22 |
| `Console\ScheduleRunCommand` | class | 4 | 4 | `handle()` | 1 | 19 |
| `Console\ExportCommand` | class | 3 | 3 | `handle()` | 1 | 26 |
| `Console\PhpStanStubCommand` | class | 2 | 2 | `handle()` | 1 | 18 |
| `Console\SchemaExportCommand` | class | 2 | 2 | `handle()` | 1 | 17 |
| `Console\SearchIndexCommand` | class | 2 | 2 | `handle()` | 1 | 18 |
| `Console\DemoCommand` | class | 1 | 1 | `handle()` | 1 | 17 |

## core/Content

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Content\PackageFiles` | class | 27 | 5 | `export()` | 8 | 99 |
| `Content\ContentExporter` | class | 26 | 9 | `keys()` | 5 | 133 |
| `Content\ContentPackage` | class | 13 | 7 | `open()` | 4 | 42 |
| `Content\PackageReferences` | class | 11 | 5 | `resolve()` | 5 | 56 |
| `Content\PackageArchive` | class | 5 | 3 | `zip()` | 2 | 33 |

## core/Demo

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Demo\DemoSite` | class | 30 | 16 | `build()` | 7 | 160 |

## core/Events

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Events\LanguageCreated` | class | 2 | 1 | `__construct()` | 2 | 10 |
| `Events\LanguageDeleted` | class | 2 | 1 | `__construct()` | 2 | 10 |
| `Events\LanguagePrefixChanged` | class | 2 | 1 | `__construct()` | 2 | 10 |
| `Events\LanguageUpdated` | class | 2 | 1 | `__construct()` | 2 | 10 |
| `Events\PageDeleted` | class | 2 | 1 | `__construct()` | 2 | 12 |
| `Events\PageMoved` | class | 2 | 1 | `__construct()` | 2 | 9 |
| `Events\PageRestored` | class | 2 | 1 | `__construct()` | 2 | 9 |
| `Events\PageSaved` | class | 2 | 1 | `__construct()` | 2 | 9 |
| `Events\PageSorted` | class | 2 | 1 | `__construct()` | 2 | 9 |
| `Events\PageStatusChanged` | class | 2 | 1 | `__construct()` | 2 | 13 |
| `Events\PageTemplateSwitched` | class | 2 | 1 | `__construct()` | 2 | 9 |
| `Events\PageTrashed` | class | 2 | 1 | `__construct()` | 2 | 12 |
| `Events\SiteStringsSaved` | class | 2 | 1 | `__construct()` | 2 | 12 |
| `Events\TemplateUpdated` | class | 2 | 1 | `__construct()` | 2 | 10 |

## core/Facades

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Facades\Lynx` | class | 1 | 1 | `getFacadeAccessor()` | 1 | 7 |

## core/Fields

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Fields\Items\ItemFieldType` | class | 51 | 9 | `referenceValues()` | 26 | 243 |
| `Fields\Items\ItemNormalizer` | class | 51 | 13 | `normalize()` | 9 | 168 |
| `Fields\Types\ChoiceType` | class | 33 | 7 | `normalize()` | 15 | 121 |
| `Fields\Types\MatrixType` | class | 28 | 5 | `allSubfields()` | 11 | 109 |
| `Fields\Items\ItemMerger` | class | 25 | 16 | `merge()` | 3 | 60 |
| `Fields\Types\ComponentType` | class | 25 | 10 | `normalize()` | 13 | 76 |
| `Fields\Types\UrlType` | class | 25 | 6 | `rules()` | 11 | 76 |
| `Fields\Items\ItemRows` | class | 24 | 11 | `sleep()` | 4 | 88 |
| `Fields\Types\Concerns\StoresPageReferences` | trait | 24 | 8 | `normalize()` | 12 | 91 |
| `Fields\Items\ItemTranslations` | class | 22 | 15 | `collect()` | 2 | 69 |
| `Fields\Types\TextareaType` | class | 22 | 9 | `normalize()` | 12 | 86 |
| `Fields\Types\TimeType` | class | 21 | 9 | `normalize()` | 10 | 74 |
| `Fields\Types\DateType` | class | 20 | 8 | `normalize()` | 10 | 67 |
| `Fields\ChoiceOptions` | class | 19 | 10 | `normalize()` | 3 | 77 |
| `Fields\Types\DecimalType` | class | 19 | 6 | `normalize()` | 10 | 71 |
| `Fields\Items\Subfield` | class | 18 | 3 | `labels()` | 10 | 59 |
| `Fields\Types\IntegerType` | class | 17 | 4 | `normalize()` | 10 | 59 |
| `Fields\FormatterChain` | class | 16 | 5 | `render()` | 5 | 51 |
| `Fields\Types\BaseFieldType` | class | 15 | 3 | `isEmpty()` | 12 | 62 |
| `Fields\FieldRegistry` | class | 14 | 5 | `find()` | 6 | 60 |
| `Fields\FormatterRegistry` | class | 14 | 4 | `get()` | 6 | 73 |
| `Fields\Formatters\SmartyPants` | class | 12 | 5 | `format()` | 5 | 56 |
| `Fields\Types\TextType` | class | 12 | 3 | `normalize()` | 9 | 57 |
| `Fields\Types\ToggleType` | class | 12 | 3 | `wake()` | 9 | 47 |
| `Fields\Items\ItemFiles` | class | 11 | 8 | `walk()` | 4 | 60 |
| `Fields\FieldSettings` | class | 10 | 5 | `validate()` | 2 | 51 |
| `Fields\FieldTypeRegistry` | class | 10 | 4 | `get()` | 5 | 59 |
| `Fields\Formatters\HeadingAnchors` | class | 10 | 3 | `anchors()` | 5 | 63 |
| `Fields\Types\PageTableType` | class | 10 | 4 | `allowedTemplates()` | 6 | 61 |
| `Fields\Choices\ChoiceList` | class | 9 | 2 | `first()` | 8 | 51 |
| `Fields\FieldSchemaManager` | class | 8 | 6 | `create()` | 3 | 53 |
| `Fields\LinkRelSanitizer` | class | 8 | 5 | `withNoopener()` | 4 | 36 |
| `Fields\Types\EmailType` | class | 8 | 3 | `normalize()` | 6 | 45 |
| `Fields\Items\ItemSettingsRules` | class | 7 | 4 | `subfieldExists()` | 4 | 65 |
| `Fields\Types\RepeaterType` | class | 7 | 2 | `subfields()` | 6 | 34 |
| `Fields\Formatters\ExternalLinks` | class | 6 | 4 | `format()` | 3 | 28 |
| `Fields\HtmlCleaner` | class | 6 | 3 | `clean()` | 2 | 44 |
| `Fields\LinkRel` | class | 6 | 3 | `chosen()` | 2 | 33 |
| `Fields\PageStub` | class | 6 | 3 | `render()` | 2 | 55 |
| `Fields\Formatters\Paragraphs` | class | 5 | 2 | `format()` | 4 | 25 |
| `Fields\Types\PageReferenceType` | class | 5 | 1 | `name()` | 5 | 36 |
| `Fields\Formatters\Markdown` | class | 4 | 1 | `name()` | 4 | 22 |
| `Fields\Choices\Choice` | class | 3 | 1 | `__construct()` | 3 | 15 |
| `Fields\Formatters\TextFormatter` | class | 2 | 1 | `appliesTo()` | 2 | 12 |
| `Fields\Types\Concerns\ProvidesPlainSearchText` | trait | 2 | 2 | `searchText()` | 1 | 10 |
| `Fields\Items\ItemProperty` | class | 1 | 1 | `__construct()` | 1 | 8 |
| `Fields\Items\ItemTable` | class | 1 | 1 | `define()` | 1 | 18 |
| `Fields\Types\Concerns\PresentsSharedText` | trait | 1 | 1 | `present()` | 1 | 7 |

## core/Http

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Http\Controllers\PageController` | class | 44 | 12 | `__invoke()` | 8 | 138 |
| `Http\Middleware\DetectLocale` | class | 16 | 9 | `unprefixedLanguage()` | 3 | 59 |
| `Http\Middleware\RedirectDefaultLocale` | class | 10 | 9 | `handle()` | 2 | 27 |
| `Http\Middleware\RememberLocale` | class | 5 | 4 | `handle()` | 2 | 18 |
| `Http\Middleware\MarkPageRoute` | class | 1 | 1 | `handle()` | 1 | 12 |

## core/Languages

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Languages\LanguageRegistry` | class | 23 | 6 | `find()` | 10 | 109 |
| `Languages\LanguageContext` | class | 8 | 2 | `current()` | 7 | 65 |
| `Languages\LanguageNotFound` | class | 2 | 2 | `for()` | 1 | 7 |
| `Languages\TranslationLocales` | class | 2 | 2 | `expand()` | 1 | 19 |

## core/Livewire

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Livewire\UsesLynxPage` | trait | 8 | 3 | `restoreLynxLanguage()` | 4 | 37 |

## core/Models

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Models\Template` | class | 19 | 2 | `labels()` | 11 | 100 |
| `Models\Field` | class | 16 | 2 | `labels()` | 11 | 91 |
| `Models\Language` | class | 10 | 3 | `appLocale()` | 6 | 60 |
| `Models\Setting` | class | 3 | 2 | `read()` | 2 | 30 |

## core/Pages

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Pages\Saving\ValueWriter` | class | 54 | 10 | `missingRequired()` | 10 | 201 |
| `Pages\Page` | class | 49 | 9 | `__get()` | 17 | 226 |
| `Pages\PageLoader` | class | 39 | 10 | `hydrate()` | 10 | 186 |
| `Pages\Saving\PageInput` | class | 38 | 11 | `applyValues()` | 8 | 123 |
| `Pages\PathBuilder` | class | 26 | 11 | `rebuildOne()` | 4 | 110 |
| `Pages\Trash` | class | 24 | 4 | `ensureRoot()` | 11 | 149 |
| `Pages\Concerns\HasNamesAndUrls` | trait | 23 | 4 | `name()` | 9 | 81 |
| `Pages\PageGuard` | class | 23 | 5 | `assertDeletable()` | 12 | 121 |
| `Pages\Items\Item` | class | 21 | 8 | `get()` | 11 | 86 |
| `Pages\TranslatableValue` | class | 21 | 4 | `resolvedLanguage()` | 13 | 94 |
| `Pages\Saving\NameWriter` | class | 20 | 9 | `prepare()` | 6 | 91 |
| `Pages\Revisions` | class | 19 | 6 | `saveInput()` | 7 | 131 |
| `Pages\SiblingOrder` | class | 19 | 4 | `write()` | 11 | 130 |
| `Pages\PageArray` | class | 18 | 4 | `total()` | 11 | 84 |
| `Pages\Concerns\SerializesPage` | trait | 16 | 9 | `toArray()` | 3 | 72 |
| `Pages\PathResolver` | class | 16 | 4 | `history()` | 7 | 112 |
| `Pages\Items\ItemList` | class | 15 | 8 | `__construct()` | 7 | 68 |
| `Pages\UniqueNames` | class | 15 | 5 | `assertAllowed()` | 6 | 67 |
| `Pages\Concerns\TracksChanges` | trait | 14 | 4 | `isDirty()` | 10 | 107 |
| `Pages\PageReferences` | class | 13 | 5 | `resolve()` | 5 | 67 |
| `Pages\Saving\PageRowWriter` | class | 13 | 5 | `write()` | 4 | 61 |
| `Pages\Drafts` | class | 12 | 3 | `create()` | 7 | 77 |
| `Pages\EditLocks` | class | 12 | 4 | `assertFree()` | 5 | 61 |
| `Pages\SharedValue` | class | 8 | 2 | `isEmpty()` | 7 | 34 |
| `Pages\RootPage` | class | 7 | 4 | `ensure()` | 3 | 33 |
| `Pages\SettingsPages` | class | 7 | 5 | `get()` | 3 | 26 |
| `Pages\BranchMover` | class | 6 | 4 | `relink()` | 3 | 48 |
| `Pages\Concerns\ResolvesPages` | trait | 6 | 6 | `resolvePageReference()` | 1 | 14 |
| `Pages\PageUrl` | class | 5 | 3 | `of()` | 2 | 21 |
| `Pages\PageAncestry` | class | 4 | 2 | `idsOf()` | 3 | 26 |
| `Pages\PageStatus` | class | 4 | 3 | `fromNames()` | 2 | 43 |
| `Pages\ScheduleReader` | class | 4 | 3 | `of()` | 2 | 24 |
| `Pages\UnknownFields` | class | 4 | 4 | `report()` | 1 | 25 |
| `Pages\UnpublishedBranches` | class | 3 | 2 | `ids()` | 2 | 23 |
| `Pages\OldPath` | class | 2 | 1 | `__construct()` | 2 | 14 |
| `Pages\Publication` | class | 2 | 2 | `isPublished()` | 1 | 16 |
| `Pages\PageName` | class | 1 | 1 | `from()` | 1 | 12 |

## core/Permissions

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Permissions\Authorizer` | class | 66 | 12 | `allows()` | 14 | 217 |
| `Permissions\PermissionRegistry` | class | 11 | 2 | `__construct()` | 8 | 90 |
| `Permissions\ScopeRules` | class | 11 | 9 | `scope()` | 3 | 40 |
| `Permissions\Role` | class | 4 | 1 | `grants()` | 4 | 35 |
| `Permissions\PermissionHolders` | class | 1 | 1 | `userIds()` | 1 | 15 |
| `Permissions\RoleGrant` | class | 1 | 1 | `casts()` | 1 | 16 |
| `Permissions\RoleAssignment` | class | 0 | 0 |  | 0 | 8 |

## core/Query

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Query\SelectorCompiler` | class | 55 | 21 | `where()` | 6 | 211 |
| `Query\PageFinder` | class | 27 | 8 | `find()` | 9 | 124 |
| `Query\SubfieldCompiler` | class | 26 | 6 | `apply()` | 8 | 134 |
| `Query\ItemPropertyCompiler` | class | 25 | 9 | `matchElement()` | 7 | 129 |
| `Query\ClauseComparison` | class | 24 | 17 | `apply()` | 3 | 77 |
| `Query\JsonSql` | class | 21 | 7 | `elementValue()` | 7 | 108 |
| `Query\SelectorParser` | class | 20 | 7 | `values()` | 4 | 101 |
| `Query\PageScope` | class | 12 | 4 | `apply()` | 6 | 90 |
| `Query\ValueJoins` | class | 9 | 4 | `coalesce()` | 5 | 61 |
| `Query\Backlinks` | class | 5 | 4 | `pageIds()` | 2 | 39 |
| `Query\Clause` | class | 3 | 2 | `value()` | 2 | 18 |
| `Query\CompiledSelector` | class | 2 | 1 | `__construct()` | 2 | 14 |
| `Query\Sql` | class | 2 | 1 | `trusted()` | 2 | 16 |
| `Query\SelectorException` | class | 0 | 0 |  | 0 | 1 |

## core/Schema

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Schema\SchemaSync` | class | 59 | 26 | `applyLanguages()` | 10 | 243 |
| `Schema\RoleSync` | class | 31 | 9 | `saves()` | 7 | 126 |
| `Schema\SchemaReader` | class | 23 | 22 | `read()` | 2 | 47 |
| `Schema\SchemaExporter` | class | 13 | 5 | `export()` | 6 | 105 |
| `Schema\LocalizedSchemaText` | class | 10 | 5 | `renameLanguage()` | 2 | 41 |
| `Schema\SchemaSyncResult` | class | 4 | 3 | `isEmpty()` | 2 | 23 |

## core/Search

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Search\SearchIndex` | class | 17 | 4 | `index()` | 9 | 108 |
| `Search\ItemSearchText` | class | 15 | 10 | `of()` | 3 | 53 |
| `Search\Engines\SearchEngine` | class | 12 | 3 | `replace()` | 9 | 66 |
| `Search\SearchText` | class | 11 | 5 | `of()` | 3 | 41 |
| `Search\UpdateSearchIndex` | class | 7 | 6 | `handle()` | 2 | 19 |
| `Search\Engines\SqliteSearchEngine` | class | 5 | 1 | `whereMatches()` | 5 | 34 |
| `Search\SearchServiceProvider` | class | 5 | 3 | `register()` | 2 | 25 |
| `Search\Engines\LikeSearchEngine` | class | 2 | 2 | `whereMatches()` | 1 | 13 |
| `Search\Engines\PostgresSearchEngine` | class | 1 | 1 | `whereMatches()` | 1 | 11 |

## core/Strings

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Strings\StringScanner` | class | 66 | 18 | `literal()` | 8 | 204 |
| `Strings\StringCatalog` | class | 41 | 7 | `conflicts()` | 11 | 187 |
| `Strings\PageStringEdits` | class | 30 | 7 | `write()` | 8 | 143 |
| `Strings\StringDefinition` | class | 16 | 8 | `fromArray()` | 7 | 76 |
| `Strings\PageStrings` | class | 14 | 7 | `edits()` | 4 | 73 |
| `Strings\StringRenderer` | class | 12 | 7 | `text()` | 4 | 44 |
| `Strings\StringStore` | class | 4 | 3 | `forPage()` | 2 | 26 |
| `Strings\ScanReport` | class | 0 | 0 |  | 0 | 11 |

## core/Support

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Support\LocalizedText` | class | 41 | 10 | `from()` | 12 | 157 |
| `Support\Value` | class | 28 | 6 | `string()` | 10 | 91 |
| `Support\Tables` | class | 2 | 1 | `field()` | 2 | 73 |

## core/Templates

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Templates\Layout\TemplateLayout` | class | 38 | 11 | `place()` | 12 | 157 |
| `Templates\FieldCondition` | class | 30 | 7 | `scalars()` | 8 | 110 |
| `Templates\TemplateField` | class | 30 | 4 | `isRequired()` | 14 | 109 |
| `Templates\Layout\LayoutRules` | class | 23 | 10 | `assert()` | 4 | 81 |
| `Templates\TemplateFieldsWriter` | class | 21 | 5 | `normalize()` | 6 | 111 |
| `Templates\TemplateRegistry` | class | 17 | 5 | `find()` | 6 | 60 |
| `Templates\Layout\FieldListInput` | class | 16 | 6 | `parse()` | 4 | 75 |
| `Templates\FamilyRules` | class | 12 | 6 | `refusal()` | 4 | 35 |
| `Templates\Layout\FieldListOutput` | class | 10 | 10 | `nest()` | 1 | 51 |
| `Templates\Layout\LayoutBlock` | class | 4 | 1 | `__construct()` | 4 | 26 |
| `Templates\Layout\LayoutSection` | class | 1 | 1 | `__construct()` | 1 | 10 |
| `Templates\Layout\LayoutTab` | class | 1 | 1 | `__construct()` | 1 | 6 |

## core/View

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `View\FieldRenderer` | class | 10 | 10 | `render()` | 1 | 23 |
| `View\MatrixRenderer` | class | 10 | 5 | `item()` | 3 | 37 |
| `View\HreflangBuilder` | class | 9 | 5 | `links()` | 5 | 43 |
| `View\ComponentRenderer` | class | 8 | 7 | `render()` | 2 | 39 |
| `View\LanguageSwitcher` | class | 8 | 7 | `links()` | 2 | 30 |

## forms

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `SubmissionPresenter` | class | 31 | 10 | `linesFor()` | 5 | 84 |
| `FormRegistry` | class | 11 | 5 | `register()` | 5 | 38 |
| `FormsServiceProvider` | class | 5 | 4 | `boot()` | 2 | 51 |
| `FormSchema` | class | 3 | 1 | `fields()` | 3 | 124 |
| `FormsFiles` | class | 3 | 2 | `deleteAfterCommit()` | 2 | 19 |
| `FormsTables` | class | 0 | 0 |  | 0 | 6 |

## forms/Actions

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Actions\SubmitForm` | class | 18 | 4 | `store()` | 10 | 106 |
| `Actions\InstallForms` | class | 17 | 8 | `installFields()` | 7 | 77 |
| `Actions\CreateForm` | class | 13 | 7 | `handle()` | 7 | 62 |
| `Actions\UpdateSubmission` | class | 11 | 6 | `handle()` | 6 | 60 |
| `Actions\PurgeSubmissions` | class | 10 | 3 | `handle()` | 8 | 51 |
| `Actions\DeleteSubmission` | class | 8 | 1 | `__construct()` | 8 | 46 |
| `Actions\ResolvesSubmissions` | trait | 3 | 3 | `submission()` | 1 | 10 |

## forms/Admin

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Admin\Livewire\Inbox` | class | 20 | 8 | `summaryOf()` | 6 | 106 |
| `Admin\Http\ExportSubmissions` | class | 14 | 8 | `columns()` | 3 | 52 |
| `Admin\Livewire\SubmissionView` | class | 13 | 4 | `setStatus()` | 7 | 70 |
| `Admin\Livewire\FormList` | class | 11 | 4 | `builderRows()` | 5 | 74 |
| `Admin\AdminIntegration` | class | 8 | 5 | `register()` | 2 | 46 |
| `Admin\Livewire\PreviewForm` | class | 4 | 2 | `definition()` | 3 | 19 |
| `Admin\Http\DownloadSubmissionFile` | class | 2 | 1 | `__construct()` | 2 | 14 |
| `Admin\OptionsConverter` | class | 2 | 1 | `toForm()` | 2 | 12 |

## forms/Agents

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Agents\ListSubmissionsTool` | class | 15 | 10 | `handle()` | 6 | 65 |
| `Agents\GetSubmissionTool` | class | 11 | 6 | `handle()` | 6 | 45 |

## forms/Builder

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Builder\PageFormCompiler` | class | 58 | 11 | `typeSettings()` | 13 | 210 |
| `Builder\PageFormValidator` | class | 29 | 13 | `blockErrors()` | 4 | 105 |
| `Builder\PageForms` | class | 13 | 5 | `find()` | 5 | 50 |
| `Builder\PageForm` | class | 12 | 2 | `successMessage()` | 9 | 59 |

## forms/Console

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Console\PurgeSubmissionsCommand` | class | 2 | 2 | `handle()` | 1 | 17 |

## forms/Definition

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Definition\FormField` | class | 52 | 14 | `isShown()` | 33 | 264 |
| `Definition\FormValidator` | class | 44 | 13 | `typeRules()` | 9 | 156 |
| `Definition\Form` | class | 19 | 3 | `text()` | 13 | 92 |
| `Definition\FormStep` | class | 1 | 1 | `__construct()` | 1 | 13 |

## forms/Events

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Events\FormSubmitted` | class | 1 | 1 | `__construct()` | 1 | 4 |

## forms/Fields

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Fields\OptionsType` | class | 14 | 3 | `wake()` | 10 | 54 |

## forms/Listeners

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Listeners\NotifyRecipients` | class | 22 | 7 | `notifyTeam()` | 5 | 61 |

## forms/Livewire

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Livewire\FormComponent` | class | 38 | 5 | `updated()` | 14 | 192 |
| `Livewire\Concerns\EditsRows` | trait | 11 | 4 | `addRow()` | 5 | 46 |
| `Livewire\Concerns\GuardsSubmissions` | trait | 7 | 2 | `secondsUntilAllowed()` | 5 | 43 |

## forms/Models

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Models\Submission` | class | 4 | 1 | `files()` | 4 | 32 |
| `Models\SubmissionFile` | class | 0 | 0 |  | 0 | 8 |

## forms/Notifications

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Notifications\SubmissionReceived` | class | 7 | 5 | `toMail()` | 3 | 32 |
| `Notifications\SubmissionConfirmation` | class | 6 | 4 | `toMail()` | 3 | 28 |

## forms/View

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `View\FormTag` | class | 12 | 8 | `__construct()` | 4 | 32 |

## mcp

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `McpServiceProvider` | class | 2 | 2 | `boot()` | 1 | 14 |
| `LynxServer` | class | 1 | 1 | `boot()` | 1 | 30 |

## mcp/Prompts

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Prompts\ContentAssistantPrompt` | class | 1 | 1 | `handle()` | 1 | 13 |

## mcp/Resources

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Resources\UsageGuideResource` | class | 1 | 1 | `handle()` | 1 | 19 |

## mcp/Tools

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Tools\ActionTool` | class | 7 | 2 | `handle()` | 6 | 40 |
| `Tools\ActsForUser` | trait | 6 | 4 | `userId()` | 2 | 21 |
| `Tools\ModuleReadTool` | class | 5 | 1 | `__construct()` | 5 | 29 |
| `Tools\FindPagesTool` | class | 3 | 2 | `handle()` | 2 | 30 |
| `Tools\GetPageTool` | class | 3 | 2 | `handle()` | 2 | 27 |
| `Tools\PageHistoryTool` | class | 3 | 2 | `handle()` | 2 | 27 |
| `Tools\DescribeFieldTypesTool` | class | 1 | 1 | `handle()` | 1 | 13 |
| `Tools\DescribeSchemaTool` | class | 1 | 1 | `handle()` | 1 | 13 |

## media

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `MediaPageFiles` | class | 7 | 4 | `write()` | 3 | 32 |
| `MediaPaths` | class | 6 | 1 | `disk()` | 6 | 33 |
| `MediaServiceProvider` | class | 4 | 3 | `boot()` | 2 | 31 |
| `DeleteMediaFolders` | class | 3 | 2 | `handle()` | 2 | 11 |

## media/Actions

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Actions\StoreMedia` | class | 46 | 12 | `image()` | 10 | 176 |
| `Actions\PruneMedia` | class | 21 | 6 | `handle()` | 11 | 113 |

## media/Admin

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Admin\Livewire\MediaField` | class | 30 | 7 | `render()` | 8 | 108 |
| `Admin\MediaConverter` | class | 28 | 14 | `toForm()` | 3 | 57 |
| `Admin\PageMediaFiles` | class | 9 | 4 | `__invoke()` | 3 | 42 |
| `Admin\AdminIntegration` | class | 1 | 1 | `register()` | 1 | 11 |

## media/Console

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Console\PruneMediaCommand` | class | 3 | 3 | `handle()` | 1 | 19 |

## media/Fields

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Fields\MediaFieldType` | class | 66 | 17 | `normalize()` | 21 | 235 |
| `Fields\ImagesType` | class | 17 | 7 | `details()` | 10 | 76 |
| `Fields\FilesType` | class | 6 | 1 | `name()` | 6 | 35 |

## media/Images

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Images\Variations` | class | 28 | 11 | `make()` | 6 | 68 |
| `Images\FocusCrop` | class | 2 | 2 | `rect()` | 1 | 22 |

## media/Values

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Values\Pageimage` | class | 22 | 5 | `focus()` | 9 | 74 |
| `Values\Pagefile` | class | 17 | 2 | `file()` | 12 | 64 |
| `Values\MediaList` | class | 14 | 3 | `inLanguage()` | 10 | 78 |

## media/View

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `View\ImageComponent` | class | 14 | 13 | `render()` | 2 | 31 |

## menus

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Menus` | class | 18 | 4 | `menuOf()` | 10 | 86 |
| `MenusServiceProvider` | class | 4 | 3 | `boot()` | 2 | 31 |
| `MenuSchema` | class | 2 | 1 | `fields()` | 2 | 83 |

## menus/Actions

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Actions\SaveMenuItem` | class | 27 | 4 | `handle()` | 12 | 138 |
| `Actions\InstallMenus` | class | 22 | 7 | `installFields()` | 8 | 98 |
| `Actions\CreateMenu` | class | 11 | 5 | `handle()` | 7 | 60 |

## menus/Admin

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Admin\MenuEditor` | class | 41 | 4 | `pageResults()` | 19 | 213 |
| `Admin\Forms\ItemForm` | class | 23 | 11 | `fillFrom()` | 7 | 137 |
| `Admin\MenuItemPresenter` | class | 16 | 5 | `detail()` | 5 | 65 |
| `Admin\Forms\SettingsForm` | class | 6 | 4 | `save()` | 2 | 27 |
| `Admin\AdminIntegration` | class | 5 | 4 | `editUrl()` | 2 | 31 |
| `Admin\Forms\MenuForm` | class | 0 | 0 |  | 0 | 7 |

## menus/Reading

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Reading\MenuReader` | class | 44 | 9 | `item()` | 12 | 189 |
| `Reading\ReadContext` | class | 11 | 6 | `isCurrent()` | 4 | 41 |
| `Reading\MenuItem` | class | 4 | 1 | `__construct()` | 4 | 57 |
| `Reading\ChildPages` | class | 2 | 1 | `__construct()` | 2 | 12 |
| `Reading\Menu` | class | 2 | 1 | `__construct()` | 2 | 26 |

## menus/Support

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Support\ItemValues` | class | 41 | 13 | `build()` | 6 | 121 |
| `Support\Translations` | class | 9 | 6 | `normalize()` | 3 | 41 |
| `Support\LinkRules` | class | 6 | 4 | `isSafeUrl()` | 3 | 41 |

## menus/View

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `View\IconRenderer` | class | 6 | 5 | `render()` | 2 | 28 |
| `View\MenuComponent` | class | 4 | 3 | `render()` | 2 | 18 |

## seo

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `SeoResolver` | class | 33 | 8 | `resolve()` | 12 | 127 |
| `SeoAuditor` | class | 12 | 5 | `issues()` | 4 | 57 |
| `RobotsTxt` | class | 11 | 5 | `render()` | 3 | 33 |
| `SeoSchema` | class | 8 | 2 | `fields()` | 6 | 151 |
| `SeoServiceProvider` | class | 5 | 4 | `boot()` | 2 | 42 |
| `SeoData` | class | 2 | 1 | `__construct()` | 2 | 31 |

## seo/Actions

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Actions\InstallSeo` | class | 18 | 7 | `installFields()` | 8 | 89 |
| `Actions\EnableSeo` | class | 10 | 5 | `handle()` | 6 | 51 |
| `Actions\DisableSeo` | class | 9 | 4 | `handle()` | 6 | 45 |

## seo/Admin

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Admin\Livewire\SeoSchemas` | class | 27 | 5 | `persist()` | 12 | 161 |
| `Admin\SourceSuggestions` | class | 10 | 6 | `for()` | 3 | 35 |
| `Admin\AdminIntegration` | class | 8 | 5 | `register()` | 2 | 37 |
| `Admin\Livewire\SeoTemplates` | class | 5 | 2 | `toggle()` | 4 | 34 |
| `Admin\Livewire\SeoAudit` | class | 2 | 1 | `boot()` | 2 | 13 |

## seo/Agents

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Agents\InspectSeoTool` | class | 12 | 8 | `handle()` | 5 | 38 |

## seo/Http

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Http\RobotsController` | class | 2 | 1 | `__construct()` | 2 | 9 |

## seo/JsonLd

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `JsonLd\ValueConverter` | class | 45 | 9 | `convert()` | 9 | 112 |
| `JsonLd\EntityBuilder` | class | 38 | 12 | `set()` | 7 | 133 |
| `JsonLd\JsonLd` | class | 34 | 9 | `organization()` | 10 | 155 |
| `JsonLd\MappingValidator` | class | 30 | 17 | `problem()` | 3 | 104 |
| `JsonLd\JsonLdGraph` | class | 19 | 7 | `clean()` | 8 | 79 |
| `JsonLd\Mappings` | class | 14 | 9 | `items()` | 2 | 41 |
| `JsonLd\SourceReader` | class | 13 | 7 | `read()` | 2 | 32 |
| `JsonLd\SchemaTypes` | class | 11 | 4 | `listOf()` | 6 | 137 |
| `JsonLd\ListSubfields` | class | 9 | 9 | `of()` | 1 | 20 |
| `JsonLd\Mapping` | class | 9 | 6 | `parse()` | 4 | 50 |

## seo/Support

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Support\PageText` | class | 11 | 4 | `of()` | 4 | 52 |
| `Support\ShareImage` | class | 9 | 5 | `imageOf()` | 2 | 39 |
| `Support\AbsoluteUrl` | class | 2 | 2 | `of()` | 1 | 12 |

## seo/View

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `View\SeoHead` | class | 3 | 1 | `__construct()` | 3 | 16 |
| `View\SeoComponent` | class | 2 | 1 | `__construct()` | 2 | 17 |

## sitemap

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `SitemapWriter` | class | 23 | 7 | `page()` | 10 | 118 |
| `SitemapPages` | class | 19 | 5 | `groups()` | 8 | 85 |
| `Sitemap` | class | 14 | 5 | `part()` | 5 | 63 |
| `SitemapSettings` | class | 11 | 3 | `groupBy()` | 6 | 50 |
| `SitemapServiceProvider` | class | 6 | 4 | `boot()` | 3 | 45 |
| `SitemapCache` | class | 5 | 2 | `store()` | 4 | 29 |
| `SitemapFilters` | class | 2 | 1 | `exclude()` | 2 | 16 |
| `SitemapSchema` | class | 1 | 1 | `fields()` | 1 | 36 |

## sitemap/Actions

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Actions\InstallSitemap` | class | 12 | 7 | `handle()` | 6 | 66 |
| `Actions\ClearSitemapCache` | class | 8 | 2 | `handle()` | 7 | 40 |

## sitemap/Admin

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Admin\Livewire\SitemapSettings` | class | 17 | 9 | `mount()` | 6 | 74 |
| `Admin\AdminIntegration` | class | 2 | 2 | `register()` | 1 | 17 |

## sitemap/Console

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Console\SitemapCommand` | class | 8 | 8 | `handle()` | 1 | 37 |

## sitemap/Http

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Http\SitemapController` | class | 6 | 2 | `respond()` | 5 | 30 |

## sitemap/Integrations

| Class | Kind | Total | Max | Worst method | Methods | Lines |
| --- | --- | ---: | ---: | --- | ---: | ---: |
| `Integrations\SeoIntegration` | class | 10 | 5 | `excludes()` | 3 | 30 |
