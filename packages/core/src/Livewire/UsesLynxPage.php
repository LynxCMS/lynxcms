<?php

declare(strict_types=1);

namespace LynxCms\Core\Livewire;

use Livewire\Attributes\Locked;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Pages\Page;

/**
 * For app Livewire components placed on Lynx pages (ADR-0021).
 *
 * Livewire update requests go to /livewire/update, which has no language prefix. This trait keeps
 * the page id and language as public properties and restores the language on every request, so
 * $this->page()->title and __() stay in the visitor's language.
 *
 * @api
 */
trait UsesLynxPage
{
    // Locked: they come from the CMS page, and visitors must not change them through a
    // crafted Livewire request (another page, another language, other component settings).
    #[Locked]
    public ?int $pageId = null;

    #[Locked]
    public ?string $lang = null;

    /** @var array<string, mixed> */
    #[Locked]
    public array $settings = [];

    public function mountUsesLynxPage(): void
    {
        $this->lang ??= app(LanguageContext::class)->current()->code;
        $this->restoreLynxLanguage();
    }

    public function hydrateUsesLynxPage(): void
    {
        $this->restoreLynxLanguage();
    }

    public function page(): ?Page
    {
        return $this->pageId === null ? null : lynx()->pages->get($this->pageId);
    }

    protected function restoreLynxLanguage(): void
    {
        if ($this->lang !== null && lynx()->languages->find($this->lang) !== null) {
            app(LanguageContext::class)->set($this->lang);
        }
    }
}
