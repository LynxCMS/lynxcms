<?php

declare(strict_types=1);

namespace LynxCms\Forms\Definition;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;
use LynxCms\Core\Support\Value;
use Symfony\Component\Mime\MimeTypes;

/**
 * A file's content must fit its name: the type read from its bytes must be one that extension
 * names, so a program renamed to invoice.pdf is refused. Content the reader can't name
 * (application/octet-stream) passes, as programs and scripts are recognized.
 *
 * @internal
 */
final readonly class ContentMatchesExtension implements ValidationRule
{
    /** Types read from formats built on another: Office and OpenDocument files are zip archives, old Office files OLE storage, CSV plain text. */
    private const array ALSO = [
        'application/zip' => ['docx', 'xlsx', 'pptx', 'odt', 'ods', 'odp'],
        'application/x-ole-storage' => ['doc', 'xls', 'ppt'],
        'application/CDFV2' => ['doc', 'xls', 'ppt'],
        'application/vnd.ms-office' => ['doc', 'xls', 'ppt'],
        'text/plain' => ['csv', 'txt'],
        'text/csv' => ['csv', 'txt'],
    ];

    private const string UNKNOWN = 'application/octet-stream';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! $value instanceof UploadedFile) {
            return;
        }

        $mime = (string) $value->getMimeType();
        $extension = strtolower($value->getClientOriginalExtension());

        if ($mime === self::UNKNOWN || in_array($extension, [...MimeTypes::getDefault()->getExtensions($mime), ...(self::ALSO[$mime] ?? [])], true)) {
            return;
        }

        $fail(Value::string(__('lynx-forms::validation.file_content', ['extension' => $extension])));
    }
}
