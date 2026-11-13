<?php

declare(strict_types=1);

namespace LynxCms\Finder\Admin;

use Illuminate\Http\Request;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Http\SpreadsheetCell;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Finder\Catalog\ConditionCatalog;
use LynxCms\Finder\Catalog\Subject;
use LynxCms\Finder\FinderResults;
use LynxCms\Finder\Search\FinderQuery;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every page a finder search finds, as CSV: id, title, template, status, last change, address and
 * the search's field columns. The search comes from the request, as the screen's URL holds it, and
 * runs again here with the user's own view scope. No cell runs as a formula when opened in a
 * spreadsheet.
 *
 * @internal
 */
final readonly class ExportResults
{
    private const int CHUNK = 500;

    public function __construct(private AdminAccess $access, private FinderResults $results, private ConditionCatalog $catalog) {}

    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($this->access->allows('pages.view'), 403);
        $query = FinderQuery::fromArray(Value::array($request->query('q')));
        $columns = $this->catalog->chosenColumns($query->columns);

        return response()->streamDownload(
            fn () => $this->write($query, $columns),
            'pages-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    /**
     * @param  list<Subject>  $columns
     */
    private function write(FinderQuery $query, array $columns): void
    {
        $out = fopen('php://output', 'w');

        if ($out === false) {
            return;
        }

        $labels = array_map(fn (Subject $column): string => SpreadsheetCell::safe($column->label), $columns);
        fputcsv($out, ['ID', 'Title', 'Template', 'Status', 'Last changed', 'URL', ...$labels], escape: '');
        $keys = array_column($columns, 'key');
        $rows = new ResultRows;

        for ($start = 0; ($pages = $this->results->find($query, $start, self::CHUNK))->count() > 0; $start += self::CHUNK) {
            foreach ($pages as $page) {
                fputcsv($out, $this->line($rows, $page, $keys), escape: '');
            }
        }

        fclose($out);
    }

    /**
     * @param  list<string>  $columns
     * @return list<string>
     */
    private function line(ResultRows $rows, Page $page, array $columns): array
    {
        $row = $rows->row($page, $columns);

        return [
            (string) $row['id'],
            SpreadsheetCell::safe($row['title']),
            SpreadsheetCell::safe($row['template']),
            implode(', ', array_column($row['statuses'], 'key')),
            (string) $page->updatedAt,
            SpreadsheetCell::safe((string) $page->url),
            ...array_map(SpreadsheetCell::safe(...), array_values($row['cells'])),
        ];
    }
}
