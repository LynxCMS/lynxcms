<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Atlas Training's languages, fields and templates. Run it once on a fresh install
 * (php artisan db:seed --class=AtlasSchemaSeeder), then commit the schema files
 * (php artisan lynx:schema:export) and keep environments in step with them.
 */
final class AtlasSchemaSeeder extends Seeder
{
    public function run(): void
    {
        $this->languages();
        $this->fields();
        $this->templates();
    }

    private function languages(): void
    {
        // #region languages
        lynx()->languages->update('default', ['code' => 'en', 'name' => 'English', 'locale' => 'en']);
        lynx()->languages->add('pt', 'Português', locale: 'pt-PT', fallback: 'en');
        // #endregion languages
    }

    private function fields(): void
    {
        // #region fields
        // Text that visitors read in their language
        lynx()->fields->add('summary', 'textarea', translatable: true, label: 'Summary');
        lynx()->fields->add('body', 'textarea', translatable: true, label: 'Body', settings: ['format' => 'html']);
        lynx()->fields->add('heading', 'text', translatable: true, label: 'Heading');
        lynx()->fields->add('text', 'textarea', translatable: true, label: 'Text');

        // Courses
        lynx()->fields->add('level', 'choice', label: 'Level', settings: ['input' => 'radios', 'options' => [
            ['value' => 'beginner', 'label' => ['en' => 'Beginner', 'pt' => 'Iniciante']],
            ['value' => 'intermediate', 'label' => ['en' => 'Intermediate', 'pt' => 'Intermédio']],
            ['value' => 'advanced', 'label' => ['en' => 'Advanced', 'pt' => 'Avançado']],
        ]]);
        lynx()->fields->add('hours', 'integer', label: 'Hours of teaching');
        lynx()->fields->add('price', 'decimal', label: 'Price (EUR)');
        lynx()->fields->add('curriculum', 'repeater', label: 'Curriculum', settings: [
            'fields' => [['name' => 'heading', 'required' => true], ['name' => 'text']],
            'item_label' => '{heading}',
            'add_label' => 'Add a module',
        ]);
        lynx()->fields->add('instructors', 'page', label: 'Instructors', settings: ['templates' => ['instructor']]);
        lynx()->fields->add('photos', 'images', label: 'Photos', settings: ['max_files' => 6]);

        // Sessions: one dated run of a course, which people book
        lynx()->fields->add('starts', 'date', label: 'Starts', settings: ['time' => true]);
        lynx()->fields->add('location', 'page', label: 'Location', settings: ['multiple' => false, 'templates' => ['location'], 'input' => 'select']);
        lynx()->fields->add('seats', 'integer', label: 'Seats left');
        lynx()->fields->add('availability', 'choice', label: 'Availability', settings: ['input' => 'radios', 'options' => [
            ['value' => 'scheduled', 'label' => ['en' => 'Open for booking', 'pt' => 'Inscrições abertas']],
            ['value' => 'cancelled', 'label' => ['en' => 'Cancelled', 'pt' => 'Cancelada']],
        ]]);

        // Locations
        lynx()->fields->add('street', 'text', label: 'Street');
        lynx()->fields->add('city', 'text', label: 'City');
        lynx()->fields->add('postal_code', 'text', label: 'Postal code');

        // The home page's sections
        lynx()->fields->add('icon', 'icon', label: 'Icon');
        lynx()->fields->add('figure', 'text', label: 'Figure');
        lynx()->fields->add('person', 'text', label: 'Person');
        lynx()->fields->add('rating', 'integer', label: 'Rating (1 to 5)');
        lynx()->fields->add('highlights', 'repeater', label: 'Highlights', settings: [
            'fields' => [['name' => 'icon'], ['name' => 'heading', 'required' => true], ['name' => 'text']], 'max' => 6, 'item_label' => '{heading}',
        ]);
        lynx()->fields->add('stats', 'repeater', label: 'Figures', settings: [
            'fields' => [['name' => 'figure', 'required' => true], ['name' => 'heading', 'label' => 'Caption']], 'max' => 4, 'item_label' => '{figure} {heading}',
        ]);
        lynx()->fields->add('steps', 'repeater', label: 'How it works', settings: [
            'fields' => [['name' => 'heading', 'required' => true], ['name' => 'text']], 'max' => 4, 'item_label' => '{heading}',
        ]);
        lynx()->fields->add('testimonials', 'repeater', label: 'Testimonials', settings: [
            'fields' => [['name' => 'text', 'label' => 'Quote', 'required' => true], ['name' => 'person', 'required' => true], ['name' => 'rating']], 'item_label' => '{person}',
        ]);
        lynx()->fields->add('faqs', 'repeater', label: 'Questions', settings: [
            'fields' => [['name' => 'heading', 'label' => 'Question', 'required' => true], ['name' => 'text', 'label' => 'Answer', 'required' => true]], 'item_label' => '{heading}',
        ]);
        lynx()->fields->add('featured', 'page', label: 'Featured courses', settings: ['templates' => ['course']]);

        // Site-wide details, on the settings page
        lynx()->fields->add('email', 'email', label: 'Email');
        lynx()->fields->add('phone', 'text', label: 'Phone');

        // News
        lynx()->fields->add('published_on', 'date', label: 'Published on');

        // Course materials (chapter 8): private, so only the people who booked can download them
        lynx()->fields->add('downloads', 'files', label: 'Downloads', settings: ['private' => true, 'extensions' => 'pdf zip mp3 mp4']);
        // #endregion fields
    }

    private function templates(): void
    {
        // #region templates
        // lynx:install made the home template and page; give it the landing page's sections
        lynx()->templates->update('home', ['add' => ['summary', 'highlights', 'stats', 'featured', 'steps', 'testimonials', 'faqs']]);

        lynx()->templates->add('courses', fields: [['name' => 'title', 'required' => true], 'summary'], label: 'Courses', singleton: true);
        lynx()->templates->add('course', fields: [
            ['name' => 'title', 'required' => true], ['name' => 'summary', 'required' => true], 'body',
            'level', 'hours', 'price', 'curriculum', 'instructors', 'photos',
        ], label: 'Course');
        lynx()->templates->add('session', fields: [
            ['name' => 'title', 'required' => true], ['name' => 'starts', 'required' => true], ['name' => 'location', 'required' => true],
            ['name' => 'seats', 'required' => true], 'price', 'availability',
        ], label: 'Session');

        lynx()->templates->add('instructors', fields: [['name' => 'title', 'required' => true], 'summary'], label: 'Instructors', singleton: true);
        lynx()->templates->add('instructor', fields: [['name' => 'title', 'required' => true], 'summary', 'body', 'photos'], label: 'Instructor');

        lynx()->templates->add('locations', fields: [['name' => 'title', 'required' => true]], label: 'Locations', singleton: true);
        lynx()->templates->add('location', fields: [['name' => 'title', 'required' => true], 'street', 'city', 'postal_code', 'photos'], label: 'Location');

        lynx()->templates->add('posts', fields: [['name' => 'title', 'required' => true], 'summary'], label: 'News', singleton: true);
        lynx()->templates->add('post', fields: [['name' => 'title', 'required' => true], 'summary', 'body', 'published_on', 'photos'], label: 'News post');

        lynx()->templates->add('materials', fields: [['name' => 'title', 'required' => true], 'body', 'downloads'], label: 'Course materials');
        lynx()->templates->add('page', fields: [['name' => 'title', 'required' => true], 'summary', 'body'], label: 'Page');
        lynx()->templates->add('settings', fields: ['email', 'phone'], label: 'Settings', hasUrl: false, singleton: true);

        // Where pages may go: the admin offers only these, and the API refuses the rest
        $children = [
            'home' => ['courses', 'instructors', 'locations', 'posts', 'page', 'settings'],
            'courses' => ['course'], 'course' => ['session', 'materials'], 'session' => [], 'materials' => [],
            'instructors' => ['instructor'], 'instructor' => [],
            'locations' => ['location'], 'location' => [],
            'posts' => ['post'], 'post' => [], 'page' => [],
        ];

        foreach ($children as $template => $allowed) {
            lynx()->templates->update($template, ['allow_children' => $allowed !== [], 'child_templates' => $allowed]);
        }
        // #endregion templates
    }
}
