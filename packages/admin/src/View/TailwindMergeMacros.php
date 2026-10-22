<?php

declare(strict_types=1);

namespace LynxCms\Admin\View;

use Illuminate\Contracts\Container\Container;
use Illuminate\View\ComponentAttributeBag;
use LynxCms\Core\Support\Value;
use TailwindMerge\Contracts\TailwindMergeContract;
use TailwindMerge\TailwindMerge;

/**
 * The attribute bag macros BlatUI components call ($attributes->twMerge(...)), the same ones
 * gehrisandro/tailwind-merge-laravel adds. That bridge pins Guzzle 7, so the admin registers them
 * itself on tailwind-merge-php, and leaves alone a host app that already has them (ADR-0034).
 *
 * @internal
 */
final class TailwindMergeMacros
{
    /**
     * Merged class strings by input. Rows repeat the same classes, and merging is slow.
     *
     * @var array<string, string>
     */
    private static array $merged = [];

    public static function register(Container $app): void
    {
        if (! $app->bound(TailwindMergeContract::class)) {
            $app->singleton(TailwindMergeContract::class, static fn (): TailwindMergeContract => TailwindMerge::factory()->make());
        }

        if (ComponentAttributeBag::hasMacro('twMerge')) {
            return;
        }

        ComponentAttributeBag::macro('twMerge', function (mixed ...$classes): ComponentAttributeBag {
            /** @var ComponentAttributeBag $this */
            $this->offsetSet('class', TailwindMergeMacros::merge($classes, $this->get('class', '')));

            return $this;
        });

        ComponentAttributeBag::macro('twMergeFor', function (string $for, mixed ...$classes): ComponentAttributeBag {
            /** @var ComponentAttributeBag $this */
            $this->offsetSet('class', TailwindMergeMacros::merge($classes, $this->get('class'.($for !== '' ? ':'.$for : ''), '')));

            return $this->only('class');
        });

        ComponentAttributeBag::macro('withoutTwMergeClasses', function (): ComponentAttributeBag {
            /** @var ComponentAttributeBag $this */
            return $this->whereDoesntStartWith('class:');
        });
    }

    /**
     * Later classes win over earlier ones that set the same thing (px-2 then px-4 gives px-4).
     *
     * @param  array<array-key, mixed>  $classes
     */
    public static function merge(array $classes, mixed $last): string
    {
        $strings = array_map(static fn (mixed $class): string => is_string($class) ? $class : '', [...$classes, $last]);
        $key = implode("\0", $strings);

        return self::$merged[$key] ??= Value::string(app(TailwindMergeContract::class)->merge(...$strings));
    }
}
