<?php

declare(strict_types=1);

return [
    'form' => [
        'name' => 'Use lowercase letters, digits and dashes, starting with a letter, such as contact-us (at most :max characters).',
        'taken' => 'A form named [:form] already exists.',
        'no_root' => 'The forms page is missing.',
        'failed' => 'The form could not be created.',
        'missing' => 'There is no form [:form].',
    ],
    'field' => 'Field [:field] exists with other settings; the form builder needs [:needs].',
    'submission' => 'There is no submission [:submission].',
    'import' => [
        'value' => 'Fields of type [:type] have no setting [:value].',
    ],
    'builder' => [
        'indented' => 'Indented blocks belong to a group: add a group above it, or outdent it.',
        'nested' => 'A group cannot hold a group or a step.',
        'no_fields' => 'Add at least one field before publishing the form.',
        'code_form' => 'A form defined in code already uses the name [:form].',
        'name' => '[:name] cannot be a field name: use lowercase letters, digits and underscores, starting with a letter.',
        'name_taken' => 'Another field is already named [:name]; give this one its own name.',
        'no_options' => 'Add at least one option.',
        'show_if' => 'There is no field [:field] before this one.',
        'number' => 'Compare with a number, e.g. ">= 18".',
    ],
];
