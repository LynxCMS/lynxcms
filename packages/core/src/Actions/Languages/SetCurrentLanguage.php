<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Languages;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Languages\LanguageContext;
use LynxCms\Core\Models\Language;
use LynxCms\Core\Support\Value;

/**
 * Sets the language for the rest of the request and Laravel's locale. With remember: true the
 * choice is stored in the session and on the logged-in user (ADR-0007).
 */
final class SetCurrentLanguage extends Action
{
    public const string SESSION_KEY = 'lynx.language';

    public function __construct(private readonly LanguageContext $context) {}

    public static function name(): string
    {
        return 'languages.set_current';
    }

    public static function permission(): string
    {
        return self::EVERYONE;
    }

    public static function description(): string
    {
        return 'Set the current language for this request, optionally remembering it in the session and on the user.';
    }

    public static function logsActivity(): bool
    {
        return false;
    }

    public static function exposedAsTool(): bool
    {
        return false;
    }

    public static function rules(): array
    {
        return ['language' => ['required'], 'remember' => ['sometimes', 'boolean']];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'language' => $schema->string()->required(),
            'remember' => $schema->boolean(),
        ];
    }

    public function handle(Input $input, ActionContext $context): Language
    {
        $language = $this->context->set(Value::string($input->get('language')));

        if ($input->bool('remember')) {
            $request = app(Request::class);

            if ($request->hasSession()) {
                $request->session()->put(self::SESSION_KEY, $language->code);
            }

            $user = $request->user();

            if ($user !== null && Schema::hasColumn($user->getTable(), 'lynx_language_id')) {
                $user->forceFill(['lynx_language_id' => $language->id])->saveQuietly();
            }
        }

        return $language;
    }
}
