<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use LynxCms\Core\Models\Setting;
use LynxCms\Core\Support\Flushable;
use LynxCms\Core\Support\Value;

/**
 * The page shown, with status 404, for front-end URLs that match nothing (ADR-0085): a page in the
 * tree with its own template and fields, which editors write like any other. Which page it is
 * lives in a setting, set by pages.set_not_found; lynx:install creates one. A trashed or
 * unpublished page counts as none, and Laravel's own 404 answers instead. The id is read once per
 * request and again after every action, so the tree can ask for each row.
 *
 * @api
 */
final class NotFoundPage implements Flushable
{
    public const string SETTING = 'pages.not_found';

    /** The id read, 0 for none; null until read. */
    private ?int $id = null;

    public function __construct(private readonly PageLoader $loader, private readonly Publication $publication, private readonly Trash $trash) {}

    /** The chosen page's id, whatever its state. */
    public function id(): ?int
    {
        $this->id ??= Value::int(Setting::read(self::SETTING), 0);

        return $this->id > 0 ? $this->id : null;
    }

    public function flush(): void
    {
        $this->id = null;
    }

    /** The page to show, when it is there to show. */
    public function page(): ?Page
    {
        $id = $this->id();
        $page = $id === null || $this->trash->contains($id) ? null : $this->loader->find($id);

        return $page !== null && $page->template()->hasUrl() && $this->publication->isPublished($page) ? $page : null;
    }

    /** Whether anyone chose, a page or none: lynx:install only creates one when nobody did. */
    public function isChosen(): bool
    {
        return Setting::query()->whereKey(self::SETTING)->exists();
    }

    public function is(Page $page): bool
    {
        return $page->id === $this->id();
    }
}
