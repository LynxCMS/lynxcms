<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/*
 * Admin screens use the admin's form components (<x-lynx-admin::ui.input>, ui.textarea, ui.select,
 * ui.checkbox, ui.switch) instead of raw <input>, <select> and <textarea> tags: the components follow
 * the site's input style and branding (data-input-style), and a raw tag does not (ADR-0106). Hidden
 * inputs are fine. Views visitors see (a site's form, the page password screen) keep plain HTML.
 *
 * RAW_CONTROLS_LEFT lists the views that still have raw controls, with how many. The list only
 * shrinks: a view not on it may have none, a listed view may not gain one, and a view that loses
 * some must have its number lowered here, or taken off once it reaches none.
 */

/** View (under packages/) => raw form controls it still has. */
const RAW_CONTROLS_LEFT = [
    'admin/resources/views/auth/login.blade.php' => 1,
    'admin/resources/views/components/choices-editor.blade.php' => 3,
    'admin/resources/views/components/localized-input.blade.php' => 4,
    'admin/resources/views/inputs/choice.blade.php' => 2,
    'admin/resources/views/inputs/component.blade.php' => 5,
    'admin/resources/views/inputs/date.blade.php' => 1,
    'admin/resources/views/inputs/decimal.blade.php' => 1,
    'admin/resources/views/inputs/integer.blade.php' => 1,
    'admin/resources/views/inputs/page.blade.php' => 2,
    'admin/resources/views/inputs/text.blade.php' => 1,
    'admin/resources/views/inputs/textarea.blade.php' => 1,
    'admin/resources/views/inputs/time.blade.php' => 1,
    'admin/resources/views/inputs/toggle.blade.php' => 1,
    'admin/resources/views/livewire/account-security.blade.php' => 5,
    'admin/resources/views/livewire/admin-search.blade.php' => 1,
    'admin/resources/views/livewire/branding-form.blade.php' => 5,
    'admin/resources/views/livewire/field-list.blade.php' => 4,
    'admin/resources/views/livewire/icon-picker.blade.php' => 1,
    'admin/resources/views/livewire/language-form.blade.php' => 7,
    'admin/resources/views/livewire/link-dialog-pages.blade.php' => 1,
    'admin/resources/views/livewire/link-dialog.blade.php' => 5,
    'admin/resources/views/livewire/page-creator.blade.php' => 3,
    'admin/resources/views/livewire/page-picker.blade.php' => 1,
    'admin/resources/views/livewire/page-schedule.blade.php' => 2,
    'admin/resources/views/livewire/page-settings.blade.php' => 5,
    'admin/resources/views/livewire/page-strings.blade.php' => 2,
    'admin/resources/views/livewire/page-table-input.blade.php' => 2,
    'admin/resources/views/livewire/partials/field/fields.blade.php' => 4,
    'admin/resources/views/livewire/partials/field/kind.blade.php' => 1,
    'admin/resources/views/livewire/partials/field/settings.blade.php' => 4,
    'admin/resources/views/livewire/partials/items/builder.blade.php' => 1,
    'admin/resources/views/livewire/partials/items/create-dialog.blade.php' => 3,
    'admin/resources/views/livewire/partials/items/limits.blade.php' => 5,
    'admin/resources/views/livewire/partials/items/subfields.blade.php' => 3,
    'admin/resources/views/livewire/partials/items/types.blade.php' => 2,
    'admin/resources/views/livewire/partials/schedule-row.blade.php' => 1,
    'admin/resources/views/livewire/partials/template-field-row.blade.php' => 7,
    'admin/resources/views/livewire/partials/template-section.blade.php' => 2,
    'admin/resources/views/livewire/role-form.blade.php' => 5,
    'admin/resources/views/livewire/template-family.blade.php' => 4,
    'admin/resources/views/livewire/template-form.blade.php' => 3,
    'admin/resources/views/livewire/template-list.blade.php' => 1,
    'admin/resources/views/livewire/user-roles.blade.php' => 5,
    'cache/resources/views/admin/livewire/fragments.blade.php' => 1,
    'cache/resources/views/admin/livewire/partials/rules.blade.php' => 6,
    'cache/resources/views/admin/livewire/partials/server.blade.php' => 1,
    'forms/resources/views/admin/livewire/form-list.blade.php' => 2,
    'forms/resources/views/admin/livewire/inbox.blade.php' => 4,
    'forms/resources/views/admin/livewire/submission.blade.php' => 1,
    'media/resources/views/livewire/media-field.blade.php' => 3,
    'menus/resources/views/admin/editor.blade.php' => 1,
    'menus/resources/views/admin/partials/item-form.blade.php' => 12,
    'menus/resources/views/admin/partials/settings.blade.php' => 1,
    'menus/resources/views/admin/partials/translated-input.blade.php' => 1,
    'page-password/resources/views/inputs/password.blade.php' => 1,
    'seo/resources/views/admin/livewire/schemas.blade.php' => 3,
    'seo/resources/views/admin/livewire/templates.blade.php' => 1,
    'sitemap/resources/views/admin/livewire/settings.blade.php' => 5,
];

/** @return array<string, int> raw form controls by admin view, under packages/ */
function rawFormControls(): array
{
    $packages = dirname(__DIR__, 2).'/packages';
    $counts = [];

    foreach (Finder::create()->files()->name('*.blade.php')->in($packages.'/*/resources/views') as $file) {
        $view = str_replace('\\', '/', substr($file->getPathname(), strlen($packages) + 1));

        if (! isAdminView($view)) {
            continue;
        }

        $blade = (string) preg_replace('/\{\{--.*?--\}\}/s', '', $file->getContents());
        preg_match_all('/<(input|select|textarea)\b([^>]*)>/s', $blade, $tags, PREG_SET_ORDER);
        $raw = array_filter($tags, fn (array $tag): bool => $tag[1] !== 'input' || ! str_contains($tag[2], 'type="hidden"'));

        if ($raw !== []) {
            $counts[$view] = count($raw);
        }
    }

    ksort($counts);

    return $counts;
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

it('adds no raw form control to an admin view', function (): void {
    $grown = [];

    foreach (rawFormControls() as $view => $count) {
        if ($count > (RAW_CONTROLS_LEFT[$view] ?? 0)) {
            $grown[] = "{$view} has {$count} raw form controls; use the admin's form components";
        }
    }

    expect($grown)->toBe([]);
});

it('lowers the list as views move to the form components', function (): void {
    $found = rawFormControls();
    $stale = [];

    foreach (RAW_CONTROLS_LEFT as $view => $count) {
        if (($found[$view] ?? 0) < $count) {
            $stale[] = "{$view}: lower RAW_CONTROLS_LEFT to ".($found[$view] ?? 0);
        }
    }

    expect($stale)->toBe([]);
});

it('counts raw controls and leaves hidden inputs and the components alone', function (): void {
    expect(isAdminView('admin/resources/views/components/ui/input.blade.php'))->toBeFalse()
        ->and(isAdminView('forms/resources/views/fields/text.blade.php'))->toBeFalse()
        ->and(isAdminView('seo/resources/views/admin/livewire/templates.blade.php'))->toBeTrue()
        ->and(array_sum(rawFormControls()))->toBe(array_sum(RAW_CONTROLS_LEFT));
});
