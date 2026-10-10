<?php

declare(strict_types=1);

namespace SilverShop\AddressFormats\Provider;

use CommerceGuys\Addressing\AddressFormat\AddressField;
use CommerceGuys\Addressing\AddressFormat\AddressFormatRepository;
use SilverStripe\Core\Injector\Injectable;

/**
 * Builds address rules from the Google **libaddressinput** dataset (via commerceguys/addressing) for global country
 * coverage. Returns the same array shape as a YAML `formats` entry — {required, hidden, labels, postcode_regex,
 * format} — so {@link \SilverShop\AddressFormats\AddressFormatRegistry} can merge project YAML overrides on top.
 * A no-op (returns null) when the library is not installed; enabled with `AddressFormatRegistry.rule_provider:
 * addressing`.
 */
class AddressingRuleProvider
{
    use Injectable;

    /** commerceguys AddressField → this module's address field name. Other fields (name, org, sorting) are ignored. */
    private const FIELD_MAP = [
        AddressField::ADDRESS_LINE1 => 'Address',
        AddressField::ADDRESS_LINE2 => 'AddressLine2',
        AddressField::LOCALITY => 'City',
        AddressField::ADMINISTRATIVE_AREA => 'State',
        AddressField::POSTAL_CODE => 'PostalCode',
    ];

    /** libaddressinput field/area type → a human label. Unknown types are title-cased. */
    private const TYPE_LABELS = [
        'area' => 'Area',
        'county' => 'County',
        'department' => 'Department',
        'district' => 'District',
        'do_si' => 'Do / Si',
        'emirate' => 'Emirate',
        'island' => 'Island',
        'oblast' => 'Oblast',
        'parish' => 'Parish',
        'prefecture' => 'Prefecture',
        'province' => 'Province',
        'state' => 'State',
        'zip' => 'ZIP code',
        'postal' => 'Postcode',
        'eircode' => 'Eircode',
        'pin' => 'PIN code',
        'post_town' => 'Post town',
        'city' => 'City',
        'suburb' => 'Suburb',
        'townland' => 'Townland',
        'village_township' => 'Village / Township',
    ];

    public static function available(): bool
    {
        return class_exists(AddressFormatRepository::class);
    }

    /**
     * Rules for a country from the libaddressinput dataset, or null if unavailable / unknown.
     *
     * @return array<string, mixed>|null
     */
    public function rulesFor(string $country): ?array
    {
        $code = strtoupper(trim($country));
        if (!self::available() || $code === '') {
            return null;
        }

        try {
            $format = (new AddressFormatRepository())->get($code);
        } catch (\Throwable $e) {
            return null;
        }

        $managed = ['Address', 'AddressLine2', 'City', 'State', 'PostalCode'];
        $used = $this->mapFields($format->getUsedFields());

        $labels = [];
        if ($type = $format->getAdministrativeAreaType()) {
            $labels['State'] = $this->label($type);
        }
        if ($type = $format->getPostalCodeType()) {
            $labels['PostalCode'] = $this->label($type);
        }
        if ($type = $format->getLocalityType()) {
            $labels['City'] = $this->label($type);
        }

        $pattern = (string) $format->getPostalCodePattern();

        return [
            'required' => $this->mapFields($format->getRequiredFields()),
            'hidden' => array_values(array_diff($managed, $used)),
            'labels' => $labels,
            'postcode_regex' => $pattern !== '' ? '/^(' . $pattern . ')$/' : '',
            'format' => $this->convertFormat($format->getFormat()),
        ];
    }

    /**
     * @param string[] $fields
     * @return string[]
     */
    private function mapFields(array $fields): array
    {
        $mapped = [];
        foreach ($fields as $field) {
            if (isset(self::FIELD_MAP[$field])) {
                $mapped[] = self::FIELD_MAP[$field];
            }
        }

        return array_values(array_unique($mapped));
    }

    private function label(string $type): string
    {
        return self::TYPE_LABELS[$type] ?? ucwords(str_replace('_', ' ', $type));
    }

    /**
     * Convert a libaddressinput format string (%-tokens, newline-separated) to this module's `{Token}` line
     * templates, dropping the tokens it doesn't manage (name, organisation, sorting code, dependent locality).
     *
     * @return string[]
     */
    private function convertFormat(string $format): array
    {
        $tokenMap = [
            '%addressLine1' => '{Address}',
            '%addressLine2' => '{AddressLine2}',
            '%addressLine3' => '',
            '%locality' => '{City}',
            '%administrativeArea' => '{State}',
            '%postalCode' => '{PostalCode}',
            '%dependentLocality' => '',
            '%sortingCode' => '',
            '%organization' => '',
            '%givenName' => '',
            '%additionalName' => '',
            '%familyName' => '',
        ];

        $lines = [];
        foreach (explode("\n", $format) as $line) {
            $line = strtr($line, $tokenMap);
            $line = trim((string) preg_replace('/\s{2,}/', ' ', $line));
            $line = trim($line, ' ,');
            if ($line !== '') {
                $lines[] = $line;
            }
        }
        $lines[] = '{Country}';

        return $lines;
    }
}
