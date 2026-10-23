<?php

declare(strict_types=1);

// The blog page and two posts, as in "Your First Site".

// #region pages
$blog = lynx()->pages->new('blog', '/');
$blog->name = 'blog';
$blog->title = 'Blog';
$blog->summary = 'Notes from the team.';
$blog->save();

$post = lynx()->pages->new('post', '/blog');
$post->name = 'hello-world';
$post->title = 'Hello, world';
$post->summary = 'Our first post.';
$post->body = '<p>Welcome to our new <strong>blog</strong>.</p>';
$post->published_on = '2026-10-01';
$post->save();
// #endregion pages

$second = lynx()->pages->new('post', '/blog');
$second->name = 'second-post';
$second->title = 'A second post';
$second->summary = 'More news.';
$second->body = '<p>Another post.</p>';
$second->published_on = '2026-10-15';
$second->save();
