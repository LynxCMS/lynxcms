<?php

declare(strict_types=1);

namespace LynxCms\Cache\Islands;

use Illuminate\Contracts\View\Factory as ViewFactory;
use InvalidArgumentException;
use Livewire\Features\SupportScriptsAndAssets\SupportScriptsAndAssets;
use Livewire\LivewireManager;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Support\Value;
use Throwable;

/**
 * Renders an island for the current visitor, in its page's language, with the page as $page.
 *
 * @internal
 */
final readonly class IslandRenderer
{
    public function __construct(private ViewFactory $views, private LanguageContext $languages) {}

    /** The island's HTML, or null when it cannot be rendered (the error is reported). */
    public function render(Island $island): ?string
    {
        try {
            return $this->languages->with($island->language, fn (): string => $this->html($island));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * The @assets blocks of the Livewire components rendered so far in this request, keyed as
     * Livewire keys them. On a full page Livewire puts them in <head> itself; islands arrive later,
     * so the refresh script adds them before it puts the islands in.
     *
     * @return array<string, string>
     */
    public function assets(): array
    {
        if (! class_exists(SupportScriptsAndAssets::class)) {
            return [];
        }

        $assets = [];

        foreach (Value::map(SupportScriptsAndAssets::getAssets()) as $key => $html) {
            $assets[$key] = Value::string($html);
        }

        return $assets;
    }

    private function html(Island $island): string
    {
        if ($island->type === Island::LIVEWIRE) {
            return Value::string(app(LivewireManager::class)->mount($island->name, $island->with));
        }

        if (! $this->views->exists($island->name)) {
            throw new InvalidArgumentException("Island view [{$island->name}] does not exist.");
        }

        $page = $island->pageId === null ? null : lynx()->pages->get($island->pageId);

        return $this->views->make($island->name, [...$island->with, 'page' => $page, 'lynx' => lynx()])->render();
    }
}
