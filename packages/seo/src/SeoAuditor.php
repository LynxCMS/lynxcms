<?php

declare(strict_types=1);

namespace LynxCms\Seo;

use LynxCms\Core\Models\Template;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Finds published pages whose SEO needs work, in the current language: no description, a title
 * another page also uses, or a title search engines cut short.
 *
 * @api
 */
final readonly class SeoAuditor
{
    /** Search results show about this many characters of a title. */
    public const int TITLE_LENGTH = 60;

    public function __construct(private SeoResolver $resolver) {}

    /**
     * @return array{checked: int, rows: list<array{id: int, title: string, url: string, issues: list<string>}>}
     */
    public function audit(): array
    {
        $seo = [];

        foreach ($this->pages() as $page) {
            $seo[(int) $page->id] = [$page, $this->resolver->resolve($page)];
        }

        $titles = array_count_values(array_map(fn (array $entry): string => mb_strtolower($entry[1]->title), $seo));
        $rows = [];

        foreach ($seo as $id => [$page, $data]) {
            $issues = $this->issues($data, $titles[mb_strtolower($data->title)] - 1);

            if ($issues !== []) {
                $rows[] = ['id' => $id, 'title' => $data->title, 'url' => $page->url(), 'issues' => $issues];
            }
        }

        return ['checked' => count($seo), 'rows' => $rows];
    }

    /** @return list<string> */
    private function issues(SeoData $data, int $sameTitles): array
    {
        return array_values(array_filter([
            $data->description === '' ? Value::string(__('lynx-seo::admin.audit.no_description')) : null,
            $sameTitles > 0 ? trans_choice('lynx-seo::admin.audit.same_title', $sameTitles) : null,
            mb_strlen($data->title) > self::TITLE_LENGTH ? Value::string(__('lynx-seo::admin.audit.long_title', ['length' => self::TITLE_LENGTH])) : null,
        ]));
    }

    /** @return list<Page> published pages with URLs, up to lynx-seo.audit_limit */
    private function pages(): array
    {
        $templates = lynx()->templates->all()->filter(fn (Template $template): bool => $template->hasUrl())->pluck('name')->all();

        if ($templates === []) {
            return [];
        }

        $limit = Value::int(config('lynx-seo.audit_limit'), 2000);
        $pages = lynx()->pages->find('template='.implode('|', Value::strings($templates)).', sort=id, limit='.$limit);

        return array_values(array_filter($pages->all(), fn (mixed $page): bool => $page instanceof Page));
    }
}
