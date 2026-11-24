<?php

declare(strict_types=1);

namespace LynxCms\Admin\Livewire\Concerns;

use Livewire\Attributes\Locked;

/**
 * Lazy fields of the page editor (ADR-0149): a closed field set to load when opened renders its
 * value and input only after the editor first opens it, which keeps pages with big repeaters,
 * matrices or page lists quick to open.
 *
 * @internal
 */
trait OpensLazyFields
{
    /** @var list<string> lazy fields opened so far */
    #[Locked]
    public array $openedFields = [];

    public function openField(string $field): void
    {
        if (! in_array($field, $this->openedFields, true)) {
            $this->openedFields[] = $field;
        }
    }
}
