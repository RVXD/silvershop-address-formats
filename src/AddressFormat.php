<?php

declare(strict_types=1);

namespace SilverShop\AddressFormats;

/**
 * The resolved address rules for one country (its config merged over the `default`). Immutable value object returned
 * by {@link AddressFormatRegistry::formatFor()}.
 */
class AddressFormat
{
    /**
     * @param string        $country       ISO 3166-1 alpha-2 (or '' for the default)
     * @param string[]      $required      field names that must be filled
     * @param string[]      $hidden        field names to drop from forms (e.g. State in NL)
     * @param array<string, string> $labels field name => label override
     * @param string        $postcodeRegex PCRE for the postcode, or '' to skip validation
     * @param string[]      $format        ordered line templates, e.g. '{PostalCode} {City}'
     */
    public function __construct(
        private readonly string $country,
        private readonly array $required,
        private readonly array $hidden,
        private readonly array $labels,
        private readonly string $postcodeRegex,
        private readonly array $format,
    ) {
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    /** @return string[] */
    public function getRequired(): array
    {
        return $this->required;
    }

    public function isRequired(string $field): bool
    {
        return in_array($field, $this->required, true);
    }

    /** @return string[] */
    public function getHidden(): array
    {
        return $this->hidden;
    }

    public function isHidden(string $field): bool
    {
        return in_array($field, $this->hidden, true);
    }

    public function labelFor(string $field, string $default): string
    {
        return $this->labels[$field] ?? $default;
    }

    public function getPostcodeRegex(): string
    {
        return $this->postcodeRegex;
    }

    /** @return string[] */
    public function getFormat(): array
    {
        return $this->format;
    }

    /**
     * Validate a postcode against this country's pattern. Empty regex or empty value = valid (presence is handled by
     * the required-fields rules, not here).
     */
    public function postcodeValid(string $postcode): bool
    {
        if ($this->postcodeRegex === '' || trim($postcode) === '') {
            return true;
        }

        return (bool) preg_match($this->postcodeRegex, trim($postcode));
    }
}
