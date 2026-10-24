<?php

declare(strict_types=1);

namespace LynxCms\Core;

use LynxCms\Core\Actions\Content\ImportContent;
use LynxCms\Core\Actions\Fields\CleanHtmlValues;
use LynxCms\Core\Actions\Fields\CreateField;
use LynxCms\Core\Actions\Fields\DeleteField;
use LynxCms\Core\Actions\Fields\UpdateField;
use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Actions\Languages\CreateLanguage;
use LynxCms\Core\Actions\Languages\DeleteLanguage;
use LynxCms\Core\Actions\Languages\SetCurrentLanguage;
use LynxCms\Core\Actions\Languages\UpdateLanguage;
use LynxCms\Core\Actions\Pages\DeletePage;
use LynxCms\Core\Actions\Pages\DeleteRedirect;
use LynxCms\Core\Actions\Pages\DiscardDraft;
use LynxCms\Core\Actions\Pages\EmptyTrash;
use LynxCms\Core\Actions\Pages\LockEdit;
use LynxCms\Core\Actions\Pages\MovePage;
use LynxCms\Core\Actions\Pages\PublishDraft;
use LynxCms\Core\Actions\Pages\PurgeOrphanedValues;
use LynxCms\Core\Actions\Pages\RestorePage;
use LynxCms\Core\Actions\Pages\RestoreRevision;
use LynxCms\Core\Actions\Pages\RunSchedules;
use LynxCms\Core\Actions\Pages\SaveDraft;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Actions\Pages\SchedulePage;
use LynxCms\Core\Actions\Pages\SetPageStatus;
use LynxCms\Core\Actions\Pages\SortPage;
use LynxCms\Core\Actions\Pages\SwitchTemplate;
use LynxCms\Core\Actions\Pages\TranslatePage;
use LynxCms\Core\Actions\Pages\TrashPage;
use LynxCms\Core\Actions\Pages\UnlockEdit;
use LynxCms\Core\Actions\Roles\AssignRoles;
use LynxCms\Core\Actions\Roles\DeleteRole;
use LynxCms\Core\Actions\Roles\SaveRole;
use LynxCms\Core\Actions\Strings\SaveStrings;
use LynxCms\Core\Actions\Templates\CreateTemplate;
use LynxCms\Core\Actions\Templates\DeleteTemplate;
use LynxCms\Core\Actions\Templates\UpdateTemplate;
use LynxCms\Core\Console\BackupCommand;
use LynxCms\Core\Console\BenchCommand;
use LynxCms\Core\Console\CleanHtmlCommand;
use LynxCms\Core\Console\DemoCommand;
use LynxCms\Core\Console\ExportCommand;
use LynxCms\Core\Console\FindCommand;
use LynxCms\Core\Console\ImportCommand;
use LynxCms\Core\Console\InstallCommand;
use LynxCms\Core\Console\LanguagesCommand;
use LynxCms\Core\Console\MigrateCommand;
use LynxCms\Core\Console\PhpStanCommand;
use LynxCms\Core\Console\ScanStringsCommand;
use LynxCms\Core\Console\ScheduleRunCommand;
use LynxCms\Core\Console\SchemaApplyCommand;
use LynxCms\Core\Console\SchemaExportCommand;
use LynxCms\Core\Fields\Formatters\ExternalLinks;
use LynxCms\Core\Fields\Formatters\HeadingAnchors;
use LynxCms\Core\Fields\Formatters\Markdown;
use LynxCms\Core\Fields\Formatters\Paragraphs;
use LynxCms\Core\Fields\Formatters\SmartyPants;
use LynxCms\Core\Fields\Types\ChoiceType;
use LynxCms\Core\Fields\Types\ComponentType;
use LynxCms\Core\Fields\Types\DateType;
use LynxCms\Core\Fields\Types\DecimalType;
use LynxCms\Core\Fields\Types\EmailType;
use LynxCms\Core\Fields\Types\IntegerType;
use LynxCms\Core\Fields\Types\MatrixType;
use LynxCms\Core\Fields\Types\PageReferenceType;
use LynxCms\Core\Fields\Types\PageTableType;
use LynxCms\Core\Fields\Types\RepeaterType;
use LynxCms\Core\Fields\Types\TextareaType;
use LynxCms\Core\Fields\Types\TextType;
use LynxCms\Core\Fields\Types\TimeType;
use LynxCms\Core\Fields\Types\ToggleType;
use LynxCms\Core\Fields\Types\UrlType;

/**
 * What the core registers at boot: its field types, formatters, actions and Artisan commands,
 * kept apart from LynxServiceProvider so the provider holds only the wiring. Modules register
 * their own through the same registries.
 *
 * @internal
 */
final class CoreRegistrations
{
    public const array FIELD_TYPES = [
        TextType::class,
        TextareaType::class,
        IntegerType::class,
        DecimalType::class,
        EmailType::class,
        UrlType::class,
        ChoiceType::class,
        DateType::class,
        TimeType::class,
        ToggleType::class,
        PageReferenceType::class,
        PageTableType::class,
        RepeaterType::class,
        MatrixType::class,
        ComponentType::class,
    ];

    public const array FORMATTERS = [
        SmartyPants::class,
        Paragraphs::class,
        Markdown::class,
        ExternalLinks::class,
        HeadingAnchors::class,
    ];

    public const array ACTIONS = [
        SaveStrings::class,
        InstallLynx::class,
        CreateLanguage::class,
        UpdateLanguage::class,
        DeleteLanguage::class,
        SetCurrentLanguage::class,
        CreateField::class,
        UpdateField::class,
        DeleteField::class,
        CreateTemplate::class,
        UpdateTemplate::class,
        DeleteTemplate::class,
        SavePage::class,
        MovePage::class,
        SortPage::class,
        SetPageStatus::class,
        DeletePage::class,
        SchedulePage::class,
        LockEdit::class,
        RestoreRevision::class,
        SaveDraft::class,
        ImportContent::class,
        PublishDraft::class,
        DiscardDraft::class,
        UnlockEdit::class,
        RunSchedules::class,
        TrashPage::class,
        RestorePage::class,
        EmptyTrash::class,
        SwitchTemplate::class,
        PurgeOrphanedValues::class,
        TranslatePage::class,
        DeleteRedirect::class,
        CleanHtmlValues::class,
        SaveRole::class,
        DeleteRole::class,
        AssignRoles::class,
    ];

    public const array COMMANDS = [
        InstallCommand::class,
        MigrateCommand::class,
        LanguagesCommand::class,
        FindCommand::class,
        DemoCommand::class,
        BenchCommand::class,
        SchemaExportCommand::class,
        SchemaApplyCommand::class,
        CleanHtmlCommand::class,
        ScheduleRunCommand::class,
        ExportCommand::class,
        ImportCommand::class,
        BackupCommand::class,
        ScanStringsCommand::class,
        PhpStanCommand::class,
    ];
}
