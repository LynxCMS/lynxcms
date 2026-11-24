{{-- One field of the page editor; show_if hides it while its condition does not match, required_if marks it required (ADR-0030); locked fields show their value read only (ADR-0063, ADR-0149); a lazy field renders once opened. --}}
<x-lynx-admin::editor.field :field="$field" :languages="$languages" :page="$page" :input="$field->isEditable() ? $inputs->for($field->type) : null" :disabled="$isLocked || ! $canEdit" :current="$values[$field->name] ?? []"
    :deferred="$field->isLazy && ! in_array($field->name, $openedFields, true)"
    :live="in_array($field->name, $watched, true) || ($field->settings['formatters'] ?? []) !== []" :required="$states[$field->name]['required'] ?? null" :show-type="$isSuperuser" :cell="true" />
