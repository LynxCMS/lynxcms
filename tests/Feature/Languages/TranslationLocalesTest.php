<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

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

describe('JSON translation strings', function (): void {
    beforeEach(function (): void {
        $this->lang = testingPath('json-lang');
        File::ensureDirectoryExists($this->lang);
        file_put_contents($this->lang.'/pt.json', json_encode(['Book now' => 'Reservar já', 'Pay' => 'Pagar']));
        app('translator')->addJsonPath($this->lang);
    });

    afterEach(function (): void {
        File::deleteDirectory($this->lang);
    });

    it('reads a regional language\'s strings from the bare language\'s file', function (): void {
        expect(lynx()->language('pt', fn (): string => __('Book now')))->toBe('Reservar já');
    });

    it('prefers the regional file\'s strings, and keeps the rest', function (): void {
        file_put_contents($this->lang.'/pt-PT.json', json_encode(['Book now' => 'Reserve já']));

        expect(lynx()->language('pt', fn (): array => [__('Book now'), __('Pay')]))->toBe(['Reserve já', 'Pagar']);
    });

    it('leaves strings with no translation as they are', function (): void {
        expect(lynx()->language('pt', fn (): string => __('Not translated')))->toBe('Not translated');
    });
});
