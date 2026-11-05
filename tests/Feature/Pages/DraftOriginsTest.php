<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\PublishDraft;
use LynxCms\Core\Actions\Pages\SaveDraft;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Support\Tables;

/*
 * A value keeps its origin (ADR-0016) through a draft: a machine translation saved into a draft
 * is still a machine translation once the draft is published, and a person's edit is human.
 */

beforeEach(function (): void {
    courseSite();
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alphaId = (int) $alpha->id;
});

/** @return array{origin: string|null, provider: string|null, source_hash: string|null} the live title's origin in Portuguese */
function liveTitleOrigin(): array
{
    $row = DB::table(Tables::FIELD_VALUE_META)
        ->where('page_id', test()->alphaId)
        ->where('field_id', lynx()->fields->find('title')?->id)
        ->where('language_id', lynx()->languages->find('pt')?->id)
        ->first();

    return ['origin' => $row->origin ?? null, 'provider' => $row->provider ?? null, 'source_hash' => $row->source_hash ?? null];
}

function machineTitle(string $action, string $text): void
{
    $page = $action === SaveDraft::class ? ['page' => test()->alphaId] : ['id' => test()->alphaId];

    lynx()->run($action, $page + [
        'values' => ['title' => ['pt' => $text]],
        'origin' => 'machine',
        'provider' => 'fake',
        'source_hashes' => ['title' => hash('sha256', 'Alpha')],
    ]);
}

it('publishes a machine translation saved in a draft as a machine translation', function (): void {
    machineTitle(SaveDraft::class, 'Alfa');

    lynx()->run(PublishDraft::class, ['page' => $this->alphaId]);

    expect(liveTitleOrigin())->toBe(['origin' => 'machine', 'provider' => 'fake', 'source_hash' => hash('sha256', 'Alpha')]);
});

it('publishes a person\'s edit in the draft as human', function (): void {
    machineTitle(SavePage::class, 'Alfa');
    lynx()->pages->saveDraft($this->alphaId, ['title' => ['pt' => 'Alfa revista']]);

    lynx()->run(PublishDraft::class, ['page' => $this->alphaId]);

    expect(liveTitleOrigin())->toBe(['origin' => 'human', 'provider' => null, 'source_hash' => null]);
});

it('keeps the origin of values the draft did not change', function (): void {
    machineTitle(SavePage::class, 'Alfa');
    lynx()->pages->saveDraft($this->alphaId, ['price' => 20]);

    lynx()->run(PublishDraft::class, ['page' => $this->alphaId]);

    expect(liveTitleOrigin()['origin'])->toBe('machine');
});

it('refuses an origin it does not know, and creates no draft', function (): void {
    expect(fn (): mixed => lynx()->run(SaveDraft::class, ['page' => $this->alphaId, 'values' => ['title' => ['pt' => 'Alfa']], 'origin' => 'guessed']))
        ->toThrow(ValidationException::class)
        ->and(lynx()->pages->draft($this->alphaId))->toBeNull();
});
