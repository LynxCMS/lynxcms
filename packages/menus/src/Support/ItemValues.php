<?php

declare(strict_types=1);

namespace LynxCms\Menus\Support;

use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Runner\Input;
use LynxCms\Core\Fields\LinkRel;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Support\Value;
use LynxCms\Menus\MenuSchema;

/**
 * Validates a menu item's input for its kind and builds the field values to save. Values of
 * the other kinds are cleared, so switching an item from "link" to "page" leaves no stale URL.
 *
 * @internal
 */
final class ItemValues
{
    public const array KINDS = ['section', 'page', 'link'];

    /** Templates that are never served, so pages using them cannot be menu targets. */
    private const array UNLINKABLE = [MenuSchema::ROOT_TEMPLATE, MenuSchema::MENU_TEMPLATE, MenuSchema::ITEM_TEMPLATE];

    /**
     * @return array<string, mixed>
     */
    public function build(Input $input, ?Page $item): array
    {
        $kind = (string) $input->string('kind');
        $hasChildren = $kind === 'page' && $input->bool('children');
        $values = $this->label($input, $kind, $item) + [
            MenuSchema::PAGE => $kind === 'page' ? [$this->page($input)] : [],
            MenuSchema::URL => $kind === 'link' ? $this->url($input) : Translations::empty(),
            MenuSchema::CHILDREN => $hasChildren,
            // Depth and limit only mean something for child pages; cleared otherwise.
            MenuSchema::DEPTH => $hasChildren ? $input->int('depth') : null,
            MenuSchema::LIMIT => $hasChildren ? $input->int('limit') : null,
            MenuSchema::TARGET => $kind === 'section' ? null : $this->target($input),
            MenuSchema::REL => $kind === 'section' ? null : (implode(' ', LinkRel::chosen(Value::strings($input->array('rel')))) ?: null),
        ];

        if ($input->has('languages')) {
            $values[MenuSchema::LANGUAGES] = $this->languages(Value::strings($input->array('languages')));
        }

        foreach (['class' => MenuSchema::CSS_CLASS, 'icon_before' => MenuSchema::ICON_BEFORE, 'icon_after' => MenuSchema::ICON_AFTER] as $key => $field) {
            if ($input->has($key)) {
                $values[$field] = trim((string) $input->string($key)) ?: null;
            }
        }

        return $values;
    }

    /**
     * Section and link labels are required in the default language; page items fall back to the
     * page title. An update without "label" keeps the current one.
     *
     * @return array<string, mixed>
     */
    private function label(Input $input, string $kind, ?Page $item): array
    {
        $label = $input->has('label') || ! $item instanceof Page
            ? Translations::normalize($input->get('label'), 'label')
            : [lynx()->languages->default()->code => trim(Value::string($item->getLanguageValue(lynx()->languages->default(), 'title'))) ?: null];

        if ($kind !== 'page' && ! Translations::hasDefault($label)) {
            throw ValidationException::withMessages(['label' => 'A label in the default language is required.']);
        }

        return $input->has('label') || ! $item instanceof Page ? ['title' => $label] : [];
    }

    /**
     * The codes to store, or null when the item shows in every language.
     *
     * @param  list<string>  $shown
     */
    private function languages(array $shown): ?string
    {
        $codes = array_keys(Translations::empty());
        $unknown = array_diff($shown, $codes);

        if ($shown === [] || $unknown !== []) {
            throw ValidationException::withMessages(['languages' => $shown === [] ? 'Show the item in at least one language.' : 'Unknown language ['.implode(', ', $unknown).'].']);
        }

        $kept = array_values(array_intersect($codes, $shown));

        return count($kept) === count($codes) ? null : implode(',', $kept);
    }

    private function page(Input $input): int
    {
        $reference = $input->get('page');
        $page = is_int($reference) || is_string($reference) ? lynx()->pages->get($reference) : null;

        if (! $page instanceof Page || in_array($page->template()->name, self::UNLINKABLE, true) || ! $page->template()->hasUrl()) {
            throw ValidationException::withMessages(['page' => 'Choose an existing page that has a URL.']);
        }

        return (int) $page->id;
    }

    /**
     * @return array<string, string|null>
     */
    private function url(Input $input): array
    {
        $urls = Translations::normalize($input->get('url'), 'url');

        if (! Translations::hasDefault($urls)) {
            throw ValidationException::withMessages(['url' => 'A URL in the default language is required.']);
        }

        foreach ($urls as $code => $url) {
            if ($url !== null && ! LinkRules::isSafeUrl($url)) {
                $language = lynx()->languages->get($code)->name;

                throw ValidationException::withMessages(['url' => "The {$language} URL must be relative, a #fragment, or use ".implode(', ', LinkRules::SCHEMES).'.']);
            }
        }

        return $urls;
    }

    private function target(Input $input): ?string
    {
        $target = $input->string('target') ?: null;

        if ($target !== null && ! LinkRules::isValidTarget($target)) {
            throw ValidationException::withMessages(['target' => 'Use _blank, _self, _parent, _top or a frame name (letters, digits, - and _).']);
        }

        return $target;
    }
}
