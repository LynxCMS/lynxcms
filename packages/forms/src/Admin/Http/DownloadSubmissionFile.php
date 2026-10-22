<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Http;

use LynxCms\Admin\AdminAccess;
use LynxCms\Forms\FormsFiles;
use LynxCms\Forms\Models\Submission;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A file sent with a submission, from the private forms disk, for users who may see submissions.
 *
 * @internal
 */
final readonly class DownloadSubmissionFile
{
    public function __construct(private AdminAccess $access, private FormsFiles $files) {}

    public function __invoke(string $submission, int $file): StreamedResponse
    {
        abort_unless($this->access->allows('forms.view'), 403);
        $stored = Submission::query()->where('uuid', $submission)->firstOrFail()->files()->whereKey($file)->firstOrFail();

        abort_unless($this->files->disk()->exists($stored->path), 404);

        return $this->files->disk()->download($stored->path, $stored->name);
    }
}
