<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Http;

use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Forms\Models\Submission;
use LynxCms\Forms\SubmissionPresenter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A form's submissions as CSV, newest first: date, status, language, then one column per field,
 * read through each submission's own snapshot.
 *
 * @internal
 */
final readonly class ExportSubmissions
{
    private const int CHUNK = 200;

    public function __construct(private AdminAccess $access, private SubmissionPresenter $presenter) {}

    public function __invoke(string $form): StreamedResponse
    {
        abort_unless($this->access->allows('forms.view'), 403);
        $columns = $this->columns($form);

        return response()->streamDownload(function () use ($form, $columns): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            fputcsv($out, ['Date', 'Status', 'Language', 'Account', ...array_values($columns)], escape: '');
            Submission::query()->with('files')->where('form', $form)->orderByDesc('id')->chunk(self::CHUNK, function ($submissions) use ($out, $columns): void {
                foreach ($submissions as $submission) {
                    $values = array_column($this->presenter->lines($submission), 'value', 'name');
                    fputcsv($out, [
                        $submission->created_at->toDateTimeString(),
                        $submission->status,
                        (string) $submission->language()?->code,
                        (string) $submission->account(),
                        ...array_map(fn (string $name): string => $values[$name] ?? '', array_keys($columns)),
                    ], escape: '');
                }
            });
            fclose($out);
        }, "{$form}-submissions-".now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Field name => label, from the form if it is registered, else from its latest submission.
     *
     * @return array<string, string>
     */
    private function columns(string $form): array
    {
        $fields = lynx()->forms()->get($form)?->snapshot() ?? Submission::query()->where('form', $form)->latest('id')->first()?->fields ?? [];
        $columns = [];

        foreach ((array) $fields as $field) {
            $field = (array) $field;
            $columns[(string) ($field['name'] ?? '')] = LocalizedText::from($field['label'] ?? '')->in();
        }

        return $columns;
    }
}
