<?php

declare(strict_types=1);

namespace SilverShop\AddressFormats;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;

/**
 * Resolves per-country address rules from the `formats` config. Every country's entry is merged over the `default`
 * entry, so a country only needs to declare what differs. Configure additional countries in YAML under
 * {@link self}::formats.
 */
class AddressFormatRegistry
{
    use Configurable;
    use Injectable;

    /**
     * Per-country rules keyed by ISO 3166-1 alpha-2, plus a `default` fallback. Each entry may set: required (string[]),
     * hidden (string[]), labels (map), postcode_regex (string), format (string[] line templates).
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $formats = [];

    public function formatFor(?string $country): AddressFormat
    {
        $formats = (array) static::config()->get('formats');
        $default = (array) ($formats['default'] ?? []);

        $code = strtoupper(trim((string) $country));
        $specific = ($code !== '' && isset($formats[$code])) ? (array) $formats[$code] : [];

        $merged = array_merge($default, $specific);

        return new AddressFormat(
            $code,
            array_values((array) ($merged['required'] ?? [])),
            array_values((array) ($merged['hidden'] ?? [])),
            (array) ($merged['labels'] ?? []),
            (string) ($merged['postcode_regex'] ?? ''),
            array_values((array) ($merged['format'] ?? [])),
        );
    }

    /**
     * ISO codes with an explicit entry (excludes `default`).
     *
     * @return string[]
     */
    public function configuredCountries(): array
    {
        $formats = (array) static::config()->get('formats');
        unset($formats['default']);

        return array_keys($formats);
    }
}
