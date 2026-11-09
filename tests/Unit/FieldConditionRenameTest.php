<?php

declare(strict_types=1);

use LynxCms\Core\Templates\FieldCondition;

it('renames the fields a condition reads', function (): void {
    $condition = FieldCondition::parse('format=onsite|hybrid, price>0, venue!=')->renamed(['format' => 'event_format', 'venue' => 'event_venue']);

    expect($condition->toString())->toBe('event_format=onsite|hybrid, price>0, event_venue!=')
        ->and($condition->fields())->toBe(['event_format', 'price', 'event_venue']);
});

it('writes a condition back as it reads it', function (): void {
    expect(FieldCondition::parse(' title %= lynx ')->renamed([])->toString())->toBe('title%=lynx');
});
