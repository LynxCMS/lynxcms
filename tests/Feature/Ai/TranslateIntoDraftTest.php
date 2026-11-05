<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Contracts\Translation\TranslationProvider;
use LynxCms\Core\Actions\Pages\PublishDraft;
use LynxCms\Core\Actions\Pages\TranslatePage;
use LynxCms\Core\Translation\TranslationProviders;
use LynxCms\Tests\Fixtures\FakeTranslationProvider;

/*
 * pages.translate can write into the page's draft (ADR-0100), so an editor reviews machine
 * translations before visitors see them, and can name the provider to use. A dry run counts
 * what it would send and calls no provider.
 */

beforeEach(function (): void {
    courseSite();
    $this->provider = new FakeTranslationProvider;
    app()->instance(TranslationProvider::class, $this->provider);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
});

function translateAlpha(array $input = [], bool $dryRun = false): mixed
{
    return lynx()->run(TranslatePage::class, ['page' => test()->alphaId, 'to' => 'pt', 'draft' => true] + $input, $dryRun);
}

it('writes the translation into a new draft and leaves the live page alone', function (): void {
    translateAlpha();

    expect(lynx()->pages->get($this->alphaId)?->getLanguageValue('pt', 'title'))->toBeNull()
        ->and(lynx()->pages->draft($this->alphaId)?->getLanguageValue('pt', 'title'))->toBe('[pt-PT] Alpha');
});

it('translates what the draft holds, and keeps the draft\'s other edits', function (): void {
    lynx()->pages->saveDraft($this->alphaId, ['title' => ['default' => 'Alpha revised'], 'price' => 30]);

    translateAlpha();
    $draft = lynx()->pages->draft($this->alphaId);

    expect($draft?->getLanguageValue('pt', 'title'))->toBe('[pt-PT] Alpha revised')
        ->and($draft?->get('price'))->toBe(30);
});

it('publishes the draft\'s translation as a machine translation', function (): void {
    translateAlpha();

    lynx()->run(PublishDraft::class, ['page' => $this->alphaId]);

    expect(DB::table('lynx_value_meta')->where('page_id', $this->alphaId)->where('language_id', lynx()->languages->get('pt')->id)->value('origin'))->toBe('machine');
});

it('uses the provider it is asked for', function (): void {
    $other = new class implements TranslationProvider
    {
        public function name(): string
        {
            return 'other';
        }

        public function translate(array $texts, string $from, string $to, array $formats = [], array $options = []): array
        {
            return array_map(fn (string $text): string => "other: {$text}", $texts);
        }
    };
    app(TranslationProviders::class)->register('other', fn (): TranslationProvider => $other);

    translateAlpha(['provider' => 'other']);

    expect(lynx()->pages->draft($this->alphaId)?->getLanguageValue('pt', 'title'))->toBe('other: Alpha')
        ->and($this->provider->calls)->toBe([]);
});

it('refuses a provider it does not know, and writes nothing', function (): void {
    expect(fn (): mixed => translateAlpha(['provider' => 'nobody']))->toThrow(ValidationException::class)
        ->and(lynx()->pages->draft($this->alphaId))->toBeNull();
});

it('counts what it would translate in a dry run, without calling the provider', function (): void {
    $result = translateAlpha(dryRun: true);

    expect($this->provider->calls)->toBe([])
        ->and($result->changes->all()[0]->data)->toMatchArray(['texts' => 1, 'characters' => 5])
        ->and(lynx()->pages->draft($this->alphaId))->toBeNull();
});

it('creates no draft when the provider fails', function (): void {
    app()->instance(TranslationProvider::class, new class implements TranslationProvider
    {
        public function name(): string
        {
            return 'broken';
        }

        public function translate(array $texts, string $from, string $to, array $formats = [], array $options = []): array
        {
            throw new RuntimeException('Provider down.');
        }
    });

    expect(fn (): mixed => translateAlpha())->toThrow(RuntimeException::class, 'Provider down.')
        ->and(lynx()->pages->draft($this->alphaId))->toBeNull();
});

it('lists the default provider first, then the registered ones', function (): void {
    expect(app(TranslationProviders::class)->names())->toBe(['fake', 'laravel-ai']);
});

it('translates into the draft from the pages API', function (): void {
    lynx()->pages->translate($this->alphaId, 'pt', draft: true, provider: 'fake');

    expect(lynx()->pages->draft($this->alphaId)?->getLanguageValue('pt', 'title'))->toBe('[pt-PT] Alpha');
});

it('wraps every provider it hands out with the extensions modules add', function (): void {
    $seen = [];
    app(TranslationProviders::class)->extend(function (TranslationProvider $provider) use (&$seen): TranslationProvider {
        $seen[] = $provider->name();

        return $provider;
    });

    translateAlpha();
    app(TranslationProviders::class)->register('second', fn (): TranslationProvider => new FakeTranslationProvider);
    app(TranslationProviders::class)->get('second');

    expect($seen)->toBe(['fake', 'fake']);
});

it('passes a module\'s options on through an extension, such as a glossary', function (): void {
    app(TranslationProviders::class)->extend(fn (TranslationProvider $provider): TranslationProvider => new readonly class($provider) implements TranslationProvider
    {
        public function __construct(private TranslationProvider $inner) {}

        public function name(): string
        {
            return $this->inner->name();
        }

        public function translate(array $texts, string $from, string $to, array $formats = [], array $options = []): array
        {
            return $this->inner->translate($texts, $from, $to, $formats, $options + ['glossary' => ['Alpha' => 'Alfa']]);
        }
    });

    translateAlpha();

    expect($this->provider->options[0]['glossary'] ?? null)->toBe(['Alpha' => 'Alfa']);
});
