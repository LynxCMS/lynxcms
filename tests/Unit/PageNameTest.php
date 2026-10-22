<?php

declare(strict_types=1);

use LynxCms\Core\Pages\PageName;

it('turns text into a URL name', function (string $text, string $name): void {
    expect(PageName::from($text))->toBe($name);
})->with([
    ['Agile Leadership: Ação!', 'agile-leadership-acao'],
    ['  --Spaces  and  dashes--  ', 'spaces-and-dashes'],
    ['already_fine-name', 'already_fine-name'],
    ['!!!', ''],
]);
