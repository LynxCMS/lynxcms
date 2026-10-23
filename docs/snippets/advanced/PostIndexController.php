<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

// #region controller
final class PostIndexController
{
    private const int PER_PAGE = 10;

    /** Sort options visitors may pick, mapped to selector sorts. */
    private const array SORTS = ['newest' => '-published_on', 'oldest' => 'published_on', 'title' => 'title'];

    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', 'in:'.implode(',', array_keys(self::SORTS))],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $page = (int) ($filters['page'] ?? 1);

        $clauses = ['template=post', 'sort='.self::SORTS[$filters['sort'] ?? 'newest']];

        if (($filters['q'] ?? '') !== '') {
            // Quotes, commas and pipes have a meaning in selectors: keep only the words.
            $words = trim((string) preg_replace('/["\',|]+/', ' ', $filters['q']));
            $clauses[] = 'search*="'.$words.'"';
        }

        $clauses[] = 'start='.(($page - 1) * self::PER_PAGE);
        $clauses[] = 'limit='.self::PER_PAGE;
        $posts = lynx()->pages->find(implode(', ', $clauses));

        return view('blog.index', [
            'posts' => new LengthAwarePaginator($posts->all(), $posts->total(), self::PER_PAGE, $page, [
                'path' => $request->url(),
                'query' => $request->except('page'),
            ]),
        ]);
    }
}
// #endregion controller
