<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Languages\CreateLanguage;
use LynxCms\Core\Lynx;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Schema\SchemaSync;
use Symfony\Component\Yaml\Yaml;

beforeEach(fn (): Lynx => installLynx());

it('labels tabs with the name until a label is set', function (): void {
    $pt = lynx()->languages->add('pt', 'Português');

    expect($pt->label)->toBeNull()->and($pt->tabLabel())->toBe('Português');
});

it('sets and clears the tab label', function (): void {
    lynx()->languages->add('pt', 'Português');

    expect(lynx()->languages->update('pt', ['label' => 'PT'])->tabLabel())->toBe('PT')
        ->and(lynx()->languages->update('pt', ['label' => ''])->label)->toBeNull();
});

it('takes the label when creating a language', function (): void {
    $result = lynx()->run(CreateLanguage::class, ['code' => 'pt', 'name' => 'Português', 'label' => 'pt-PT']);

    expect($result->valueAs(Language::class)->tabLabel())->toBe('pt-PT');
});

it('rejects a label longer than 32 characters', function (): void {
    lynx()->languages->add('pt', 'Português');

    lynx()->languages->update('pt', ['label' => str_repeat('x', 33)]);
})->throws(ValidationException::class);

it('syncs the label with the schema files', function (): void {
    $dir = sys_get_temp_dir().'/lynx-label-'.uniqid();
    lynx()->languages->add('pt', 'Português', fallback: 'default');
    lynx()->languages->update('pt', ['label' => 'PT']);
    app(SchemaSync::class)->export($dir);

    expect(Yaml::parseFile("{$dir}/languages/pt.yaml")['label'])->toBe('PT');

    $file = Yaml::parseFile("{$dir}/languages/pt.yaml");
    $file['label'] = 'Port.';
    File::put("{$dir}/languages/pt.yaml", Yaml::dump($file));
    app(SchemaSync::class)->apply($dir);
    File::deleteDirectory($dir);

    expect(lynx()->languages->get('pt')->label)->toBe('Port.');
});
