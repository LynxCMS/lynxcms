<?php

declare(strict_types=1);

namespace LynxCms\Forms;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\Models\Submission;

/**
 * The private disk files sent with forms live on (lynx-forms.disk).
 *
 * @internal
 */
final class FormsFiles
{
    public function disk(): Filesystem
    {
        return Storage::disk(Value::string(config('lynx-forms.disk'), 'local'));
    }

    /** Removes the submissions' folders once the deleting transaction commits. */
    public function deleteAfterCommit(Submission ...$submissions): void
    {
        $folders = array_map(fn (Submission $submission): string => $submission->folder(), $submissions);

        DB::afterCommit(function () use ($folders): void {
            foreach ($folders as $folder) {
                $this->disk()->deleteDirectory($folder);
            }
        });
    }
}
