<?php

declare(strict_types=1);

namespace LynxCms\Forms\Admin\Http;

use Illuminate\Http\Request;
use LynxCms\Admin\AdminAccess;
use LynxCms\Core\Support\Value;
use LynxCms\Forms\PersonalData;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Everything the forms hold about one email address, as a JSON file to hand the person
 * (ADR-0147). Only people who may manage forms download it.
 *
 * @internal
 */
final readonly class ExportPersonalData
{
    public function __construct(private AdminAccess $access, private PersonalData $data) {}

    public function __invoke(Request $request): StreamedResponse
    {
        abort_unless($this->access->allows('forms.manage'), 403);
        $email = trim(Value::string($request->query('email')));
        abort_unless(filter_var($email, FILTER_VALIDATE_EMAIL) !== false, 422);
        $json = json_encode($this->data->export($email), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)."\n";

        return response()->streamDownload(function () use ($json): void {
            echo $json;
        }, 'personal-data-'.now()->format('Y-m-d').'.json', ['Content-Type' => 'application/json; charset=UTF-8']);
    }
}
