<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LynxCms\Core\Actions\Roles\SaveRole;

/**
 * The editors' side of Atlas Training: page editor layouts that match how the school works, and
 * roles for its staff. Run it after the schema and the content (the roles point at branches).
 */
final class AtlasEditorsSeeder extends Seeder
{
    public function run(): void
    {
        $this->layouts();
        $this->roles();
    }

    private function layouts(): void
    {
        // #region layouts
        lynx()->templates->update('course', [
            'tabs' => [['key' => 'teaching', 'label' => ['en' => 'Teaching', 'pt' => 'Ensino']]],
            'fields' => [
                ['name' => 'title', 'required' => true],
                ['name' => 'summary', 'required' => true, 'notes' => ['en' => 'One or two sentences. Shown on course cards and in search results.', 'pt' => 'Uma ou duas frases. Aparece nos cartões e nos resultados de pesquisa.']],
                'body',
                ['section' => 'facts', 'label' => ['en' => 'Facts', 'pt' => 'Dados'], 'column' => 'sidebar', 'fields' => [
                    ['name' => 'level', 'required' => true], ['name' => 'hours', 'width' => 50], ['name' => 'price', 'width' => 50],
                ]],
                ['section' => 'media', 'label' => ['en' => 'Photos', 'pt' => 'Fotografias'], 'collapsed' => true, 'fields' => ['photos']],
                ['name' => 'curriculum', 'tab' => 'teaching'],
                ['name' => 'instructors', 'tab' => 'teaching'],
            ],
        ]);

        lynx()->templates->update('session', ['fields' => [
            ['name' => 'title', 'required' => true, 'notes' => ['en' => 'The course and the date, such as "Yoga teacher training, 2 November 2026". Search engines show it as the event\'s name.', 'pt' => 'O curso e a data, por exemplo "Formação de professores de yoga, 2 de novembro de 2026". Os motores de pesquisa mostram-no como nome do evento.']],
            ['name' => 'starts', 'required' => true, 'icon' => 'calendar', 'width' => 50],
            ['name' => 'location', 'required' => true, 'icon' => 'map-pin', 'width' => 50],
            ['name' => 'seats', 'required' => true, 'column' => 'sidebar'],
            ['name' => 'price', 'column' => 'sidebar', 'notes' => ['en' => 'Leave empty to use the course price.', 'pt' => 'Deixe vazio para usar o preço do curso.']],
            ['name' => 'availability', 'column' => 'sidebar'],
        ]]);
        // #endregion layouts
    }

    private function roles(): void
    {
        // #region roles
        $grant = fn (string $permission, string $branch, array $templates = []): array => array_filter([
            'permission' => $permission,
            'branches' => [lynx()->pages->get($branch)?->id],
            'templates' => $templates,
        ]);

        // Runs the catalogue: courses and their sessions, nothing else
        lynx()->run(SaveRole::class, ['name' => 'course-manager', 'label' => 'Course manager', 'grants' => [
            ['permission' => 'admin.access'],
            $grant('pages.view', '/courses/'),
            $grant('pages.edit', '/courses/'),
            $grant('pages.create', '/courses/', ['course', 'session']),
            $grant('pages.publish', '/courses/'),
            $grant('pages.status', '/courses/'),
        ]]);

        // Writes the news, and may publish it
        lynx()->run(SaveRole::class, ['name' => 'news-writer', 'label' => 'News writer', 'grants' => [
            ['permission' => 'admin.access'],
            $grant('pages.view', '/posts/'),
            $grant('pages.edit', '/posts/'),
            $grant('pages.create', '/posts/', ['post']),
            $grant('pages.publish', '/posts/'),
        ]]);
        // #endregion roles
    }
}
