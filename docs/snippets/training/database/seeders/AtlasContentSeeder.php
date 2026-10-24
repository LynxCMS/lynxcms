<?php

declare(strict_types=1);

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use LynxCms\Core\Pages\Page;
use LynxCms\Media\Actions\StoreMedia;
use RuntimeException;

/**
 * Atlas Training's demo content, in English and Portuguese: the sections of the home page,
 * the catalogue with six courses and two dated sessions each, the instructors, the studios,
 * news and the legal pages. Run it after AtlasSchemaSeeder.
 */
final class AtlasContentSeeder extends Seeder
{
    /** @var array<string, int> page ids by the demo data's keys */
    private array $ids = [];

    public function run(): void
    {
        /** @var array<string, mixed> $data */
        $data = require __DIR__.'/data/atlas.php';

        $this->sections(['courses' => ['cursos', 'Courses', 'Cursos'], 'instructors' => ['formadores', 'Instructors', 'Formadores'],
            'locations' => ['espacos', 'Studios', 'Estúdios'], 'posts' => ['noticias', 'News', 'Notícias']]);

        foreach ($data['instructors'] as $name => $instructor) {
            $this->ids[$name] = $this->page('instructor', '/instructors/', $name, $instructor)->id;
        }

        foreach ($data['locations'] as $name => $location) {
            $this->ids[$name] = $this->page('location', '/locations/', $name, $location)->id;
        }

        $week = 2;
        foreach ($data['courses'] as $name => $course) {
            $page = $this->course($name, $course);
            $this->session($page, 'lisbon', CarbonImmutable::now()->startOfWeek()->addWeeks($week++)->setTime(10, 0));
            $this->session($page, 'porto', CarbonImmutable::now()->startOfWeek()->addWeeks($week + 3)->setTime(18, 30));
            $materials = $this->page('materials', '/'.$page->path().'/', 'materials', ['pt' => 'materiais', 'title' => ['en' => 'Course materials', 'pt' => 'Materiais do curso'],
                'body' => ['en' => '<p>Your reading list and handouts. See you in class!</p>', 'pt' => '<p>A lista de leituras e os apontamentos. Até à aula!</p>']]);
            $this->download($materials, 'reading-list.pdf', "Reading list for {$name}");
        }

        $this->home($data['home']);
        $this->extras();
        $this->menus();
    }

    /** A file in the materials page's private downloads field, as an editor would upload it. */
    private function download(Page $materials, string $filename, string $contents): void
    {
        $entry = lynx()->run(StoreMedia::class, ['page' => $materials->id, 'field' => 'downloads', 'base64' => base64_encode($contents), 'filename' => $filename])->value;
        $materials->downloads = [['values' => $entry]];
        $materials->save();
    }

    /** The main and footer menus, which editors change in the admin's Menus screen. */
    private function menus(): void
    {
        lynx()->menus()->create('main', ['en' => 'Main navigation', 'pt' => 'Navegação principal']);
        foreach (['/courses/', '/instructors/', '/locations/', '/posts/'] as $path) {
            lynx()->menus()->saveItem(['menu' => 'main', 'kind' => 'page', 'page' => $path]);
        }

        lynx()->menus()->create('footer', ['en' => 'About Atlas', 'pt' => 'Sobre a Atlas']);
        foreach (['/about/', '/posts/', '/privacy/'] as $path) {
            lynx()->menus()->saveItem(['menu' => 'footer', 'kind' => 'page', 'page' => $path]);
        }
    }

    /** @param  array<string, array{0: string, 1: string, 2: string}>  $sections */
    private function sections(array $sections): void
    {
        foreach ($sections as $template => [$pt, $en, $ptTitle]) {
            $this->page($template, '/', $template, ['pt' => $pt, 'title' => ['en' => $en, 'pt' => $ptTitle]]);
        }
    }

    /** @param  array<string, mixed>  $course */
    private function course(string $name, array $course): Page
    {
        $course['instructors'] = array_map(fn (string $key): int => $this->ids[$key], $course['instructors']);
        $course['curriculum'] = array_map(fn (array $heading): array => ['values' => ['heading' => $heading]], $course['curriculum']);
        $course['body'] = ['en' => '<p>'.$course['summary']['en'].'</p>', 'pt' => '<p>'.$course['summary']['pt'].'</p>'];
        $page = $this->page('course', '/courses/', $name, $course);
        $this->ids[$name] = $page->id;

        return $page;
    }

    private function session(Page $course, string $location, CarbonImmutable $starts): void
    {
        $name = $starts->format('Y-m-d').'-'.$location;
        $this->page('session', '/'.$course->path().'/', $name, ['pt' => $name,
            'title' => [
                'en' => $course->getLanguageValue('en', 'title').', '.$starts->format('j F Y'),
                'pt' => $course->getLanguageValue('pt', 'title').', '.$starts->locale('pt')->isoFormat('D [de] MMMM [de] YYYY'),
            ],
            'starts' => $starts->format('Y-m-d H:i'), 'location' => $this->ids[$location], 'seats' => 16, 'availability' => 'scheduled',
        ]);
    }

    /** @param  array<string, mixed>  $home */
    private function home(array $home): void
    {
        $page = lynx()->pages->get('/') ?? throw new RuntimeException('Run php artisan lynx:install first.');
        $item = fn (array $values): array => ['values' => $values];

        $this->fill($page, [
            'title' => $home['title'], 'summary' => $home['summary'],
            'highlights' => array_map(fn (array $h): array => $item(['icon' => $h[0], 'heading' => $h[1], 'text' => $h[2]]), $home['highlights']),
            'stats' => array_map(fn (array $s): array => $item(['figure' => $s[0], 'heading' => $s[1]]), $home['stats']),
            'featured' => array_map(fn (string $key): int => $this->ids[$key], $home['featured']),
            'steps' => array_map(fn (array $s): array => $item(['heading' => $s[0], 'text' => $s[1]]), $home['steps']),
            'testimonials' => array_map(fn (array $t): array => $item(['text' => $t[0], 'person' => $t[1], 'rating' => $t[2]]), $home['testimonials']),
            'faqs' => array_map(fn (array $f): array => $item(['heading' => $f[0], 'text' => $f[1]]), $home['faqs']),
        ])->save();
    }

    /** News, the about and privacy pages, and the settings page. */
    private function extras(): void
    {
        $this->page('post', '/posts/', 'new-porto-studio', ['pt' => 'novo-estudio-porto', 'published_on' => CarbonImmutable::now()->subDays(10)->format('Y-m-d'),
            'title' => ['en' => 'Our new Porto studio', 'pt' => 'O nosso novo estúdio no Porto'],
            'summary' => ['en' => 'Classes start next month in Cedofeita.', 'pt' => 'As aulas começam no próximo mês em Cedofeita.']]);
        $this->page('page', '/', 'about', ['pt' => 'sobre', 'title' => ['en' => 'About us', 'pt' => 'Sobre nós'],
            'body' => ['en' => '<p>Atlas Training has taught wellness professionals since 2011.</p>', 'pt' => '<p>A Atlas Training forma profissionais de bem-estar desde 2011.</p>']]);
        $this->page('page', '/', 'privacy', ['pt' => 'privacidade', 'title' => ['en' => 'Privacy', 'pt' => 'Privacidade']]);
        $this->page('settings', '/', 'settings', ['email' => 'hello@atlas-training.example', 'phone' => '+351 210 000 000']);
    }

    /**
     * A page with its URL names and values. Texts given as ['en' => …, 'pt' => …] go to each
     * language; anything else is shared.
     *
     * @param  array<string, mixed>  $values  the page's values, plus "pt" for its Portuguese URL name
     */
    private function page(string $template, string $parent, string $name, array $values): Page
    {
        $page = lynx()->pages->new($template, $parent);
        $page->name = $name;

        if (isset($values['pt'])) {
            $page->setLanguageName('pt', $values['pt']);
        }

        unset($values['pt']);
        $this->fill($page, $values)->save();

        return $page;
    }

    /** @param  array<string, mixed>  $values */
    private function fill(Page $page, array $values): Page
    {
        foreach ($values as $field => $value) {
            if (is_array($value) && array_keys($value) === ['en', 'pt']) {
                $page->setLanguageValue('en', $field, $value['en'])->setLanguageValue('pt', $field, $value['pt']);
            } else {
                $page->{$field} = $value;
            }
        }

        return $page;
    }
}
