<?php

declare(strict_types=1);

namespace LynxCms\Seo\Support;

use LynxCms\Core\Pages\Page;
use LynxCms\Media\Values\MediaList;
use LynxCms\Media\Values\Pageimage;

/**
 * The image social networks show for a page: the first image of the first field that has one,
 * cropped to the size they recommend. Needs lynxcms/media; without it there is none.
 *
 * @internal
 */
final class ShareImage
{
    /** Open Graph's recommended size: 1.91 to 1. */
    public const int WIDTH = 1200;

    public const int HEIGHT = 630;

    /**
     * @param  list<array{0: ?Page, 1: string}>  $candidates  [page, field] pairs, in order of preference
     * @return array{url: string, width: int, height: int, alt: string}|null
     */
    public function first(array $candidates): ?array
    {
        if (! class_exists(MediaList::class)) {
            return null;
        }

        foreach ($candidates as [$page, $field]) {
            $image = $this->imageOf($page, $field);

            if ($image instanceof Pageimage) {
                $card = $image->size(self::WIDTH, self::HEIGHT);

                return ['url' => AbsoluteUrl::of($card->url()), 'width' => $card->width(), 'height' => $card->height(), 'alt' => $image->description()];
            }
        }

        return null;
    }

    private function imageOf(?Page $page, string $field): ?Pageimage
    {
        $value = $page?->templateField($field) === null ? null : $page->get($field);
        // A field limited to one image gives the image itself, others a list.
        $image = $value instanceof MediaList ? $value->first() : $value;

        return $image instanceof Pageimage ? $image : null;
    }
}
