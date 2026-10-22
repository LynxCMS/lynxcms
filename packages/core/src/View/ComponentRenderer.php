<?php

declare(strict_types=1);

namespace LynxCms\Core\View;

use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;
use Livewire\Livewire;
use LynxCms\Core\Components\ComponentRegistry;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Renders the app component chosen in a "component" field (ADR-0021): @lynxComponent($page, 'form').
 * Livewire components receive pageId, lang and settings.
 *
 * @api
 */
final readonly class ComponentRenderer
{
    public function __construct(
        private ComponentRegistry $components,
        private LanguageContext $context,
        private ViewFactory $views,
    ) {}

    public function render(Page $page, string $field): string
    {
        $value = $page->get($field);

        if (! is_array($value) || ! isset($value['component'])) {
            return '';
        }

        $component = $this->components->find(Value::string($value['component']));

        if ($component === null) {
            return '';
        }

        $params = [
            'pageId' => $page->id,
            'lang' => $this->context->current()->code,
            'settings' => (array) ($value['settings'] ?? []),
        ];

        if (class_exists(Livewire::class)) {
            return Livewire::mount($component->component, $params);
        }

        if (! $this->views->exists($component->component)) {
            throw new InvalidArgumentException("Component [{$component->alias}] points to view [{$component->component}], which does not exist.");
        }

        return $this->views->make($component->component, $params)->render();
    }
}
