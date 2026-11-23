<?php

declare(strict_types=1);

use LynxCms\Core\Actions\Runner\PageFold;

it('collects the pages of folded events once each', function (): void {
    $fold = new PageFold;
    $fold->add([3, 1]);
    $fold->add([1, 2]);

    expect($fold->isEmpty())->toBeFalse()
        ->and($fold->event()->affectedPages())->toBe([1, 2, 3]);
});

it('means every page past the limit or when an event did not say', function (): void {
    $many = new PageFold;
    $many->add(range(1, PageFold::LIMIT + 1));
    $unknown = new PageFold;
    $unknown->add([1]);
    $unknown->add(null);

    expect($many->event()->affectedPages())->toBeNull()
        ->and($unknown->event()->affectedPages())->toBeNull();
});

it('merges another fold', function (): void {
    $fold = new PageFold;
    $other = new PageFold;
    $other->add([5]);

    $fold->merge($other);

    expect((new PageFold)->isEmpty())->toBeTrue()
        ->and($fold->event()->affectedPages())->toBe([5]);
});
