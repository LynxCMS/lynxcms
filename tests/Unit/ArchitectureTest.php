<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use LynxCms\Admin\Branding\SaveBranding;
use LynxCms\Ai\AgentRun;
use LynxCms\Ai\LynxAi;
use LynxCms\Cache\Pages\ClearOnChange;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Fields\FieldSchemaManager;
use LynxCms\Core\Indexes\TrigramIndexes;
use LynxCms\Core\Query\ClauseComparison;
use LynxCms\Core\Query\CountClause;
use LynxCms\Core\Query\ItemPropertyCompiler;
use LynxCms\Core\Query\JsonSql;
use LynxCms\Core\Query\SelectorCompiler;
use LynxCms\Core\Query\Sql;
use LynxCms\Core\Query\SubfieldCompiler;
use LynxCms\Core\Query\ValueJoins;
use LynxCms\Core\Search\Engines\SqliteSearchEngine;
use LynxCms\Core\Templates\TemplateFieldsWriter;
use LynxCms\Forms\FormsFiles;
use LynxCms\Media\MovePrivateMedia;
use LynxCms\Media\Remote\MediaReferences;
use LynxCms\Menus\Menus;
use LynxCms\Menus\MenuSchema;
use LynxCms\Menus\MenusServiceProvider;
use LynxCms\PagePassword\PagePasswordSchema;
use LynxCms\PagePassword\StoredPassword;
use LynxCms\Seo\RobotsTxt;
use LynxCms\Seo\SeoResolver;
use LynxCms\Seo\SeoSchema;
use LynxCms\Sitemap\Sitemap;
use LynxCms\Sitemap\SitemapPages;
use LynxCms\Sitemap\SitemapWriter;

arch('source files declare strict types')
    ->expect('LynxCms')
    ->toUseStrictTypes();

arch('contracts depend on nothing in the implementation')
    ->expect('LynxCms\Contracts')
    ->not->toUse(['LynxCms\Core', 'LynxCms\Mcp', 'LynxCms\Ai', 'LynxCms\Admin']);

arch('the core does not depend on the MCP, AI, admin or menu packages')
    ->expect('LynxCms\Core')
    ->not->toUse(['LynxCms\Mcp', 'LynxCms\Ai', 'LynxCms\Admin', 'LynxCms\Menus', 'Laravel\Mcp', 'Laravel\Ai']);

arch('menus work without Livewire or the admin, except their admin screens')
    ->expect(['LynxCms\Menus\Actions', 'LynxCms\Menus\Reading', 'LynxCms\Menus\Support', 'LynxCms\Menus\View', Menus::class, MenuSchema::class, MenusServiceProvider::class])
    ->not->toUse(['Livewire', 'LynxCms\Admin']);

arch('media works without Livewire or the admin, except its admin input')
    ->expect(['LynxCms\Media\Actions', 'LynxCms\Media\Fields', 'LynxCms\Media\Values', 'LynxCms\Media\Images', 'LynxCms\Media\View'])
    ->not->toUse(['Livewire', 'LynxCms\Admin']);

arch('forms work without Livewire or the admin, except their screens and component')
    ->expect(['LynxCms\Forms\Actions', 'LynxCms\Forms\Definition', 'LynxCms\Forms\Models', 'LynxCms\Forms\Notifications', 'LynxCms\Forms\Listeners'])
    ->not->toUse(['Livewire', 'LynxCms\Admin']);

arch('SEO works without Livewire or the admin, except its screens')
    ->expect(['LynxCms\Seo\Actions', 'LynxCms\Seo\Agents', 'LynxCms\Seo\Http', 'LynxCms\Seo\JsonLd', 'LynxCms\Seo\Support', 'LynxCms\Seo\View', SeoResolver::class, SeoSchema::class, RobotsTxt::class])
    ->not->toUse(['Livewire', 'LynxCms\Admin']);

arch('the sitemap works without Livewire or the admin, except its screen')
    ->expect(['LynxCms\Sitemap\Actions', 'LynxCms\Sitemap\Console', 'LynxCms\Sitemap\Http', 'LynxCms\Sitemap\Integrations', Sitemap::class, SitemapPages::class, SitemapWriter::class])
    ->not->toUse(['Livewire', 'LynxCms\Admin']);

arch('the core does not depend on the sitemap module')
    ->expect('LynxCms\Core')
    ->not->toUse('LynxCms\Sitemap');

arch('sitemap actions are final and extend the base action')
    ->expect('LynxCms\Sitemap\Actions')
    ->classes()
    ->toBeFinal()
    ->toExtend(Action::class);

arch('the core does not depend on the SEO module')
    ->expect('LynxCms\Core')
    ->not->toUse('LynxCms\Seo');

arch('SEO actions are final and extend the base action')
    ->expect('LynxCms\Seo\Actions')
    ->classes()
    ->toBeFinal()
    ->toExtend(Action::class);

arch('the core does not depend on the forms module')
    ->expect('LynxCms\Core')
    ->not->toUse('LynxCms\Forms');

arch('menu actions are final and extend the base action')
    ->expect('LynxCms\Menus\Actions')
    ->classes()
    ->toBeFinal()
    ->toExtend(Action::class);

arch('actions are final and extend the base action')
    ->expect(['LynxCms\Core\Actions\Languages', 'LynxCms\Core\Actions\Fields', 'LynxCms\Core\Actions\Templates', 'LynxCms\Core\Actions\Pages'])
    ->classes()
    ->toBeFinal()
    ->toExtend(Action::class);

arch('only actions write page data')
    ->expect(DB::class)
    ->toOnlyBeUsedIn([
        'LynxCms\Core\Actions',
        'LynxCms\Core\Pages',
        'LynxCms\Core\Query',
        FieldSchemaManager::class,
        TemplateFieldsWriter::class,
        'LynxCms\Core\Agents',
        'LynxCms\Core\Schema',
        'LynxCms\Core\Content',
        AgentRun::class,
        LynxAi::class,
        'LynxCms\Media\Actions',
        'LynxCms\Forms\Actions',
        FormsFiles::class,
        'LynxCms\Core\Strings',
        'LynxCms\Core\Search',
        'LynxCms\Core\Indexes',
        'LynxCms\PagePassword\Access',
        // Only defers clearing the page cache until the action's transaction commits.
        ClearOnChange::class,
        // Reads which files a field's values name, and defers deleting moved files to the commit.
        MovePrivateMedia::class,
        // Reads which files pages' media values name, for lynx:media:pull.
        MediaReferences::class,
        // Only defers writing and deleting the admin's logo files until the action commits.
        SaveBranding::class,
    ]);

arch('no debugging calls')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('only the selector compiler may mark runtime-built SQL as trusted')
    ->expect(Sql::class)
    ->toOnlyBeUsedIn([SelectorCompiler::class, ClauseComparison::class, CountClause::class, TrigramIndexes::class, JsonSql::class, SubfieldCompiler::class, ItemPropertyCompiler::class, ValueJoins::class, SqliteSearchEngine::class]);

arch('the core does not depend on the page password module')
    ->expect('LynxCms\Core')
    ->not->toUse('LynxCms\PagePassword');

arch('page passwords work without Livewire or the admin, except their input')
    ->expect(['LynxCms\PagePassword\Access', 'LynxCms\PagePassword\Actions', 'LynxCms\PagePassword\Http', 'LynxCms\PagePassword\Fields', PagePasswordSchema::class, StoredPassword::class])
    ->not->toUse(['Livewire', 'LynxCms\Admin']);

arch('page password actions are final and extend the base action')
    ->expect('LynxCms\PagePassword\Actions')
    ->classes()
    ->toBeFinal()
    ->toExtend(Action::class);

arch('the static export works without Livewire or the admin, except its screen')
    ->expect(['LynxCms\StaticSite\Actions', 'LynxCms\StaticSite\Console', 'LynxCms\StaticSite\Export'])
    ->not->toUse(['Livewire', 'LynxCms\Admin']);

arch('the core does not depend on the static export')
    ->expect('LynxCms\Core')
    ->not->toUse('LynxCms\StaticSite');

arch('static export actions are final and extend the base action')
    ->expect('LynxCms\StaticSite\Actions')
    ->classes()
    ->toBeFinal()
    ->toExtend(Action::class);
