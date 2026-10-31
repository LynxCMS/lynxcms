<?php

declare(strict_types=1);

return [
    'groups' => [
        'text' => 'Text',
        'numbers' => 'Numbers and dates',
        'choices' => 'Choices',
        'media' => 'Pages and media',
        'structure' => 'Structure',
        'other' => 'Other',
    ],
    'text' => [
        'label' => 'Single line',
        'description' => 'A title, a name, a short phrase.',
    ],
    'textarea' => [
        'label' => 'Paragraphs',
        'description' => 'Plain text, rich text or Markdown.',
    ],
    'url' => [
        'label' => 'Web address',
        'description' => 'A link to a page or another site.',
    ],
    'email' => [
        'label' => 'Email address',
        'description' => 'Checked when the page is saved.',
    ],
    'integer' => [
        'label' => 'Whole number',
        'description' => 'A count, a duration, a price in cents.',
    ],
    'decimal' => [
        'label' => 'Decimal number',
        'description' => 'A price or a rating.',
    ],
    'date' => [
        'label' => 'Date',
        'description' => 'With or without a time.',
    ],
    'time' => [
        'label' => 'Time of day',
        'description' => 'Such as 09:30.',
    ],
    'choice' => [
        'label' => 'Choice',
        'description' => 'One or more options from a list you set.',
    ],
    'toggle' => [
        'label' => 'On or off',
        'description' => 'Yes or no, such as "Featured".',
    ],
    'icon' => [
        'label' => 'Icon',
        'description' => 'One of the Lucide icons.',
    ],
    'page' => [
        'label' => 'Pages',
        'description' => 'Links to other pages, such as related courses.',
    ],
    'page_table' => [
        'label' => 'Table of pages',
        'description' => 'Pages made for this one, such as sessions.',
    ],
    'images' => [
        'label' => 'Images',
        'description' => 'With focus points and descriptions.',
    ],
    'files' => [
        'label' => 'Files',
        'description' => 'PDFs and documents.',
    ],
    'repeater' => [
        'label' => 'Repeater',
        'description' => 'A list of items with the same fields.',
    ],
    'matrix' => [
        'label' => 'Blocks',
        'description' => 'A page built from blocks of different kinds.',
    ],
    'component' => [
        'label' => 'App component',
        'description' => 'A part of the app, such as a booking form.',
    ],
];
