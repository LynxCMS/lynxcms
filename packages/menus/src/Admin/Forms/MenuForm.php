<?php

declare(strict_types=1);

namespace LynxCms\Menus\Admin\Forms;

use Livewire\Form;

/**
 * The "new menu" form.
 *
 * @internal
 */
final class MenuForm extends Form
{
    public string $name = '';

    /** @var array<string, string> */
    public array $title = [];
}
