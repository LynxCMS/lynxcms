<?php

declare(strict_types=1);

namespace LynxCms\Admin\Editor;

use Closure;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;

/**
 * Files the link dialog of rich text fields offers under "Select file", for the page being
 * edited. Modules that store files add a source, e.g. the media package lists the page's files
 * and images: app(LinkFiles::class)->add(fn (Page $page): array => [['name' => 'a.pdf', 'url' => '/media/1/a.pdf']]).
 *
 * @api
 */
final class LinkFiles
{
    /** @var list<Closure(Page): iterable<mixed>> */
    private array $sources = [];

    /**
     * @param  Closure(Page): iterable<mixed>  $source  returns rows of name and url
     */
    public function add(Closure $source): void
    {
        $this->sources[] = $source;
    }

    /**
     * @return list<array{name: string, url: string}>
     */
    public function for(Page $page): array
    {
        $files = [];

        foreach ($this->sources as $source) {
            foreach ($source($page) as $file) {
                $row = Value::map($file);
                $url = Value::string($row['url'] ?? '');

                if ($url !== '') {
                    $files[] = ['name' => Value::string($row['name'] ?? basename($url)), 'url' => $url];
                }
            }
        }

        return $files;
    }
}
