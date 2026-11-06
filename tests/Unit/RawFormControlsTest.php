<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * Every form control in an admin view follows the site's appearance and branding: it is one of the
 * admin's components (<x-lynx-admin::ui.input>…) or a native control with BlatUI's class for it
 * (blat-input, blat-textarea, blat-select, blat-checkbox, blat-radio), which mirrors the component
 * and follows the input style too. A raw control with classes of its own follows neither.
 * Hidden and visually hidden inputs, colour pickers (a swatch, no field to style) and the control
 * inside an input group (data-slot="input-group-control", the group is the field) are fine.
 * Views visitors see (a site's form, the page password screen) keep plain HTML.
 */

/** A form control's tag, its attributes allowed to hold quotes, {{ }}, ->, and directives' parentheses. */
const FORM_CONTROL = '/<(input|select|textarea)\b((?:[^>"(){]|"[^"]*"|\{\{.*?\}\}|\{(?!\{)|\((?:[^()]|\([^()]*\))*\))*)>/s';

/** @return list<string> "view:line <tag>" for each control that follows neither a component nor a blat- class */
function unstyledFormControls(): array
{
    $packages = dirname(__DIR__, 2).'/packages';
    $found = [];

    foreach (Finder::create()->files()->name('*.blade.php')->in($packages.'/*/resources/views') as $file) {
        $view = str_replace('\\', '/', substr($file->getPathname(), strlen($packages) + 1));

        if (! isAdminView($view)) {
            continue;
        }

        $blade = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $file->getContents());
        preg_match_all(FORM_CONTROL, $blade, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($tags as $tag) {
            if (! isStyledControl($tag[1][0], $tag[2][0])) {
                $found[] = $view.':'.(substr_count(substr($blade, 0, $tag[0][1]), "\n") + 1)." <{$tag[1][0]}>";
            }
        }
    }

    return $found;
}

function isStyledControl(string $tag, string $attributes): bool
{
    return preg_match('/\bblat-(input|textarea|select|checkbox|radio)\b/', $attributes) === 1
        || str_contains($attributes, 'sr-only')
        || str_contains($attributes, 'data-slot="input-group-control"')
        || ($tag === 'input' && preg_match('/type="(hidden|color)"/', $attributes) === 1);
}

/** The admin package's views and modules' admin screens and field inputs, not the components themselves. */
function isAdminView(string $view): bool
{
    if (str_starts_with($view, 'admin/resources/views/components/ui/')) {
        return false;
    }

    return str_starts_with($view, 'admin/')
        || str_contains($view, '/resources/views/admin/')
        || str_contains($view, '/resources/views/inputs/')
        || str_starts_with($view, 'media/resources/views/livewire/');
}

it('styles every form control in an admin view like the admin\'s components', function (): void {
    expect(unstyledFormControls())->toBe([]);
});

it('reads tags whose attributes hold Blade and PHP', function (): void {
    $blade = '<input id="input-{{ $field->name }}" class="blat-input" @error(\'values.\'.$field->name) aria-invalid="true" @enderror>';
    preg_match(FORM_CONTROL, $blade, $tag);

    expect($tag[0] ?? '')->toBe($blade)
        ->and(isStyledControl('input', $tag[2] ?? ''))->toBeTrue()
        ->and(isStyledControl('input', ' type="text" class="border px-2"'))->toBeFalse()
        ->and(isStyledControl('input', ' type="hidden" name="token"'))->toBeTrue();
});

it('looks at admin views only', function (): void {
    expect(isAdminView('admin/resources/views/components/ui/input.blade.php'))->toBeFalse()
        ->and(isAdminView('forms/resources/views/fields/text.blade.php'))->toBeFalse()
        ->and(isAdminView('seo/resources/views/admin/livewire/templates.blade.php'))->toBeTrue();
});
