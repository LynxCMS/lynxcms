<?php

declare(strict_types=1);

namespace LynxCms\Forms\Builder;

use LynxCms\Core\Pages\Page;
use LynxCms\Forms\FormSchema;

/**
 * The builder's published forms, by key (the form page's name). Each is compiled once per page
 * revision, so a form read many times in a request costs one compile, and an edit is picked up.
 *
 * @internal
 */
final class PageForms
{
    /** @var array<string, PageForm> "page id:revision" => form */
    private array $compiled = [];

    public function __construct(private readonly PageFormCompiler $compiler) {}

    /** A published form, or with $includeUnpublished also one being built (the admin's preview). */
    public function find(string $key, bool $includeUnpublished = false): ?PageForm
    {
        if (! $this->isInstalled() || preg_match(FormSchema::NAME_PATTERN, $key) !== 1) {
            return null;
        }

        // By the key, the default language's name, whatever the request's language (FormSchema::key()).
        $page = lynx()->pages->findOne('template='.FormSchema::FORM_TEMPLATE.', name='.$key.', language='.lynx()->languages->default()->code.', include='.($includeUnpublished ? 'all' : 'hidden'));

        return $page instanceof Page ? $this->compiled($page) : null;
    }

    /**
     * @return array<string, PageForm>
     */
    public function all(): array
    {
        if (! $this->isInstalled()) {
            return [];
        }

        $forms = [];

        foreach (lynx()->pages->find('template='.FormSchema::FORM_TEMPLATE.', include=hidden, sort=title') as $page) {
            if ($page instanceof Page) {
                $form = $this->compiled($page);
                $forms[$form->key()] = $form;
            }
        }

        return $forms;
    }

    private function compiled(Page $page): PageForm
    {
        return $this->compiled[$page->id.':'.$page->revision] ??= $this->compiler->compile($page);
    }

    private function isInstalled(): bool
    {
        return lynx()->templates->find(FormSchema::FORM_TEMPLATE) !== null;
    }
}
