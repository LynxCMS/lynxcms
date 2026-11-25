<?php

declare(strict_types=1);

namespace LynxCms\Core\Actions\Fields;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Action;
use LynxCms\Core\Actions\Runner\ActionContext;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Actions\Runner\RefusesUnknownInput;
use LynxCms\Core\Fields\FieldRegistry;
use LynxCms\Core\Fields\FieldSettings;
use LynxCms\Core\Models\Field;
use LynxCms\Core\Permissions\Authorizer;
use LynxCms\Core\Support\LocalizedText;
use LynxCms\Core\Support\Tables;
use LynxCms\Core\Support\Value;
use Override;

/**
 * Changes a field's label or settings. Renaming and changing scope come later (they move data).
 * Only superusers change who may see and edit a field (access) or whether only code writes it
 * (locked): with schema.manage alone a role could otherwise lift both. A field whose format turns
 * to html has its stored values cleaned (fields.clean_html), as they were never sanitized.
 *
 * @api
 */
final class UpdateField extends Action implements RefusesUnknownInput
{
    /** Settings that protect content; only superusers change them. */
    private const array PROTECTED_SETTINGS = ['access', 'locked'];

    public function __construct(
        private readonly FieldRegistry $fields,
        private readonly Authorizer $authorizer,
    ) {}

    public static function name(): string
    {
        return 'fields.update';
    }

    public static function permission(): string
    {
        return 'schema.manage';
    }

    #[Override]
    public static function changesSchema(): bool
    {
        return true;
    }

    public static function description(): string
    {
        return 'Change a field\'s label or settings. Settings are merged with the existing ones.';
    }

    public static function rules(): array
    {
        return [
            'field' => ['required', 'string'],
            'label' => ['sometimes', ...LocalizedText::rules()],
            'settings' => ['sometimes', 'array'],
            'settings.fallback' => ['sometimes', 'nullable', Rule::in(['inherit', 'none'])],
            'settings.autoload' => ['sometimes', 'nullable', 'boolean'],
            'settings.locked' => ['sometimes', 'nullable', 'boolean'],
            'settings.access' => ['sometimes', 'nullable', 'array:view,edit'],
            'settings.access.view' => ['sometimes', 'nullable', 'array'],
            'settings.access.view.*' => ['string', Rule::exists(Tables::ROLES, 'name')],
            'settings.access.edit' => ['sometimes', 'nullable', 'array'],
            'settings.access.edit.*' => ['string', Rule::exists(Tables::ROLES, 'name')],
        ];
    }

    public static function schema(JsonSchema $schema): array
    {
        return [
            'field' => $schema->string()->description('Name of the field.')->required(),
            'label' => $schema->string(),
            'settings' => $schema->object()->description('Settings to merge into the existing ones; a setting given as null is removed.'),
        ];
    }

    public function handle(Input $input, ActionContext $context): Field
    {
        $field = Field::query()->findOrFail($this->fields->get((string) $input->string('field'))->id);

        if ($input->has('label')) {
            $field->label = LocalizedText::from($input->get('label'))->toColumn();
        }

        $wasHtml = $field->fieldType()->isHtml($field->config());

        if ($input->has('settings')) {
            $settings = $input->array('settings');
            $this->authorizeProtections($field, Value::map($settings), $context);
            // Normalized settings (NormalizesSettings) replace what was given; nulls still remove.
            $settings = array_merge($settings, FieldSettings::validate($field->fieldType(), $settings));
            // A setting given as null is removed, so it falls back to the type's default again.
            $merged = array_filter(array_merge($field->config(), Value::map($settings)), fn (mixed $value): bool => $value !== null);
            FieldSettings::check($field->fieldType(), $merged);
            $this->assertLockable($field, $merged);
            $field->settings = $merged;
        }

        $field->save();
        $this->fields->flush();
        $context->changes->updated('field', $field->id, "Update field {$field->name}", $field->toSummary());

        if (! $wasHtml && $field->fieldType()->isHtml($field->config())) {
            $context->run(CleanHtmlValues::class, ['field' => $field->name]);
        }

        return $field;
    }

    /**
     * Refuses a change to access or locked from an actor who is not a superuser, at any depth.
     * Sending the stored value again is no change, so other roles can still save the field.
     *
     * @param  array<string, mixed>  $settings
     */
    private function authorizeProtections(Field $field, array $settings, ActionContext $context): void
    {
        $config = $field->config();

        foreach (self::PROTECTED_SETTINGS as $key) {
            if (array_key_exists($key, $settings) && $settings[$key] != ($config[$key] ?? null) && ! $this->authorizer->isSuperuser($context->actor)) {
                throw new AuthorizationException(Value::string(__('lynx::access.denied.field_protection', ['field' => $field->name, 'setting' => $key])));
            }
        }
    }

    /**
     * A locked field is written only by code (ADR-0149), so no template may require it: editors
     * could never fill it.
     *
     * @param  array<string, mixed>  $settings
     */
    private function assertLockable(Field $field, array $settings): void
    {
        if (($settings['locked'] ?? false) !== true) {
            return;
        }

        foreach (lynx()->templates->all() as $template) {
            $use = lynx()->templates->fields($template)->get((string) $field->name);

            if ($use !== null && ($use->required() || $use->requiredIf() !== null)) {
                throw ValidationException::withMessages(['settings.locked' => Value::string(__('lynx::structure.field.locked_required', ['field' => $field->name, 'template' => $template->name]))]);
            }
        }
    }
}
