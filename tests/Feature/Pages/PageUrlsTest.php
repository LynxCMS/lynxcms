<?php

declare(strict_types=1);

use LynxCms\Core\Pages\PageUrls;

beforeEach(function (): void {
    installLynx();
    lynx()->languages->add('pt', 'Português', fallback: 'default');
    lynx()->languages->add('de', 'Deutsch', fallback: 'default');
    lynx()->templates->add('note', fields: [['name' => 'title', 'required' => true]]);
});

it('lists a page\'s address in every active language it is live in', function (): void {
    $page = lynx()->pages->new('note', '/');
    $page->name = 'about';
    $page->title = 'About';
    $page->setLanguageName('pt', 'sobre');
    $page->setLanguageName('de', 'ueber');
    $page->setLanguageStatus('de', false);
    $page->save();

    expect(app(PageUrls::class)->of(lynx()->pages->get('/about/')))->toBe(['/about', '/pt/sobre']);
});

it('lists the home page once per language', function (): void {
    expect(app(PageUrls::class)->of(lynx()->pages->get('/')))->toBe(['/', '/pt', '/de']);
});
