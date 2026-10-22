<?php

declare(strict_types=1);

namespace LynxCms\Core;

use Closure;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Traits\Macroable;
use InvalidArgumentException;
use LynxCms\Contracts\Actions\Actor;
use LynxCms\Core\Actions\ActionRegistry;
use LynxCms\Core\Actions\Runner\ActionResult;
use LynxCms\Core\Actions\Runner\ActionRunner;
use LynxCms\Core\Actions\Runner\HookBuilder;
use LynxCms\Core\Actions\Runner\HookRegistry;
use LynxCms\Core\Api\FieldsApi;
use LynxCms\Core\Api\LanguagesApi;
use LynxCms\Core\Api\PagesApi;
use LynxCms\Core\Api\TemplatesApi;
use LynxCms\Core\Components\ComponentRegistry;
use LynxCms\Core\Fields\FieldTypeRegistry;
use LynxCms\Core\Fields\FormatterRegistry;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\SettingsPages;
use LynxCms\Core\Permissions\PermissionRegistry;

/**
 * The API root, in the spirit of ProcessWire's API variables:
 *
 *   lynx()->languages, lynx()->fields, lynx()->templates, lynx()->pages
 *
 * Packages add methods with Lynx::macro(), e.g. lynx()->ai() from lynxcms/ai.
 *
 * @property-read LanguagesApi $languages
 * @property-read FieldsApi $fields
 * @property-read TemplatesApi $templates
 * @property-read PagesApi $pages
 *
 * @api
 */
final class Lynx
{
    use Macroable {
        __call as macroCall;
    }

    public function __construct(private readonly Container $container) {}

    public function __get(string $name): mixed
    {
        return match ($name) {
            'languages' => $this->container->make(LanguagesApi::class),
            'fields' => $this->container->make(FieldsApi::class),
            'templates' => $this->container->make(TemplatesApi::class),
            'pages' => $this->container->make(PagesApi::class),
            default => throw new InvalidArgumentException("Unknown Lynx API variable [{$name}]."),
        };
    }

    /**
     * Run any action by class name, e.g. lynx()->run(CreateField::class, [...], dryRun: true).
     *
     * @param  class-string  $action
     * @param  array<string, mixed>  $input
     */
    public function run(string $action, array $input = [], bool $dryRun = false): ActionResult
    {
        return $this->container->make(ActionRunner::class)->run($action, $input, $dryRun);
    }

    /**
     * Run a callback in another language: lynx()->language('pt', fn () => $page->title).
     *
     * @template T
     *
     * @param  Closure(Language): T  $callback
     * @return T
     */
    public function language(Language|string $language, Closure $callback): mixed
    {
        return $this->container->make(LanguageContext::class)->with($language, $callback);
    }

    /** Global content stored on a singleton page without URL (ADR-0008). */
    public function settings(string $template = 'settings'): ?Page
    {
        return $this->container->make(SettingsPages::class)->get($template);
    }

    /**
     * Hook into an action: Lynx::hook(SavePage::class)->before(...)->after(...).
     *
     * @param  class-string|'*'  $action
     */
    public function hook(string $action): HookBuilder
    {
        return new HookBuilder($this->container->make(HookRegistry::class), $action);
    }

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function actingAs(Actor $actor, Closure $callback): mixed
    {
        return $this->container->make(ActionRunner::class)->actingAs($actor, $callback);
    }

    public function actions(): ActionRegistry
    {
        return $this->container->make(ActionRegistry::class);
    }

    public function fieldTypes(): FieldTypeRegistry
    {
        return $this->container->make(FieldTypeRegistry::class);
    }

    /** Permissions roles can grant (ADR-0033): lynx()->permissions()->register('reports.view', 'View reports'). */
    public function permissions(): PermissionRegistry
    {
        return $this->container->make(PermissionRegistry::class);
    }

    /** Text formatters (ADR-0031): lynx()->formatters()->register(MyFormatter::class). */
    public function formatters(): FormatterRegistry
    {
        return $this->container->make(FormatterRegistry::class);
    }

    public function components(): ComponentRegistry
    {
        return $this->container->make(ComponentRegistry::class);
    }
}
