<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\Actions\MapTemplate;
use LynxCms\Seo\SeoSchema;

/**
 * Atlas Training in search results: SEO fields for the pages that matter, the site's name and
 * title pattern, and structured data for courses, sessions, studios and the home page's questions.
 */
final class AtlasSeoSeeder extends Seeder
{
    public function run(): void
    {
        // #region settings
        foreach (['home', 'courses', 'course', 'session', 'instructor', 'location', 'posts', 'post', 'page'] as $template) {
            lynx()->run(EnableSeo::class, ['template' => $template]);
        }

        lynx()->run(SavePage::class, ['id' => lynx()->settings(SeoSchema::SETTINGS_TEMPLATE)?->id, 'values' => [
            SeoSchema::SITE_NAME => ['en' => 'Atlas Training', 'pt' => 'Atlas Training'],
            SeoSchema::TITLE_PATTERN => ['en' => '{title} | {site}', 'pt' => '{title} | {site}'],
            SeoSchema::DEFAULT_DESCRIPTION => ['en' => 'Certified yoga, pilates and nutrition courses in Lisbon and Porto.', 'pt' => 'Cursos certificados de yoga, pilates e nutrição em Lisboa e no Porto.'],
            SeoSchema::ORG_NAME => 'Atlas Training',
            SeoSchema::ORG_SAME_AS => "https://www.instagram.com/atlastraining\nhttps://www.youtube.com/@atlastraining",
        ]]);
        // #endregion settings

        // #region structured-data
        lynx()->run(MapTemplate::class, ['template' => 'course', 'type' => 'Course', 'map' => [
            'name' => '@title', 'description' => 'summary', 'image' => 'photos', 'url' => '@url',
            'provider' => '@organization', 'offers.price' => 'price', 'offers.priceCurrency' => '=EUR',
        ]]);

        lynx()->run(MapTemplate::class, ['template' => 'session', 'type' => 'Event', 'map' => [
            'name' => '@title', 'url' => '@url', 'startDate' => 'starts', 'eventStatus' => 'availability',
            'eventAttendanceMode' => '=offline', 'location' => 'location', 'organizer' => '@organization',
            'offers.price' => 'price', 'offers.priceCurrency' => '=EUR',
        ]]);

        lynx()->run(MapTemplate::class, ['template' => 'location', 'type' => 'Place', 'map' => [
            'name' => '@title', 'address.streetAddress' => 'street', 'address.addressLocality' => 'city',
            'address.postalCode' => 'postal_code', 'address.addressCountry' => '=PT',
        ]]);

        lynx()->run(MapTemplate::class, ['template' => 'home', 'type' => 'FAQPage', 'map' => [
            'mainEntity' => 'faqs', 'mainEntity.name' => 'heading', 'mainEntity.acceptedAnswer.text' => 'text',
        ]]);
        // #endregion structured-data
    }
}
