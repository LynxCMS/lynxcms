<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Http;

use LynxCms\Admin\AdminAccess;
use LynxCms\Forms\Transfer\FormExporter;
use LynxCms\Forms\Transfer\FormTransferError;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A builder form's structure as a JSON file (ADR-0146), for Import form on another site. Only
 * people who may build forms download it, as it is a way to copy one.
 *
 * @internal
 */
final readonly class DownloadFormStructure
{
    public function __construct(private AdminAccess $access, private FormExporter $exporter) {}

    public function __invoke(string $form): StreamedResponse
    {
        abort_unless($this->access->allows('forms.manage'), 403);

        try {
            $json = $this->exporter->toJson($form);
        } catch (FormTransferError) {
            abort(404);
        }

        return response()->streamDownload(function () use ($json): void {
            echo $json;
        }, "{$form}.form.json", ['Content-Type' => 'application/json; charset=UTF-8']);
    }
}
