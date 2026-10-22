<?php

declare(strict_types=1);

use Illuminate\Support\Facades\ParallelTesting;
use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\Page;
use LynxCms\Menus\Reading\MenuItem;
use LynxCms\Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit');

/**
 * A folder under storage/framework/testing for this test process only, so parallel runs do not
 * write to the same files.
 */
function testingPath(string $name): string
{
    $token = ParallelTesting::token();

    return storage_path('framework/testing/'.$name.'-'.($token === false ? 'main' : $token));
}

/**
 * Install Lynx (default language + home page) and return the API root.
 */
function installLynx(): Lynx
{
    lynx()->run(InstallLynx::class);

    return lynx();
}

/**
 * Install plus a Portuguese language and a small course structure:
 * fields title, body (translatable), summary (translatable), price, related; templates course and courses.
 */
function courseSite(): Lynx
{
    $lynx = installLynx();
    $lynx->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'default');
    $lynx->fields->add('body', 'textarea', translatable: true, settings: ['format' => 'html']);
    $lynx->fields->add('summary', 'textarea', translatable: true);
    $lynx->fields->add('price', 'integer');
    $lynx->fields->add('related', 'page');
    $lynx->templates->add('courses', fields: [['name' => 'title', 'required' => true]]);
    $lynx->templates->add('course', fields: [['name' => 'title', 'required' => true], 'summary', 'body', 'price', 'related']);

    $courses = $lynx->pages->new('courses', '/');
    $courses->name = 'courses';
    $courses->title = 'Courses';
    $courses->setLanguageName('pt', 'cursos');
    $courses->setLanguageValue('pt', 'title', 'Cursos');
    $courses->save();

    return $lynx;
}

/**
 * Create course pages under /courses/ in the given order.
 *
 * @return array<string, Page>
 */
function createCourses(string ...$names): array
{
    $pages = [];

    foreach ($names as $name) {
        $page = lynx()->pages->new('course', '/courses/');
        $page->name = $name;
        $page->title = ucfirst($name);
        $page->save();
        $pages[$name] = $page;
    }

    return $pages;
}

/**
 * Names of a page's children in the default language, in sort order.
 *
 * @return list<string>
 */
function childNamesOf(string $parent = '/courses/'): array
{
    return lynx()->pages->get($parent)->children()->map(fn (Page $page): string => $page->name());
}

/**
 * Labels of resolved menu items.
 *
 * @param  list<MenuItem>  $items
 * @return list<string>
 */
function labels(array $items): array
{
    return array_map(fn (MenuItem $item): string => $item->label, $items);
}

/** A JPEG of the given size, as bytes. */
function jpeg(int $width, int $height): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, (int) imagecolorallocate($image, 200, 60, 40));
    ob_start();
    imagejpeg($image);

    return (string) ob_get_clean();
}
