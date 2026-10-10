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
     * hidden (string[]), labels (map), postcode_regex (string), postcode_transform (string), format (string[] lines).
     *
     * @var array<string, array<string, mixed>>
     */
    private static array $formats = [];

    /**
     * When true, the front-end address form keeps every field in the DOM (hidden ones marked, not removed) and ships
     * the client script that re-labels / shows-hides / re-requires fields live as the customer changes Country. When
     * false, hidden fields are removed server-side (the pre-1.1 behaviour) and there is no client switching.
     */
    private static bool $live_country_switching = true;

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
            (string) ($merged['postcode_transform'] ?? ''),
            (bool) ($merged['house_number'] ?? false),
        );
    }

    /**
     * Per-country rules for the client switcher, keyed by uppercase ISO code plus `default`. Each entry is
     * `{labels, hidden, required, postcodePattern}` — everything the JS needs to re-render the address form without a
     * round-trip. Field labels are the address fields the form manages (Address/AddressLine2/City/State/PostalCode).
     *
     * @return array<string, array<string, mixed>>
     */
    public function rulesForJs(): array
    {
        $fields = ['Address', 'HouseNumber', 'AddressLine2', 'City', 'State', 'PostalCode'];
        $codes = array_merge(['default'], $this->configuredCountries());

        $rules = [];
        foreach ($codes as $code) {
            $format = $this->formatFor($code === 'default' ? '' : $code);
            $labels = [];
            foreach ($fields as $field) {
                $label = $format->labelFor($field, '');
                if ($label !== '') {
                    $labels[$field] = $label;
                }
            }
            // HouseNumber is hidden unless the country uses it (inverse of the explicit `hidden` list).
            $hidden = array_values($format->getHidden());
            if (!$format->hasHouseNumber()) {
                $hidden[] = 'HouseNumber';
            }
            $rules[$code] = [
                'labels' => $labels,
                'hidden' => $hidden,
                'required' => array_values($format->getRequired()),
                'postcodePattern' => $format->htmlPostcodePattern(),
            ];
        }

        return $rules;
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
