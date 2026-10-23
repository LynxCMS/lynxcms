<?php

declare(strict_types=1);

namespace LynxCms\Seo\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\AdminServiceProvider;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Value;
use LynxCms\Seo\Actions\InstallSeo;
use LynxCms\Seo\Admin\SourceSuggestions;
use LynxCms\Seo\JsonLd\Mapping;
use LynxCms\Seo\JsonLd\Mappings;
use LynxCms\Seo\JsonLd\SchemaTypes;
use LynxCms\Seo\SeoSchema;

/**
 * The SEO screen's Structured data tab: each template mapped to a schema.org type, with a source
 * per property and the template's fields that fit suggested. Saves the settings page through
 * pages.save, so the mapping checks run there (ADR-0056).
 *
 * @internal
 */
final class SeoSchemas extends Component
{
    /** Livewire paths split on dots, so "offers.price" travels as "offers__price". */
    private const string DOT = '__';

    /** @var list<array{enabled: bool, template: string, type: string, lines: string}> */
    public array $mappings = [];

    /** The mapping being edited, by index; one past the end for a new one. */
    public ?int $editing = null;

    public string $template = '';

    public string $type = '';

    /** @var array<string, string> property (dots as DOT) => source */
    public array $sources = [];

    public function boot(): void
    {
        Gate::authorize(AdminServiceProvider::GATE);
        abort_unless(app(AdminAccess::class)->allows('seo.manage'), 403);
    }

    public function mount(): void
    {
        $this->mappings = array_values(Mappings::items(lynx()->settings(SeoSchema::SETTINGS_TEMPLATE)));
    }

    public function create(): void
    {
        $this->open(count($this->mappings), '', '', []);
    }

    public function edit(int $index): void
    {
        $mapping = $this->mappings[$index] ?? null;

        if ($mapping !== null) {
            $this->open($index, $mapping['template'], $mapping['type'], Mapping::parse($mapping['lines'])[0]);
        }
    }

    public function cancel(): void
    {
        $this->editing = null;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        if ($this->editing === null) {
            return;
        }

        $mappings = $this->mappings;
        $mappings[$this->editing] = ['enabled' => true, 'template' => $this->template, 'type' => $this->type, 'lines' => $this->lines()];

        if ($this->persist($mappings)) {
            $this->editing = null;
        }
    }

    public function remove(int $index): void
    {
        $mappings = $this->mappings;
        unset($mappings[$index]);
        $this->persist(array_values($mappings));
        $this->editing = null;
    }

    public function render(): View
    {
        $templates = lynx()->templates->all()->filter(fn (Template $template): bool => $template->hasUrl())->sortBy('name');

        return view('lynx-seo::admin.livewire.schemas', [
            'templates' => $templates->mapWithKeys(fn (Template $template): array => [$template->name => $template->labels()->in()])->all(),
            'types' => SchemaTypes::names(),
            'properties' => $this->properties(),
            'dot' => self::DOT,
        ]);
    }

    /**
     * The chosen type's properties, each with its kind and the sources that fit.
     *
     * @return list<array{property: string, key: string, kind: string, suggestions: list<string>}>
     */
    private function properties(): array
    {
        $suggestions = new SourceSuggestions($this->template, $this->type);
        $rows = [];

        foreach (SchemaTypes::properties($this->type) as $property => $kind) {
            $list = SchemaTypes::listOf($this->type, $property);
            $listSource = $list === null ? null : ($this->sources[str_replace('.', self::DOT, $list)] ?? '');
            $rows[] = ['property' => $property, 'key' => str_replace('.', self::DOT, $property), 'kind' => $kind, 'suggestions' => $suggestions->for($kind, $listSource)];
        }

        return $rows;
    }

    /** @param array<string, string> $sources */
    private function open(int $index, string $template, string $type, array $sources): void
    {
        $this->resetErrorBag();
        $this->editing = $index;
        $this->template = $template;
        $this->type = $type;
        $this->sources = [];

        foreach ($sources as $property => $source) {
            $this->sources[str_replace('.', self::DOT, $property)] = $source;
        }
    }

    /** "property: source" lines, in the catalogue's order, for the sources filled in. */
    private function lines(): string
    {
        $lines = [];

        foreach (array_keys(SchemaTypes::properties($this->type)) as $property) {
            $source = trim($this->sources[str_replace('.', self::DOT, $property)] ?? '');

            if ($source !== '') {
                $lines[] = $property.': '.$source;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Saves every mapping on the settings page; on a refusal shows why and keeps the form open.
     *
     * @param  list<array{enabled: bool, template: string, type: string, lines: string}>  $mappings
     */
    private function persist(array $mappings): bool
    {
        lynx()->run(InstallSeo::class);
        $items = Mappings::toItems($mappings);

        try {
            lynx()->run(SavePage::class, ['id' => lynx()->settings(SeoSchema::SETTINGS_TEMPLATE)?->id, 'values' => [SeoSchema::SCHEMAS => $items]]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                foreach (Value::strings($messages) as $message) {
                    $this->addError('mapping', $message);
                }
            }

            return false;
        }

        $this->mappings = array_values($mappings);

        return true;
    }
}
