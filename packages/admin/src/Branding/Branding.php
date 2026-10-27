<?php

declare(strict_types=1);

namespace LynxCms\Admin\Branding;

use LynxCms\Core\Models\Setting;
use LynxCms\Core\Support\Value;

/**
 * The site's name, logo and colours in the admin (ADR-0084): what superusers saved under Settings
 * (admin.branding), else config('lynx-admin.branding'). The sign-in screens and the top of the
 * sidebar show them; the accent re-colours BlatUI's primary tokens.
 *
 * @internal
 */
final readonly class Branding
{
    /** The setting saved under Settings in the admin, JSON: name, logo, background, accent. */
    public const string SETTING = 'admin.branding';

    /** Where uploaded logos are kept: private storage, served by the admin's asset route. */
    public const string DISK = 'local';

    public const string FOLDER = 'lynx-admin';

    /** A stored logo's file name: its hash keeps old copies out of browser caches. */
    public const string LOGO_FILE = '/^logo-[a-f0-9]{16}\.(png|jpg|webp|svg)$/';

    public const string HEX = '/^#[0-9a-fA-F]{6}$/';

    /** The name the admin goes by until the site sets its own. */
    public const string DEFAULT_NAME = 'Lynx';

    /** Relative luminance above which dark text reads better than white (WCAG contrast). */
    private const float LIGHT_LUMINANCE = 0.179;

    public function __construct(
        public string $name,
        public ?string $logo,
        public ?string $background,
        public ?string $accent,
        public ?string $uploadedLogo = null,
    ) {}

    /** The branding in effect. */
    public static function current(): self
    {
        return self::from(self::saved(), Value::map(config('lynx-admin.branding', [])));
    }

    /**
     * What superusers saved; keys they never set are absent.
     *
     * @return array<string, string>
     */
    public static function saved(): array
    {
        $saved = json_decode(Setting::read(self::SETTING) ?? '{}', true);

        return array_filter(Value::map($saved), is_string(...));
    }

    /**
     * @param  array<string, string>  $saved
     * @param  array<array-key, mixed>  $defaults
     */
    public static function from(array $saved, array $defaults): self
    {
        $pick = fn (string $key): ?string => self::filled($saved[$key] ?? null) ?? self::filled($defaults[$key] ?? null);
        $uploaded = self::filled($saved['logo'] ?? null);

        return new self(
            name: $pick('name') ?? self::DEFAULT_NAME,
            logo: $uploaded === null ? self::filled($defaults['logo'] ?? null) : null,
            background: self::hex($pick('background')),
            accent: self::hex($pick('accent')),
            uploadedLogo: $uploaded,
        );
    }

    /** The logo's address: the uploaded one, else the configured URL or public path. */
    public function logoUrl(): ?string
    {
        if ($this->uploadedLogo !== null) {
            return route('lynx.admin.assets.logo', ['file' => $this->uploadedLogo], false);
        }

        if ($this->logo === null) {
            return null;
        }

        return preg_match('#^(https?:)?//#', $this->logo) === 1 ? $this->logo : asset($this->logo);
    }

    /**
     * Whether the admin shows Lynx's own mark and favicon (ADR-0089): only until the site sets a
     * name or a logo of its own, so a client's admin never carries Lynx's brand.
     */
    public function usesLynxMark(): bool
    {
        return $this->name === self::DEFAULT_NAME && $this->logo === null && $this->uploadedLogo === null;
    }

    /** The mark shown without a logo, once the site has its own name: the name's first letter. */
    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1)) ?: 'L';
    }

    /**
     * BlatUI's primary tokens in the accent, for the <html> element's style attribute, which wins
     * over the theme's own values in light and dark. Empty without an accent.
     */
    public function accentStyle(): string
    {
        if ($this->accent === null) {
            return '';
        }

        $text = self::readableOn($this->accent);

        return "--primary: {$this->accent}; --primary-foreground: {$text}; --ring: {$this->accent}; "
            ."--sidebar-primary: {$this->accent}; --sidebar-primary-foreground: {$text}; --sidebar-ring: {$this->accent};";
    }

    /** The sign-in screens' backdrop and the text on it. Empty without a background. */
    public function backgroundStyle(): string
    {
        return $this->background === null ? '' : "background-color: {$this->background}; color: ".self::readableOn($this->background).';';
    }

    /** White or near-black, whichever reads better on the colour. */
    public static function readableOn(string $hex): string
    {
        $channels = array_map(function (string $pair): float {
            $value = hexdec($pair) / 255;

            return $value <= 0.03928 ? $value / 12.92 : (($value + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 1), 2));

        $luminance = 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];

        return $luminance > self::LIGHT_LUMINANCE ? '#111111' : '#ffffff';
    }

    private static function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /** Only a six-digit hex colour reaches a style attribute; anything else is ignored. */
    private static function hex(?string $value): ?string
    {
        return $value !== null && preg_match(self::HEX, $value) === 1 ? strtolower($value) : null;
    }
}
