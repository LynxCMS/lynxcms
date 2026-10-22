<?php

declare(strict_types=1);

namespace LynxCms\Cache\View;

use Closure;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\View\Component;
use Livewire\Features\SupportAutoInjectedAssets\SupportAutoInjectedAssets;
use LynxCms\Cache\Islands\Island;
use LynxCms\Cache\Pages\PageRequest;
use LynxCms\Core\Http\PageAttributes;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * <x-lynx-island view="partials.cart" :with="[...]">Loading…</x-lynx-island>, or
 * livewire="lynx-form": a part of the page rendered for each visitor (ADR-0064). On a page that
 * may be stored it prints a placeholder, filled after load by the refresh script; anywhere else
 * it renders in place.
 *
 * @api
 */
final class IslandComponent extends Component
{
    /** @param array<string, mixed> $with */
    public function __construct(public ?string $view = null, public ?string $livewire = null, public array $with = []) {}

    public function render(): Closure
    {
        return fn (array $data): View|string => $this->isDeferred()
            ? $this->placeholder($this->fallback($data['slot'] ?? null))
            : view('lynx-cache::components.island', ['view' => $this->view, 'livewire' => $this->livewire, 'with' => $this->with, 'page' => $this->page()]);
    }

    /** Only pages that may be stored defer islands; signed in users and the rest see them at once. */
    private function isDeferred(): bool
    {
        $request = app(Request::class);

        return config('lynx-cache.pages.enabled') === true
            && $request->attributes->get(PageRequest::ATTRIBUTE) instanceof PageRequest
            && $request->user() === null;
    }

    /** What the slot holds, shown until the island arrives. */
    private function fallback(mixed $slot): string
    {
        return $slot instanceof Htmlable ? $slot->toHtml() : Value::string($slot);
    }

    private function placeholder(string $fallback): string
    {
        $type = $this->livewire === null ? Island::VIEW : Island::LIVEWIRE;
        $island = new Island($type, (string) ($this->livewire ?? $this->view), $this->with, $this->page()?->id, app(LanguageContext::class)->current()->code);

        if ($type === Island::LIVEWIRE && class_exists(SupportAutoInjectedAssets::class)) {
            // No component renders on the stored page, so Livewire would leave its script out.
            SupportAutoInjectedAssets::$forceAssetInjection = true;
        }

        return '<lynx-island data-island="'.e($island->encrypt()).'">'.$fallback.'</lynx-island>';
    }

    private function page(): ?Page
    {
        $page = app(Request::class)->attributes->get(PageAttributes::PAGE) ?? view()->shared('page');

        return $page instanceof Page ? $page : null;
    }
}
