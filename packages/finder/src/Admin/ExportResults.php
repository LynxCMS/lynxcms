<?php

declare(strict_types=1);

namespace LynxCms\Finder\Admin;

use Illuminate\Http\Request;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Http\SpreadsheetCell;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Finder\FinderResults;
use LynxCms\Finder\Search\FinderQuery;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Every page a finder search finds, as CSV: id, title, template, status, last change and address.
 * The search comes from the request, as the screen's URL holds it, and runs again here with the
 * user's own view scope. Titles never run as formulas when opened in a spreadsheet.
 *
 * @internal
 */
final readonly class ExportResults
{
    private const int CHUNK = 500;

    public function __construct(private AdminAccess $access, private FinderResults $results, private ResultRows $rows = new ResultRows) {}

    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($this->access->allows('pages.view'), 403);
        $query = FinderQuery::fromArray(Value::array($request->query('q')));

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['ID', 'Title', 'Template', 'Status', 'Last changed', 'URL'], escape: '');

            for ($start = 0; ($pages = $this->results->find($query, $start, self::CHUNK))->count() > 0; $start += self::CHUNK) {
                foreach ($pages as $page) {
                    fputcsv($out, $this->line($page), escape: '');
                }
            }

            fclose($out);
        }, 'pages-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return list<string>
     */
    private function line(Page $page): array
    {
        $row = $this->rows->row($page);

        return [
            (string) $row['id'],
            SpreadsheetCell::safe($row['title']),
            SpreadsheetCell::safe($row['template']),
            implode(', ', array_column($row['statuses'], 'key')),
            (string) $page->updatedAt,
            SpreadsheetCell::safe((string) $page->url),
        ];
    }
}
