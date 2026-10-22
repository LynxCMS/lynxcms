<?php

declare(strict_types=1);

beforeEach(function (): void {
    courseSite();
    app('translator')->addLines(['demo.greeting' => 'Olá'], 'pt');
    app('translator')->addLines(['demo.greeting' => 'Hello', 'demo.only_english' => 'Only English'], 'en');
});

it('reads a regional language\'s translations from the bare language', function (): void {
    expect(lynx()->language('pt', fn (): string => __('demo.greeting')))->toBe('Olá');
});

it('still falls back to the app\'s fallback language', function (): void {
    expect(lynx()->language('pt', fn (): string => __('demo.only_english')))->toBe('Only English');
});

it('prefers the regional translation when there is one', function (): void {
    app('translator')->addLines(['demo.greeting' => 'Olá de Portugal'], 'pt-PT');

    expect(lynx()->language('pt', fn (): string => __('demo.greeting')))->toBe('Olá de Portugal');
});

it('accepts both spellings of a regional locale', function (): void {
    app('translator')->addLines(['demo.greeting' => 'Olá (pt_PT)'], 'pt_PT');

    expect(lynx()->language('pt', fn (): string => __('demo.greeting')))->toBe('Olá (pt_PT)');
});
