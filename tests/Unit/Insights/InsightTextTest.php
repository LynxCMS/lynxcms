<?php

declare(strict_types=1);

use LynxCms\Insights\InsightText;

/*
 * InsightText keeps a translation key and its replacements, so a stored insight is shown in the
 * language of whoever reads it, whatever language the report was written in.
 */

it('translates in the current locale', function (): void {
    $text = new InsightText('lynx-insights::sources.missing_translations.evidence.links');

    app()->setLocale('pt');

    expect($text->translate())->toBe(__('lynx-insights::sources.missing_translations.evidence.links', [], 'pt'));
});

it('chooses the plural by its count', function (): void {
    $text = new InsightText('lynx-insights::sources.missing_translations.title', ['language' => 'Português'], count: 1);

    expect((string) $text)->toBe(trans_choice('lynx-insights::sources.missing_translations.title', 1, ['language' => 'Português']));
});

it('shows plain text as it is', function (): void {
    expect(InsightText::from('Shorten alpha')->translate())->toBe('Shorten alpha');
});

it('survives a round trip through storage', function (): void {
    $text = new InsightText('lynx-insights::sources.missing_translations.title', ['language' => 'Português'], count: 4);

    expect(InsightText::fromArray($text->toArray()))->toEqual($text);
});
