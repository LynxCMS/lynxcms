<?php

declare(strict_types=1);

return [
    'no_url' => 'Template [:template] has no URL: search engines never see its pages.',
    'field' => 'Field [:field] exists with other settings; the SEO module needs [:needs].',
    'mapping' => [
        'type' => '[:type] is not a type Lynx maps. Use one of: :types.',
        'template' => 'Template [:template] does not exist or has no URL.',
        'twice' => 'Template [:template] is already mapped above.',
        'not_offered' => '[:type] is not a type structured data offers: use one of :types.',
        'line' => 'Line [:line]: write property: source.',
        'no_property' => ':type has no property [:property].',
        'organization' => '[:source] only fits properties that take a page, such as author, provider, organizer or worksFor.',
        'built_in' => '[:source] is not a built-in source here: use :sources.',
        'no_field' => 'The template has no field [:field].',
        'no_subfield' => 'The [:list] list has no subfield [:field].',
        'needs' => 'Property [:property] needs :kind; [:field] is a :type field.',
    ],
    'kinds' => [
        'image' => 'an image field',
        'date' => 'a date field',
        'number' => 'a number field',
        'page' => 'a page reference field',
        'list' => 'a repeater or matrix field',
        'enum' => 'a choice or text field',
        'lines' => 'a text field',
        'url' => 'a URL, text or page reference field',
        'text' => 'a text field',
    ],
];
