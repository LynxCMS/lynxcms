<?php

declare(strict_types=1);

// #region store
use LynxCms\Media\Actions\StoreMedia;

$entry = lynx()->run(StoreMedia::class, [
    'page' => '/projects/documentary',
    'field' => 'gallery',
    'url' => 'https://example.com/still.jpg',
])->value;

$page = lynx()->pages->get('/projects/documentary');
$page->gallery = [...$page->getUnformatted('gallery') ?? [], ['values' => $entry + ['description' => ['en' => 'On set']]]];
$page->save();
// #endregion store
