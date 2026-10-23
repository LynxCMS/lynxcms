<?php

declare(strict_types=1);

namespace LynxCms\Core\Pages;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use LynxCms\Core\Models\Template;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;

/**
 * The pages that use a template, which keep it from being deleted: every page, published or not,
 * in the trash too. Drafts don't count; they belong to their page.
 *
 * @api
 */
final readonly class TemplateUsage
{
    /** How many pages a refusal names before it counts the rest. */
    public const int NAMED = 10;

    public function __construct(private Trash $trash) {}

    public function count(Template $template): int
    {
        return $this->query($template)->count();
    }

    /**
     * The first pages that use the template, oldest first.
     *
     * @return list<array{id: int, title: string, url: string, trashed: bool}>
     */
    public function pages(Template $template, int $limit): array
    {
        $ids = $this->query($template)->orderBy('id')->limit($limit)->pluck('id')->map(Value::int(...))->all();

        return array_values(array_filter(array_map($this->describe(...), $ids)));
    }

    /** Why templates.delete refuses, naming the pages and the way out. */
    public function refusal(Template $template): string
    {
        $count = $this->count($template);
        $named = array_map(fn (array $page): string => $page['title'].($page['trashed'] ? ' (in the trash)' : " ({$page['url']})"), $this->pages($template, self::NAMED));
        $more = $count > count($named) ? ', and '.($count - count($named)).' more' : '';

        return "Template [{$template->name}] is used by {$count} ".($count === 1 ? 'page' : 'pages').': '.implode(', ', $named).$more.'. '
            .'Switch them to another template (pages.switch_template) or delete them, then delete the template.';
    }

    private function query(Template $template): Builder
    {
        return DB::table(Tables::PAGES)->where('template_id', $template->id)->whereNull('draft_of');
    }

    /** @return array{id: int, title: string, url: string, trashed: bool}|null */
    private function describe(int $id): ?array
    {
        $page = lynx()->pages->get($id);

        if (! $page instanceof Page) {
            return null;
        }

        $title = $page->templateField('title') !== null ? Value::string($page->getUnformatted('title')) : '';

        return ['id' => $id, 'title' => $title === '' ? $page->name() : $title, 'url' => $page->url(), 'trashed' => $this->trash->contains($id)];
    }
}
