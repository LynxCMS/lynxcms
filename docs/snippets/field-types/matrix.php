<?php

declare(strict_types=1);

// #region fields
// The fields of each block type, first.
lynx()->fields->add('body', 'textarea', translatable: true, settings: ['format' => 'html']);
lynx()->fields->add('video_url', 'url');
lynx()->fields->add('caption', 'text', translatable: true);

// Then the matrix that lists them.
lynx()->fields->add('blocks', 'matrix', settings: ['types' => [
    ['slug' => 'text', 'label' => 'Text', 'fields' => [['name' => 'body']]],
    ['slug' => 'video', 'label' => 'Video', 'fields' => [['name' => 'video_url', 'required' => true], ['name' => 'caption']]],
]]);
// #endregion fields
