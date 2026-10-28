<?php

declare(strict_types=1);

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\File;
use Livewire\Livewire;
use LynxCms\Cache\Admin\Livewire\PageCacheScreen;

/*
 * The page cache screen's layout: Overview (what is stored, per template, and clearing), Rules
 * (each template's rule, summed up on its row), Fragments and Web server, as tabs.
 */

beforeEach(function (): void {
    config(['lynx-cache.pages.enabled' => true, 'lynx-cache.pages.path' => testingPath('lynx-cache')]);
    File::deleteDirectory(testingPath('lynx-cache'));
    installLynx();
    lynx()->templates->add('note', fields: [['name' => 'title', 'required' => true]]);
    $page = lynx()->pages->new('note', '/');
    $page->name = 'a';
    $page->title = 'A';
    $page->save();
});

it('puts the screen in tabs', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    $response = $this->get(route('lynx.admin.page-cache'))->assertOk();

    foreach (['overview', 'rules', 'fragments', 'server'] as $tab) {
        $response->assertSee('data-cache-tab="'.$tab.'"', false);
    }
});

it('counts the stored pages per template on the overview', function (): void {
    $this->get('/a/');
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PageCacheScreen::class)
        ->assertSeeHtml('data-stored-total="1"')
        ->assertSeeHtml('data-template-stored="note" data-count="1"')
        ->call('clearTemplate', 'note')
        ->assertSeeHtml('data-stored-total="0"');
});

it('sums up each template\'s rule on its row', function (): void {
    $this->actingAs(new GenericUser(['id' => 1]));

    Livewire::test(PageCacheScreen::class)
        ->assertSeeHtml('data-rule-summary="note"')
        ->assertSeeHtml('data-rule-cached="note"');
});
