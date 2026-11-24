<?php

declare(strict_types=1);

// #region fields
lynx()->fields->create('partner', 'page', settings: ['multiple' => false]);
lynx()->fields->create('role', 'text', translatable: true);
lynx()->fields->create('credits', 'repeater', settings: [
    'fields' => [['name' => 'partner', 'required' => true], ['name' => 'role']],
    'item_label' => '{partner} · {role}',
]);
// #endregion fields
