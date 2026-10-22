<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Validation\ValidationException;
use LynxCms\Core\Actions\Pages\SavePage;
use LynxCms\Core\Pages\Page;
use LynxCms\Seo\Actions\EnableSeo;
use LynxCms\Seo\JsonLd\JsonLd;
use LynxCms\Seo\JsonLd\JsonLdGraph;
use LynxCms\Seo\SeoSchema;

beforeEach(function (): void {
    config(['lynx-seo.block_non_production' => false]);
    courseSite();
    lynx()->run(EnableSeo::class, ['template' => 'course']);
    ['alpha' => $alpha] = createCourses('alpha');
    $this->alpha = $alpha;
    jsonLdSettings(['seo_site_name' => ['default' => 'Lynx Academy', 'pt' => 'Academia Lynx'], SeoSchema::ORG_NAME => 'Lynx Ltd', SeoSchema::ORG_SAME_AS => "https://github.com/lynxcms\nhttps://x.com/lynxcms"]);
});

/** @param array<string, mixed> $values */
function jsonLdSettings(array $values): void
{
    lynx()->run(SavePage::class, ['id' => lynx()->settings(SeoSchema::SETTINGS_TEMPLATE)?->id, 'values' => $values]);
}

/** @param array<string, string> $mappings template => [type, lines] */
function mapSchemas(array $mappings): void
{
    $items = [];

    foreach ($mappings as $template => [$type, $lines]) {
        $items[] = ['type' => 'mapping', 'values' => [SeoSchema::SCHEMA_TEMPLATE => $template, SeoSchema::SCHEMA_TYPE => $type, SeoSchema::SCHEMA_MAP => $lines]];
    }

    jsonLdSettings([SeoSchema::SCHEMAS => $items]);
}

/** @return list<array<string, mixed>> */
function graphOf(Page|string $page, string $language = 'default'): array
{
    $page = $page instanceof Page ? lynx()->pages->get($page->id) : lynx()->pages->get($page);

    return lynx()->language($language, fn (): array => lynx()->seo($page)->jsonLd['@graph']);
}

/** @param list<array<string, mixed>> $graph */
function nodeOf(array $graph, string $type): ?array
{
    return collect($graph)->first(fn (array $node): bool => $node['@type'] === $type);
}

/** Event, venue and location templates, as a site with events and shops would have. */
function eventSite(): void
{
    lynx()->fields->add('starts', 'date', settings: ['time' => true]);
    lynx()->fields->add('street', 'text');
    lynx()->fields->add('lat', 'decimal');
    lynx()->fields->add('venue', 'page', settings: ['multiple' => false, 'templates' => ['venue']]);
    lynx()->fields->add('state', 'choice', settings: ['options' => [['value' => 'scheduled', 'label' => 'Scheduled'], ['value' => 'cancelled', 'label' => 'Cancelled']]]);
    lynx()->fields->add('day', 'text');
    lynx()->fields->add('opens', 'text');
    lynx()->fields->add('hours', 'repeater', settings: ['fields' => [['name' => 'day'], ['name' => 'opens']]]);
    lynx()->templates->add('venue', fields: ['title', 'street', 'lat']);
    lynx()->templates->add('event', fields: ['title', 'summary', 'starts', 'venue', 'price', 'state']);
    lynx()->templates->add('shop', fields: ['title', 'street', 'hours']);
}

it('describes every page as a web page of the site, with its breadcrumbs', function (): void {
    $graph = graphOf($this->alpha);

    expect(nodeOf($graph, 'WebSite'))->toMatchArray(['@id' => 'http://localhost/#website', 'name' => 'Lynx Academy', 'url' => 'http://localhost/'])
        ->and(nodeOf($graph, 'Organization'))->toMatchArray(['@id' => 'http://localhost/#organization', 'name' => 'Lynx Ltd', 'sameAs' => ['https://github.com/lynxcms', 'https://x.com/lynxcms']])
        ->and(nodeOf($graph, 'WebPage'))->toMatchArray(['@id' => 'http://localhost/courses/alpha#webpage', 'url' => 'http://localhost/courses/alpha', 'name' => 'Alpha | Lynx Academy', 'isPartOf' => ['@id' => 'http://localhost/#website']])
        ->and(array_column(nodeOf($graph, 'BreadcrumbList')['itemListElement'], 'name'))->toBe(['Home', 'Courses', 'Alpha'])
        ->and(nodeOf($graph, 'BreadcrumbList')['itemListElement'][2])->toMatchArray(['position' => 3, 'item' => 'http://localhost/courses/alpha']);
});

it('describes a mapped template\'s pages as their schema type, in each language', function (): void {
    mapSchemas(['course' => ['Course', "name: @title\ndescription: summary\nprovider.name: =Lynx Ltd\noffers.price: price\noffers.priceCurrency: =EUR"]]);
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'names' => ['pt' => 'alfa'], 'values' => ['title' => ['pt' => 'Alfa'], 'summary' => ['default' => '<p>All about <b>alpha</b>.</p>'], 'price' => 120]]);

    $course = nodeOf(graphOf($this->alpha), 'Course');

    expect($course)->toMatchArray([
        '@id' => 'http://localhost/courses/alpha#course',
        'name' => 'Alpha',
        'description' => 'All about alpha.',
        'provider' => ['@type' => 'Organization', 'name' => 'Lynx Ltd'],
        'offers' => ['@type' => 'Offer', 'price' => 120, 'priceCurrency' => 'EUR'],
    ])
        ->and(nodeOf(graphOf($this->alpha), 'WebPage')['mainEntity'])->toBe(['@id' => 'http://localhost/courses/alpha#course'])
        ->and(nodeOf(graphOf($this->alpha, 'pt'), 'Course'))->toMatchArray(['name' => 'Alfa', 'description' => 'All about alpha.', '@id' => 'http://localhost/pt/cursos/alfa#course']);
});

it('marks articles as articles for social networks too', function (): void {
    mapSchemas(['course' => ['Article', "headline: @title\ndatePublished: @published\nauthor.name: =Ana Lima"]]);

    expect(nodeOf(graphOf($this->alpha), 'Article'))->toHaveKeys(['headline', 'datePublished'])
        ->and(nodeOf(graphOf($this->alpha), 'Article')['author'])->toBe(['@type' => 'Person', 'name' => 'Ana Lima'])
        ->and(lynx()->seo(lynx()->pages->get($this->alpha->id))->type)->toBe('article');
});

it('credits the site\'s organization as author, provider or organizer', function (): void {
    mapSchemas(['course' => ['Course', "name: @title\nprovider: @organization"]]);
    expect(nodeOf(graphOf($this->alpha), 'Course')['provider'])->toBe(['@id' => 'http://localhost/#organization']);

    mapSchemas(['course' => ['Article', "headline: @title\nauthor: @organization"]]);
    expect(nodeOf(graphOf($this->alpha), 'Article')['author'])->toBe(['@id' => 'http://localhost/#organization']);

    jsonLdSettings([SeoSchema::ORG_NAME => null, 'seo_site_name' => ['default' => null, 'pt' => null]]);
    expect(nodeOf(graphOf($this->alpha), 'Article'))->not->toHaveKey('author');
});

it('refuses the organization where a property takes no page', function (): void {
    mapSchemas(['course' => ['Article', 'headline: @organization']]);
})->throws(ValidationException::class, '[@organization] only fits');

it('embeds the mapped page an event takes place in, and converts dates, numbers and states', function (): void {
    eventSite();
    mapSchemas([
        'event' => ['Event', "name: @title\nstartDate: starts\nlocation: venue\neventStatus: state\noffers.price: price\noffers.priceCurrency: =EUR"],
        'venue' => ['Place', "name: @title\naddress.streetAddress: street\ngeo.latitude: lat"],
    ]);
    $venue = lynx()->run(SavePage::class, ['template' => 'venue', 'parent' => '/', 'names' => ['default' => 'hall'], 'values' => ['title' => ['default' => 'Main hall'], 'street' => 'Rua Augusta 1', 'lat' => '38.71']])->valueAs(Page::class);
    $event = lynx()->run(SavePage::class, ['template' => 'event', 'parent' => '/', 'names' => ['default' => 'launch'], 'values' => ['title' => ['default' => 'Launch'], 'starts' => '2026-11-20 18:30', 'venue' => $venue->id, 'price' => 15, 'state' => 'cancelled']])->valueAs(Page::class);

    $graph = graphOf($event);

    expect(nodeOf($graph, 'Event'))->toMatchArray([
        'name' => 'Launch',
        'startDate' => '2026-11-20T18:30:00+00:00',
        'location' => ['@id' => 'http://localhost/hall#place'],
        'eventStatus' => 'https://schema.org/EventCancelled',
        'offers' => ['@type' => 'Offer', 'price' => 15, 'priceCurrency' => 'EUR'],
    ])
        ->and(nodeOf($graph, 'Place'))->toMatchArray([
            '@id' => 'http://localhost/hall#place',
            'name' => 'Main hall',
            'address' => ['@type' => 'PostalAddress', 'streetAddress' => 'Rua Augusta 1'],
            'geo' => ['@type' => 'GeoCoordinates', 'latitude' => 38.71],
        ]);
});

it('names an unmapped page a page reference points to', function (): void {
    eventSite();
    mapSchemas(['event' => ['Event', "name: @title\nlocation: venue"]]);
    $venue = lynx()->run(SavePage::class, ['template' => 'venue', 'parent' => '/', 'names' => ['default' => 'hall'], 'values' => ['title' => ['default' => 'Main hall']]])->valueAs(Page::class);
    $event = lynx()->run(SavePage::class, ['template' => 'event', 'parent' => '/', 'names' => ['default' => 'launch'], 'values' => ['title' => ['default' => 'Launch'], 'venue' => $venue->id]])->valueAs(Page::class);

    expect(nodeOf(graphOf($event), 'Event')['location'])->toBe(['@type' => 'Place', 'name' => 'Main hall', 'url' => 'http://localhost/hall']);
});

it('makes one node per enabled repeater item and ties local businesses to the organization', function (): void {
    eventSite();
    mapSchemas(['shop' => ['Store', "name: @title\naddress.streetAddress: street\nopeningHoursSpecification: hours\nopeningHoursSpecification.dayOfWeek: day\nopeningHoursSpecification.opens: opens"]]);
    $shop = lynx()->run(SavePage::class, ['template' => 'shop', 'parent' => '/', 'names' => ['default' => 'baixa'], 'values' => ['title' => ['default' => 'Baixa'], 'hours' => [
        ['values' => ['day' => 'Monday', 'opens' => '09:00']],
        ['values' => ['day' => 'Sunday', 'opens' => '11:00'], 'enabled' => false],
    ]]])->valueAs(Page::class);

    $store = nodeOf(graphOf($shop), 'Store');

    expect($store['openingHoursSpecification'])->toBe([['@type' => 'OpeningHoursSpecification', 'dayOfWeek' => 'Monday', 'opens' => '09:00']])
        ->and($store['parentOrganization'])->toBe(['@id' => 'http://localhost/#organization']);
});

it('builds questions and answers from a repeater', function (): void {
    lynx()->fields->add('question', 'text', translatable: true);
    lynx()->fields->add('answer', 'textarea', translatable: true);
    lynx()->fields->add('faq', 'repeater', settings: ['fields' => [['name' => 'question'], ['name' => 'answer']]]);
    lynx()->templates->update('course', ['add' => ['faq']]);
    mapSchemas(['course' => ['FAQPage', "mainEntity: faq\nmainEntity.name: question\nmainEntity.acceptedAnswer.text: answer"]]);
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => ['faq' => [['values' => ['question' => ['default' => 'How long?', 'pt' => 'Quanto tempo?'], 'answer' => ['default' => 'Two weeks.']]]]]]);

    expect(nodeOf(graphOf($this->alpha, 'pt'), 'FAQPage')['mainEntity'])->toBe([
        ['@type' => 'Question', 'name' => 'Quanto tempo?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Two weeks.']],
    ]);
});

it('refuses mappings that would make broken structured data, on the right item', function (string $template, string $type, string $lines, string $message): void {
    lynx()->fields->add('photo', 'images', settings: ['max_files' => 1]);

    expect(fn () => mapSchemas([$template => [$type, $lines]]))
        ->toThrow(function (ValidationException $e) use ($message): void {
            expect($e->errors())->toHaveKey('values.'.SeoSchema::SCHEMAS.'.0.'.SeoSchema::SCHEMA_MAP)
                ->and(implode(' ', $e->errors()['values.'.SeoSchema::SCHEMAS.'.0.'.SeoSchema::SCHEMA_MAP]))->toContain($message);
        });
})->with([
    'unknown template' => ['nope', 'Article', 'headline: @title', 'Template [nope]'],
    'unknown property' => ['course', 'Article', 'colour: @title', 'Article has no property [colour]'],
    'unknown field' => ['course', 'Article', 'headline: nope', 'no field [nope]'],
    'unknown built in' => ['course', 'Article', 'headline: @nope', '[@nope]'],
    'wrong kind of field' => ['course', 'Article', 'image: summary', 'needs an image field'],
    'bad line' => ['course', 'Article', 'just words', 'property: source'],
]);

it('refuses the same template mapped twice', function (): void {
    expect(fn () => mapSchemas(['course' => ['Article', 'headline: @title'], 'courses' => ['CreativeWork', 'name: @title']]))->not->toThrow(ValidationException::class);

    lynx()->run(SavePage::class, ['id' => lynx()->settings(SeoSchema::SETTINGS_TEMPLATE)?->id, 'values' => [SeoSchema::SCHEMAS => [
        ['type' => 'mapping', 'values' => [SeoSchema::SCHEMA_TEMPLATE => 'course', SeoSchema::SCHEMA_TYPE => 'Article', SeoSchema::SCHEMA_MAP => 'headline: @title']],
        ['type' => 'mapping', 'values' => [SeoSchema::SCHEMA_TEMPLATE => 'course', SeoSchema::SCHEMA_TYPE => 'Course', SeoSchema::SCHEMA_MAP => 'name: @title']],
    ]]]);
})->throws(ValidationException::class, 'already mapped');

it('lets developers add to the graph', function (): void {
    app(JsonLd::class)->extend(fn (Page $page, JsonLdGraph $graph): JsonLdGraph => $graph->add(['@type' => 'Thing', 'name' => 'Extra '.$page->name()]));

    expect(nodeOf(graphOf($this->alpha), 'Thing'))->toBe(['@type' => 'Thing', 'name' => 'Extra alpha']);
});

it('prints the graph in the head, safe from what editors write', function (): void {
    lynx()->run(SavePage::class, ['id' => $this->alpha->id, 'values' => [SeoSchema::TITLE => ['default' => 'Fish & chips > all']]]);
    $html = Blade::render('<x-lynx-seo :page="$page" />', ['page' => lynx()->pages->get($this->alpha->id)]);

    expect($html)->toContain('<script type="application/ld+json">{"@context":"https://schema.org","@graph":[')
        ->toContain('Fish \\u0026 chips \\u003E all')
        ->and(substr_count($html, '</script>'))->toBe(1);
});
