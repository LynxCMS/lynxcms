---
title: A quick tour
description: Languages, fields, templates and pages in a few lines of PHP.
order: 2
---

# A quick tour

`lynx()` (or the `Lynx` facade) is the entry point.

## Languages

```php
$lynx = lynx();

$lynx->languages->update('default', ['name' => 'English', 'locale' => 'en']);
$lynx->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'default');
```

## Fields and templates

```php
$lynx->fields->add('body', 'textarea', translatable: true, settings: ['format' => 'html']);
$lynx->fields->add('price', 'integer');
$lynx->templates->add('course', fields: [['name' => 'title', 'required' => true], 'body', 'price']);
$lynx->templates->add('courses', fields: ['title']);
```

## Pages

```php
$courses = $lynx->pages->new('courses', '/');
$courses->name = 'courses';
$courses->title = 'Courses';
$courses->setLanguageName('pt', 'cursos');
$courses->save();

$page = $lynx->pages->new('course', '/courses/');
$page->name = 'delivery-8';
$page->title = 'Navigating Project Delivery';
$page->body = '<p>Only in English for now.</p>';
$page->price = 249;
$page->setLanguageName('pt', 'navegando-entrega-8');
$page->setLanguageValue('pt', 'title', 'Navegando pela Entrega de Projetos');
$page->save();
```

## Reading, with fallback per field

```php
$lynx->language('pt', function () use ($page) {
    echo $page->title;              // Navegando pela Entrega de Projetos
    echo $page->body;               // the English body (fallback)
    echo $page->body->isFallback(); // true
    echo $page->url;                // /pt/cursos/navegando-entrega-8/
});
```

## Finding pages

```php
$lynx->pages->find('template=course, price<300, title%=Delivery, sort=-title, limit=10');
```

## Previewing a write

Every write can run as a dry run, which returns the changes it would make:

```php
$result = $lynx->pages->delete('/courses/', dryRun: true);
$result->changes->summary();
```
