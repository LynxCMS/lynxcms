<?php

declare(strict_types=1);

namespace App\View\Composers;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

// #region composer
/**
 * The course catalogue's filters: level and a search, read from the query string, validated, and
 * turned into one selector with pagination. Registered for the catalogue's view, so the template
 * only prints what it gets.
 */
final class CourseCatalogue
{
    private const int PER_PAGE = 9;

    public function compose(View $view): void
    {
        $levels = lynx()->fields->choices('level');
        $filters = validator(request()->query(), [
            'level' => ['nullable', Rule::in($levels->values())],
            'q' => ['nullable', 'string', 'max:80'],
            'page' => ['nullable', 'integer', 'min:1'],
        ])->valid();

        $selector = ['template=course', 'sort=title'];

        if (isset($filters['level'])) {
            $selector[] = "level={$filters['level']}";
        }

        // Quotes, commas and pipes would end the value early, so they are taken out.
        $search = trim(str_replace(['"', "'", ',', '|'], ' ', (string) ($filters['q'] ?? '')));

        if ($search !== '') {
            $selector[] = "title|summary*=\"{$search}\"";
        }

        $page = (int) ($filters['page'] ?? 1);
        $courses = lynx()->pages->find(implode(', ', [...$selector, 'start='.(($page - 1) * self::PER_PAGE), 'limit='.self::PER_PAGE]));

        $view->with([
            'levels' => $levels,
            'filters' => $filters,
            'courses' => new LengthAwarePaginator($courses->all(), $courses->total(), self::PER_PAGE, $page, [
                'path' => request()->url(),
                'query' => array_filter(['level' => $filters['level'] ?? null, 'q' => $search ?: null]),
            ]),
        ]);
    }
}
// #endregion composer
