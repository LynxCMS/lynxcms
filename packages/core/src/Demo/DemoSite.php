<?php

declare(strict_types=1);

namespace LynxCms\Core\Demo;

use LynxCms\Core\Actions\InstallLynx;
use LynxCms\Core\Lynx;
use LynxCms\Core\Pages\Page;
use LynxCms\Core\Pages\PageStatus;
use LynxCms\Core\Support\Value;

/**
 * A small course site in the default language and Portuguese, built only through the public API.
 * Used by the workbench, the manual tests in the implementation plan, and the performance spike.
 *
 * @internal
 */
final readonly class DemoSite
{
    public function __construct(private Lynx $lynx) {}

    /**
     * @param  int  $extraPages  additional generated courses (for the performance spike)
     * @param  int  $extraLanguages  additional generated languages (for the performance spike)
     */
    public function build(int $extraPages = 0, int $extraLanguages = 0, ?callable $progress = null): void
    {
        $this->lynx->run(InstallLynx::class);
        $this->languages($extraLanguages);
        $this->schema();
        $this->homeInPortuguese();
        $courses = $this->lynx->pages->get('/courses/') ?? $this->coursesPage();
        $this->settingsPage();
        $this->sampleCourses($courses);
        $this->generatedCourses($courses, $extraPages, $progress);
    }

    /** English as the default, Portuguese falling back to it, and any generated languages. */
    private function languages(int $extraLanguages): void
    {
        $lynx = $this->lynx;

        if ($lynx->languages->find('pt') === null) {
            $lynx->languages->update('default', ['name' => 'English', 'locale' => 'en']);
            $lynx->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'default');
        }

        foreach (range(1, $extraLanguages) as $i) {
            if ($extraLanguages > 0 && $lynx->languages->find("l{$i}") === null) {
                $lynx->languages->add("l{$i}", "Language {$i}", fallback: $i === 1 ? 'pt' : 'l'.($i - 1));
            }
        }
    }

    private function sampleCourses(Page $courses): void
    {
        $lynx = $this->lynx;

        foreach ($this->courses() as [$name, $ptName, $title, $ptTitle, $summary, $price]) {
            if ($lynx->pages->get("/courses/{$name}/") !== null) {
                continue;
            }

            $page = $lynx->pages->new('course', $courses);
            $page->name = $name;
            $page->title = $title;
            $page->summary = $summary;
            $page->body = "<p>{$summary}</p><p>This body is only in English, so the Portuguese page shows it as a fallback.</p>";
            $page->price = $price;

            if ($lynx->components()->find('enrol-form') !== null) {
                $page->enrol = ['component' => 'enrol-form', 'settings' => ['capacity' => 30]];
            }

            if ($ptName !== null) {
                $page->setLanguageName('pt', $ptName);
                $page->setLanguageValue('pt', 'title', $ptTitle);
            }

            $page->save();
        }
    }

    private function generatedCourses(Page $courses, int $extraPages, ?callable $progress): void
    {
        $lynx = $this->lynx;

        for ($i = 1; $i <= $extraPages; $i++) {
            if ($lynx->pages->get("/courses/generated-{$i}/") !== null) {
                continue;
            }

            $page = $lynx->pages->new('course', $courses);
            $page->name = "generated-{$i}";
            $page->title = "Generated course {$i}";
            $page->price = random_int(10, 999);

            if ($i % 2 === 0) {
                $page->setLanguageName('pt', "curso-gerado-{$i}");
                $page->setLanguageValue('pt', 'title', "Curso gerado {$i}");
            }

            $page->save();

            if ($progress !== null && $i % 500 === 0) {
                $progress($i);
            }
        }
    }

    /** The home page is active in every language; give it a Portuguese title. */
    private function homeInPortuguese(): void
    {
        $home = $this->lynx->pages->get('/');

        if ($home === null || Value::string($home->getLanguageValue('pt', 'title')) !== '') {
            return;
        }

        $home->setLanguageValue('pt', 'title', 'Início');
        $home->save();
    }

    private function schema(): void
    {
        $lynx = $this->lynx;
        $fields = [
            ['summary', 'textarea', true, ['format' => 'plain']],
            ['body', 'textarea', true, ['format' => 'html']],
            ['price', 'integer', false, ['min' => 0]],
            ['related', 'page', false, ['templates' => ['course']]],
            ['enrol', 'component', false, []],
            ['footer_text', 'text', true, []],
        ];

        foreach ($fields as [$name, $type, $translatable, $settings]) {
            if ($lynx->fields->find($name) === null) {
                $lynx->fields->add($name, $type, translatable: $translatable, settings: $settings);
            }
        }

        if ($lynx->templates->find('courses') === null) {
            $lynx->templates->add('courses', fields: [['name' => 'title', 'required' => true], 'summary']);
        }

        if ($lynx->templates->find('course') === null) {
            $lynx->templates->add('course', fields: [['name' => 'title', 'required' => true], 'summary', 'body', 'price', 'related', 'enrol']);
        }

        if ($lynx->templates->find('settings') === null) {
            $lynx->templates->add('settings', fields: ['footer_text'], hasUrl: false, singleton: true);
        }
    }

    private function coursesPage(): Page
    {
        $page = $this->lynx->pages->new('courses', '/');
        $page->name = 'courses';
        $page->title = 'Courses';
        $page->summary = 'Project management courses.';
        $page->setLanguageName('pt', 'cursos');
        $page->setLanguageValue('pt', 'title', 'Cursos');
        $page->setLanguageValue('pt', 'summary', 'Cursos de gestão de projetos.');
        $page->save();

        return $page;
    }

    private function settingsPage(): void
    {
        if ($this->lynx->settings() !== null) {
            return;
        }

        $page = $this->lynx->pages->new('settings', '/');
        $page->name = '_settings';
        $page->footer_text = '© Lynx demo. All rights reserved.';
        $page->setLanguageValue('pt', 'footer_text', '© Demonstração Lynx. Todos os direitos reservados.');
        $page->setStatus(PageStatus::HIDDEN);
        $page->save();
    }

    /**
     * @return list<array{0: string, 1: ?string, 2: string, 3: ?string, 4: string, 5: int}>
     */
    private function courses(): array
    {
        return [
            ['delivery-8', 'navegando-entrega-8', 'Navigating Project Delivery 8th Edition', 'Navegando pela Entrega de Projetos 8ª Edição', 'An in-depth course on the Delivery Guide 8th Edition.', 249],
            ['agile-practice', null, 'Agile Practice Essentials', null, 'Agile principles and practices for project leaders.', 99],
            ['risk-management', 'gestao-de-risco', 'Enterprise Risk Management', 'Gestão de Risco Empresarial', 'Identify, assess and respond to risk.', 149],
        ];
    }
}
