<?php

declare(strict_types=1);

namespace LynxCms\Seo\JsonLd;

/**
 * The schema.org types a template can be mapped to, with the properties editors may fill and the
 * kind of value each takes. Dotted properties build nested nodes (address.streetAddress makes a
 * PostalAddress); a list property takes a repeater or matrix, one node per enabled item.
 *
 * @api
 */
final class SchemaTypes
{
    public const string TEXT = 'text';

    public const string URL = 'url';

    public const string IMAGE = 'image';

    public const string DATE = 'date';

    public const string NUMBER = 'number';

    public const string PAGE = 'page';

    public const string LIST = 'list';

    public const string ENUM = 'enum';

    /** One URL per line, e.g. sameAs. */
    public const string LINES = 'lines';

    /** The @type of the node a property makes. */
    public const array OBJECTS = [
        'address' => 'PostalAddress',
        'geo' => 'GeoCoordinates',
        'offers' => 'Offer',
        'author' => 'Person',
        'provider' => 'Organization',
        'publisher' => 'Organization',
        'organizer' => 'Organization',
        'worksFor' => 'Organization',
        'performer' => 'Person',
        'brand' => 'Brand',
        'location' => 'Place',
        'openingHoursSpecification' => 'OpeningHoursSpecification',
        'mainEntity' => 'Question',
        'acceptedAnswer' => 'Answer',
    ];

    /** Values editors write (a choice's value), and the schema.org enumeration member each means. */
    public const array ENUMS = [
        'eventStatus' => ['scheduled' => 'EventScheduled', 'cancelled' => 'EventCancelled', 'canceled' => 'EventCancelled', 'postponed' => 'EventPostponed', 'rescheduled' => 'EventRescheduled', 'online' => 'EventMovedOnline'],
        'eventAttendanceMode' => ['offline' => 'OfflineEventAttendanceMode', 'online' => 'OnlineEventAttendanceMode', 'mixed' => 'MixedEventAttendanceMode'],
        'offers.availability' => ['in_stock' => 'InStock', 'out_of_stock' => 'OutOfStock', 'preorder' => 'PreOrder', 'sold_out' => 'SoldOut', 'limited' => 'LimitedAvailability'],
    ];

    /** Field types each kind of property can read. */
    public const array FIELD_TYPES = [
        self::IMAGE => ['images'],
        self::DATE => ['date'],
        self::NUMBER => ['integer', 'decimal', 'text'],
        self::PAGE => ['page'],
        self::LIST => ['repeater', 'matrix'],
        self::ENUM => ['choice', 'text'],
        self::LINES => ['textarea', 'text'],
        self::URL => ['url', 'text', 'page', 'images'],
        self::TEXT => ['text', 'textarea', 'email', 'url', 'choice', 'integer', 'decimal', 'date', 'page'],
    ];

    /** Pages of these types are articles to social networks (og:type). */
    public const array ARTICLES = ['Article', 'BlogPosting', 'NewsArticle'];

    /** Branches of the site's organization (parentOrganization). */
    public const array LOCAL_BUSINESSES = ['LocalBusiness', 'Store', 'Restaurant', 'ProfessionalService', 'LodgingBusiness'];

    private const array THING = ['name' => self::TEXT, 'description' => self::TEXT, 'image' => self::IMAGE, 'url' => self::URL];

    private const array ADDRESS = ['streetAddress' => self::TEXT, 'addressLocality' => self::TEXT, 'postalCode' => self::TEXT, 'addressRegion' => self::TEXT, 'addressCountry' => self::TEXT];

    private const array OFFER = ['price' => self::NUMBER, 'priceCurrency' => self::TEXT, 'url' => self::URL, 'availability' => self::ENUM];

    /** @return list<string> every type, e.g. "Article" */
    public static function names(): array
    {
        return array_keys(self::all());
    }

    public static function has(string $type): bool
    {
        return array_key_exists($type, self::all());
    }

    /**
     * @return array<string, string> property => kind
     */
    public static function properties(string $type): array
    {
        return self::all()[$type] ?? [];
    }

    /** The list property a dotted property belongs to ("mainEntity" for "mainEntity.name"), if any. */
    public static function listOf(string $type, string $property): ?string
    {
        foreach (self::properties($type) as $name => $kind) {
            if ($kind === self::LIST && str_starts_with($property, $name.'.')) {
                return $name;
            }
        }

        return null;
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function all(): array
    {
        $creative = [...self::THING, 'headline' => self::TEXT, 'datePublished' => self::DATE, 'dateModified' => self::DATE, 'author' => self::PAGE, 'author.name' => self::TEXT, 'author.url' => self::URL, 'publisher' => self::PAGE];
        $place = [...self::THING, 'telephone' => self::TEXT, ...self::prefixed('address', self::ADDRESS), 'geo.latitude' => self::NUMBER, 'geo.longitude' => self::NUMBER];
        $business = [...$place, 'email' => self::TEXT, 'priceRange' => self::TEXT, 'openingHoursSpecification' => self::LIST, 'openingHoursSpecification.dayOfWeek' => self::TEXT, 'openingHoursSpecification.opens' => self::TEXT, 'openingHoursSpecification.closes' => self::TEXT];

        return [
            ...array_fill_keys([...self::ARTICLES, 'CreativeWork'], $creative),
            'Event' => [...self::THING, 'startDate' => self::DATE, 'endDate' => self::DATE, 'eventStatus' => self::ENUM, 'eventAttendanceMode' => self::ENUM, 'location' => self::PAGE, 'location.name' => self::TEXT, ...self::prefixed('location.address', self::ADDRESS), 'organizer' => self::PAGE, 'organizer.name' => self::TEXT, 'organizer.url' => self::URL, 'performer.name' => self::TEXT, ...self::prefixed('offers', self::OFFER)],
            'Product' => [...self::THING, 'sku' => self::TEXT, 'brand.name' => self::TEXT, ...self::prefixed('offers', self::OFFER)],
            'Course' => [...self::THING, 'provider' => self::PAGE, 'provider.name' => self::TEXT, 'provider.url' => self::URL, ...self::prefixed('offers', self::OFFER)],
            'Place' => $place,
            ...array_fill_keys(self::LOCAL_BUSINESSES, $business),
            'Person' => [...self::THING, 'jobTitle' => self::TEXT, 'email' => self::TEXT, 'telephone' => self::TEXT, 'sameAs' => self::LINES, 'worksFor' => self::PAGE, 'worksFor.name' => self::TEXT, 'worksFor.url' => self::URL],
            'FAQPage' => ['mainEntity' => self::LIST, 'mainEntity.name' => self::TEXT, 'mainEntity.acceptedAnswer.text' => self::TEXT],
        ];
    }

    /**
     * @param  array<string, string>  $properties
     * @return array<string, string>
     */
    private static function prefixed(string $prefix, array $properties): array
    {
        $prefixedProperties = [];

        foreach ($properties as $name => $kind) {
            $prefixedProperties[$prefix.'.'.$name] = $kind;
        }

        return $prefixedProperties;
    }
}
