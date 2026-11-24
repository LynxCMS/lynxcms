<?php

declare(strict_types=1);

namespace LynxCms\Media\Admin\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Modelable;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use LynxCms\Admin\AdminAccess;
use LynxCms\Admin\Editor\LocalizedForm;
use LynxCms\Core\Support\Value;
use LynxCms\Media\Actions\StoreMedia;
use LynxCms\Media\Admin\MediaConverter;
use LynxCms\Media\Fields\ImagesType;
use LynxCms\Media\Fields\MediaFieldType;

/**
 * An images or files field in the page editor (ADR-0046): drop or pick files, which media.store
 * writes right away; sort, switch off, remove, describe per language, tag and set the focus point.
 * With a file per language (ADR-0150), a tab per language lists its files and the files for every
 * language, and an upload in a tab is for that language. The page's value changes when the editor
 * saves; files never saved are pruned later.
 *
 * @internal
 */
final class MediaField extends Component
{
    use WithFileUploads;

    /** @var list<array<string, mixed>> form items (MediaConverter) */
    #[Modelable]
    public array $items = [];

    #[Locked]
    public ?int $pageId = null;

    #[Locked]
    public string $field = '';

    #[Locked]
    public bool $disabled = false;

    /** @var list<TemporaryUploadedFile> */
    public array $uploads = [];

    /** The file whose details are open, by key. */
    public ?string $editing = null;

    /** With a file per language: the language tab shown, "" for every file. */
    public string $language = '';

    public function boot(AdminAccess $access): void
    {
        $access->authorizeScreen();
    }

    /** Livewire hook: store each upload through media.store and add it to the field. */
    public function updatedUploads(): void
    {
        abort_if($this->disabled || $this->pageId === null, 403);

        foreach ($this->uploads as $upload) {
            $contents = $upload->get();

            if ($contents === false) {
                $this->addError('uploads', __('lynx-media::admin.unreadable', ['file' => $upload->getClientOriginalName()]));

                continue;
            }

            try {
                $values = lynx()->run(StoreMedia::class, [
                    'page' => $this->pageId,
                    'field' => $this->field,
                    // The contents, never a path: no caller of media.store names a file on the server.
                    'base64' => base64_encode($contents),
                    'filename' => $upload->getClientOriginalName(),
                    'language' => $this->language === '' ? null : $this->language,
                ])->value;
                $this->items[] = Value::map(Value::list(app(MediaConverter::class)->toForm([['key' => (string) Str::ulid(), 'values' => $values]]))[0] ?? []);
            } catch (ValidationException $exception) {
                $this->addError('uploads', $upload->getClientOriginalName().': '.Value::string(collect($exception->errors())->flatten()->first()));
            }
        }

        $this->uploads = [];
    }

    public function toggle(int $index): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['enabled'] = ! (bool) ($this->items[$index]['enabled'] ?? true);
        }
    }

    public function remove(int $index): void
    {
        $this->items = array_values(array_filter($this->items, fn (int $i): bool => $i !== $index, ARRAY_FILTER_USE_KEY));
    }

    /** wire:sort handler: a file key dropped at a 0-based position. */
    public function sort(string $key, int|string $position): void
    {
        $position = $this->fullPosition(Value::int($position), $key);
        $moving = array_values(array_filter($this->items, fn (array $item): bool => ($item['key'] ?? null) === $key));
        $rest = array_values(array_filter($this->items, fn (array $item): bool => ($item['key'] ?? null) !== $key));
        array_splice($rest, max(0, min(count($rest), Value::int($position))), 0, $moving);
        $this->items = $rest;
    }

    /** The arrow buttons, for keyboards: moves a file one place earlier (-1) or later (1). */
    public function move(int $index, int $offset): void
    {
        $key = $this->items[$index]['key'] ?? null;
        $target = $index + ($offset < 0 ? -1 : 1);

        if (is_string($key) && $target >= 0 && $target < count($this->items)) {
            $this->sort($key, $target);
        }
    }

    /** The focus point picked on the preview, in percent. */
    public function focus(int $index, int|float $x, int|float $y): void
    {
        if (isset($this->items[$index])) {
            $this->items[$index]['focus'] = ['x' => max(0, min(100, (int) round($x))), 'y' => max(0, min(100, (int) round($y))), 'zoom' => Value::int(Value::map($this->items[$index]['focus'] ?? [])['zoom'] ?? 0)];
        }
    }

    /**
     * Whether a file shows in the tab: every file, or the tab's language's and those for every language.
     *
     * @param  array<array-key, mixed>  $item
     */
    public function isShown(array $item): bool
    {
        $language = Value::string($item['language'] ?? '');

        return $this->language === '' || $language === '' || $language === $this->language;
    }

    public function render(): View
    {
        $definition = lynx()->fields->get($this->field);
        $type = $definition->fieldType();
        $settings = $definition->config();
        $max = Value::int($settings['max_files'] ?? 0);
        $isPerLanguage = MediaFieldType::isPerLanguage($settings);
        // max_files counts per language: the files of the tab's language, or those for every language.
        $counted = $isPerLanguage ? count(array_filter($this->items, fn (array $item): bool => Value::string($item['language'] ?? '') === $this->language)) : count($this->items);

        return view('lynx-media::livewire.media-field', [
            'isImages' => $type instanceof ImagesType,
            'accept' => $type instanceof MediaFieldType ? implode(',', array_map(fn (string $e): string => '.'.$e, $type->extensions($settings))) : '',
            'canAdd' => ! $this->disabled && $this->pageId !== null && ($max === 0 || $counted < $max),
            'isPerLanguage' => $isPerLanguage,
            'fallsBackTo' => $isPerLanguage ? $this->fallsBackTo($definition->fallbackMode() !== 'none') : null,
            'multiple' => $max !== 1,
            'languages' => LocalizedForm::languages(),
            'allowedTags' => Value::strings($settings['tags'] ?? []),
            'field' => $definition,
        ]);
    }

    /**
     * The position among every file of a drop at a position among the files the tab shows: just
     * before the file shown there, or after the last shown.
     */
    private function fullPosition(int $position, string $moving): int
    {
        if ($this->language === '') {
            return $position;
        }

        $shown = array_values(array_filter($this->items, fn (array $item): bool => $this->isShown($item) && ($item['key'] ?? null) !== $moving));
        $rest = array_values(array_filter($this->items, fn (array $item): bool => ($item['key'] ?? null) !== $moving));
        $anchor = $shown[$position]['key'] ?? null;

        foreach ($rest as $index => $item) {
            if (($item['key'] ?? null) === $anchor) {
                return $index;
            }
        }

        $last = end($shown);

        return $last === false ? count($rest) : (int) array_search($last, $rest, true) + 1;
    }

    /**
     * In a language's tab without files of its own or for every language: the language whose files
     * visitors see instead (ADR-0150), or "" when they see none.
     */
    private function fallsBackTo(bool $fallsBack): ?string
    {
        $has = fn (string $code): bool => array_any($this->items, fn (array $item): bool => Value::string($item['language'] ?? '') === $code);

        if ($this->language === '' || $has($this->language) || $has('')) {
            return null;
        }

        foreach ($fallsBack ? array_slice(lynx()->languages->chain($this->language), 1) : [] as $step) {
            if ($has((string) $step->code)) {
                return $step->tabLabel();
            }
        }

        return '';
    }
}
