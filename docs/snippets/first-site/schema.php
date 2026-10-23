<?php

declare(strict_types=1);

// The blog's fields and templates, as in "Your First Site". Run it once with
// php artisan tinker, or put the same calls in a seeder.

// #region schema
lynx()->fields->add('summary', 'textarea', translatable: true, label: 'Summary');
lynx()->fields->add('body', 'textarea', translatable: true, label: 'Body', settings: ['format' => 'html']);
lynx()->fields->add('published_on', 'date', label: 'Published on');

lynx()->templates->add('post', fields: [['name' => 'title', 'required' => true], 'summary', 'body', 'published_on'], label: 'Post');
lynx()->templates->add('blog', fields: ['title', 'summary'], label: 'Blog', singleton: true);

lynx()->templates->update('blog', ['allow_children' => true, 'child_templates' => ['post']]);
lynx()->templates->update('post', ['allow_children' => false, 'parent_templates' => ['blog']]);
// #endregion schema
