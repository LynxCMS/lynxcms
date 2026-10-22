<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Schema\SchemaSync;
use LynxCms\Core\Support\LocalizedText;
use Symfony\Component\Yaml\Yaml;

beforeEach(function (): void {
    courseSite();
    lynx()->fields->add('role', 'text', translatable: true, label: ['default' => 'Role', 'pt' => 'Função'], settings: [
        'description' => ['default' => 'What they did', 'pt' => 'O que fizeram'],
        'notes' => 'Short',
    ]);
});

it('keeps plain string labels working', function (): void {
    expect(lynx()->fields->get('price')->label)->toBe('Price')
        ->and(lynx()->fields->get('price')->labels()->toStorage())->toBe('Price');
});

it('shows a label in the current language, falling back along the chain', function (): void {
    lynx()->languages->add('pt-br', 'Português (Brasil)', fallback: 'pt');

    expect(lynx()->fields->get('role')->label)->toBe('Role')
        ->and(lynx()->language('pt', fn () => lynx()->fields->get('role')->label))->toBe('Função')
        ->and(lynx()->language('pt-br', fn () => lynx()->fields->get('role')->label))->toBe('Função');
});

it('translates descriptions and notes', function (): void {
    $field = lynx()->fields->get('role');

    expect(lynx()->language('pt', fn () => $field->description()))->toBe('O que fizeram')
        ->and($field->description())->toBe('What they did')
        ->and(lynx()->language('pt', fn () => $field->notes()))->toBe('Short');
});

it('translates template labels and the labels a template gives its fields', function (): void {
    lynx()->templates->add('project', fields: [['name' => 'title'], ['name' => 'role', 'label' => ['default' => 'Credit', 'pt' => 'Crédito']]], label: ['default' => 'Project', 'pt' => 'Projeto']);

    expect(lynx()->language('pt', fn () => lynx()->templates->get('project')->label))->toBe('Projeto')
        ->and(lynx()->language('pt', fn () => lynx()->templates->fields('project')->get('role')?->label()))->toBe('Crédito')
        ->and(lynx()->templates->fields('project')->get('role')?->label())->toBe('Credit');
});

it('rejects labels in languages that do not exist', function (): void {
    lynx()->fields->update('role', ['label' => ['default' => 'Role', 'xx' => 'Nope']]);
})->throws(ValidationException::class);

it('follows a language code rename', function (): void {
    lynx()->templates->add('project', fields: [['name' => 'role', 'label' => ['pt' => 'Crédito']]], label: ['pt' => 'Projeto']);
    lynx()->languages->update('pt', ['code' => 'pt-pt']);

    expect(lynx()->fields->get('role')->labels()->toStorage())->toBe(['default' => 'Role', 'pt-pt' => 'Função'])
        ->and(lynx()->fields->get('role')->config()['description'])->toBe(['default' => 'What they did', 'pt-pt' => 'O que fizeram'])
        ->and(lynx()->templates->get('project')->labels()->toStorage())->toBe(['pt-pt' => 'Projeto'])
        ->and(lynx()->language('pt-pt', fn () => lynx()->templates->fields('project')->get('role')?->label()))->toBe('Crédito');
});

it('carries translated labels through the schema files', function (): void {
    $dir = sys_get_temp_dir().'/lynx-labels-'.uniqid();
    app(SchemaSync::class)->export($dir);

    expect(Yaml::parseFile("{$dir}/fields/role.yaml")['label'])->toBe(['default' => 'Role', 'pt' => 'Função']);

    $file = Yaml::parseFile("{$dir}/fields/role.yaml");
    $file['label']['pt'] = 'Papel';
    File::put("{$dir}/fields/role.yaml", Yaml::dump($file, 4));
    app(SchemaSync::class)->apply($dir);
    File::deleteDirectory($dir);

    expect(lynx()->fields->get('role')->labels()->toStorage())->toBe(['default' => 'Role', 'pt' => 'Papel']);
});

it('resolves localized text on its own', function (): void {
    $text = LocalizedText::from(['pt' => 'Olá']);

    expect($text->in('default'))->toBe('Olá')
        ->and(LocalizedText::from('{"pt":"Olá"}')->in('pt'))->toBe('Olá')
        ->and(LocalizedText::from(null)->in())->toBe('');
});
