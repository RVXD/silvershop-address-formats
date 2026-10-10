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
     * @param string        $postcodeTransform normalisation applied on save: '', 'upper', 'nl' or 'ca'
     * @param bool          $houseNumber   whether this country uses a separate house-number field
     */
    public function __construct(
        private readonly string $country,
        private readonly array $required,
        private readonly array $hidden,
        private readonly array $labels,
        private readonly string $postcodeRegex,
        private readonly array $format,
        private readonly string $postcodeTransform = '',
        private readonly bool $houseNumber = false,
    ) {
    }

    /**
     * Whether this country splits the street address into a separate house-number field.
     */
    public function hasHouseNumber(): bool
    {
        return $this->houseNumber;
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

    public function getPostcodeTransform(): string
    {
        return $this->postcodeTransform;
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

    /**
     * The postcode pattern as an HTML5 `pattern` attribute value — the regex body without PCRE delimiters, flags or
     * anchors (HTML patterns are implicitly anchored). Empty when the country has no regex.
     */
    public function htmlPostcodePattern(): string
    {
        $regex = $this->postcodeRegex;
        if ($regex === '') {
            return '';
        }

        // Strip the PCRE delimiter + trailing flags: /…/i, #…#, etc.
        if (preg_match('/^(.)(.*)\1([a-zA-Z]*)$/s', $regex, $m)) {
            $regex = $m[2];
        }
        // HTML patterns are implicitly anchored, so drop leading ^ / trailing $.
        $regex = preg_replace('/^\^/', '', (string) $regex);
        $regex = preg_replace('/\$$/', '', (string) $regex);

        return (string) $regex;
    }

    /**
     * Normalise a postcode to this country's canonical form (e.g. NL "1234ab" → "1234 AB"). Unknown/empty transform
     * returns the trimmed input unchanged. Idempotent.
     */
    public function normalisePostcode(string $postcode): string
    {
        $value = trim($postcode);
        if ($value === '') {
            return '';
        }

        switch ($this->postcodeTransform) {
            case 'upper':
                return strtoupper($value);
            case 'nl':
                $compact = strtoupper((string) preg_replace('/\s+/', '', $value));
                return preg_match('/^([0-9]{4})([A-Z]{2})$/', $compact, $m) ? $m[1] . ' ' . $m[2] : strtoupper($value);
            case 'ca':
                $compact = strtoupper((string) preg_replace('/\s+/', '', $value));
                return preg_match('/^([A-Z][0-9][A-Z])([0-9][A-Z][0-9])$/', $compact, $m)
                    ? $m[1] . ' ' . $m[2]
                    : strtoupper($value);
            default:
                return $value;
        }
    }
}
