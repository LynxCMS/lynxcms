<?php

declare(strict_types=1);

return [
    'field' => [
        'missing' => 'Field [:field] does not exist.',
        'create_first' => 'Field [:field] does not exist. Create it first with lynx()->fields->add(\':field\', ...) or the fields.create action.',
        'problem' => 'Field [:field]: :problem',
        'not_html' => 'Field [:field] is not an html field.',
        'reserved' => 'The name ":name" is reserved, because pages and selectors already use it. Choose another name.',
        'name_max' => 'The field name may not be longer than :max characters.',
        'table' => 'A database table named :table already exists.',
        'unknown_type' => 'Unknown field type [:type]. Available: :types.',
        'not_translatable' => 'Fields of type [:type] cannot be translatable.',
        'translatable' => 'Field [:field] is translatable already.',
        'subfield_of' => 'Field [:field] is a subfield of :fields. Remove it there first.',
        'nested' => 'Field [:field] stores items itself; items cannot nest. Use depth instead.',
        'per_use' => 'Field [:field] can\'t set [:settings] where it is used: set it on the field.',
    ],
    'items' => [
        'no_fields' => 'List at least one field.',
        'no_types' => 'Add at least one block type.',
    ],
    'choice' => [
        'default' => 'The default is an option value, or a list of them.',
        'single' => 'A single choice takes one default.',
        'not_an_option' => 'The default [:default] is not one of the options.',
        'value' => '[:value] is not a valid option value: use lowercase letters, digits, dashes and underscores.',
        'twice' => 'The option value [:value] appears twice.',
    ],
    'formatter' => [
        'unknown' => 'Unknown formatter [:formatter]. Available: :formatters.',
        'type' => 'The formatter [:formatter] does not apply to :type fields.',
    ],
    'language' => [
        'delete_default' => 'The default language cannot be deleted.',
        'code_taken' => 'The code [:code] is already used by another language.',
        'prefix_taken' => 'The prefix [:prefix] is already used by another language.',
        'default_fallback' => 'The default language is the end of every fallback chain and cannot fall back to another language.',
        'self_fallback' => 'A language cannot fall back to itself.',
        'cycle' => 'Falling back to :language would create a cycle.',
    ],
    'text' => [
        'max' => 'The :attribute may not be longer than :max characters.',
        'type' => 'The :attribute must be text, or text per language code.',
        'language' => 'The :attribute uses the language [:language], which does not exist.',
        'language_max' => 'The :attribute in [:language] must be text of at most :max characters.',
    ],
    'editor' => [
        'display' => 'editor is one of :values.',
        'width' => 'width is one of :values.',
        'icon' => 'icon is a Lucide icon name, such as "calendar".',
        'required' => 'a :display field cannot be required: editors could not fill it.',
    ],
    'section' => [
        'missing' => 'Template [:template] has no section [:section].',
        'taken' => 'Template [:template] already has a section [:section].',
        'clashes' => 'Template [:template] already has [:fields]. Rename the fields that clash, or remove them from that template first.',
        'prefix' => 'Field [:field] already exists: choose another prefix.',
        'key' => 'The section key [:section] must be lowercase letters, digits, - and _, starting with a letter.',
        'twice' => 'The section [:section] appears twice.',
        'nested' => 'Sections cannot be inside sections: [:nested] is inside [:section].',
        'place' => 'Field [:field] is in section [:section]: set the tab and column on the section.',
    ],
    'layout' => [
        'field' => 'Field [:name]',
        'section' => 'Section [:name]',
        'tab' => 'Tab [:name]',
        'problem' => ':what: :problem',
        'no_section' => 'Field [:field] names section [:section], which this template does not have.',
        'no_tab' => ':what names tab [:tab], which this template does not have.',
        'tab_key' => 'The tab key [:tab] must be unique, lowercase letters, digits, - and _, and not one of: :reserved.',
        'column' => ':what: the column is main or sidebar, not [:column].',
    ],
    'condition' => [
        'problem' => 'The :setting of [:field]: :problem',
        'itself' => 'The :setting of [:field] cannot depend on itself.',
        'missing' => 'The :setting of [:field] reads [:fields], which is not on this template.',
        'unreadable' => 'Cannot read the condition [:condition]: write it as field=value, for example format=online|hybrid.',
        'no_value' => 'Cannot read the condition [:condition]: [:operator] needs a value.',
    ],
    'usage' => [
        'trashed' => 'in the trash',
        'more' => ', and :count more',
        'refusal' => 'Template [:template] is used by :count page: :pages. Switch them to another template (pages.switch_template) or delete them, then delete the template.|Template [:template] is used by :count pages: :pages. Switch them to another template (pages.switch_template) or delete them, then delete the template.',
    ],
];
