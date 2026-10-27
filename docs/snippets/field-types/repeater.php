<?php

declare(strict_types=1);

// #region fields
lynx()->fields->add('partner', 'page', settings: ['multiple' => false]);
lynx()->fields->add('role', 'text', translatable: true);
lynx()->fields->add('credits', 'repeater', settings: [
    'fields' => [['name' => 'partner', 'required' => true], ['name' => 'role']],
    'item_label' => '{partner} · {role}',
]);
// #endregion fields
