<?php

declare(strict_types=1);

namespace SilverShop\AddressFormats\Extension;

use SilverShop\AddressFormats\AddressFormat;
use SilverShop\AddressFormats\AddressFormatRegistry;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\TextField;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Model\ArrayData;

/**
 * Applied to {@link \SilverShop\Model\Address}. Makes the customer address form country-aware: relabels and hides
 * fields, drives the required fields from {@link AddressFormatRegistry} (fixing the State-always-required core
 * default), enforces the per-country postcode pattern, and normalises the postcode on save. When
 * `live_country_switching` is on (default) the form keeps every field and ships a small client script that
 * re-labels / shows-hides / re-requires fields as the customer changes Country.
 *
 * @property \SilverShop\Model\Address $owner
 */
class CustomerAddressExtension extends Extension
{
    /** Address fields this extension manages on the front-end form (HouseNumber is created by this extension). */
    private const MANAGED_FIELDS = ['Address', 'HouseNumber', 'AddressLine2', 'City', 'State', 'PostalCode'];

    /**
     * Optional separate house-number field, used by countries configured with `house_number: true` (e.g. NL, DE).
     * Nullable + off by default, so existing installs are unaffected until a country opts in.
     */
    private static array $db = [
        'HouseNumber' => 'Varchar(20)',
    ];

    /**
     * Relabel / hide address fields per the address's country. With live switching on, hidden fields are kept in the
     * DOM (marked, not removed) and the client switcher script is injected so the form adapts without a round-trip.
     */
    public function updateFormFields(FieldList $fields): void
    {
        $format = $this->formatFor();
        $live = (bool) AddressFormatRegistry::config()->get('live_country_switching');

        // Core doesn't provide a HouseNumber field; add it next to Address when any country could use it.
        if (!$fields->dataFieldByName('HouseNumber')
            && $fields->dataFieldByName('Address')
            && ($live || $format->hasHouseNumber())
        ) {
            $fields->insertAfter('Address', TextField::create('HouseNumber', $format->labelFor('HouseNumber', 'No.')));
        }

        foreach (self::MANAGED_FIELDS as $name) {
            $field = $fields->dataFieldByName($name);
            if (!$field) {
                continue;
            }

            // HouseNumber is shown only where the country opts in (inverse of the explicit `hidden` list).
            $hidden = $name === 'HouseNumber' ? !$format->hasHouseNumber() : $format->isHidden($name);

            if (!$live && $hidden) {
                $fields->removeByName($name);
                continue;
            }

            $field->setTitle($format->labelFor($name, (string) $field->Title()));

            if (!$live) {
                continue;
            }

            $field->setAttribute('data-addressformat-field', $name);
            if ($hidden) {
                $field->addExtraClass('addressformat-hidden');
            }
            if ($name === 'PostalCode' && ($pattern = $format->htmlPostcodePattern()) !== '') {
                $field->setAttribute('pattern', $pattern);
            }
        }

        if ($live) {
            $fields->push($this->switcherField());
        }
    }

    /**
     * Replace the per-country address requirements: drop State where it isn't used, and ensure the country's required
     * address fields are present. Non-address requirements set elsewhere are left untouched.
     *
     * @param array<string, string> $fields combined (key == value) required-field map, by reference
     */
    public function updateRequiredFields(array &$fields): void
    {
        $format = $this->formatFor();

        if (!$format->isRequired('State') || $format->isHidden('State')) {
            unset($fields['State']);
        }
        foreach ($format->getRequired() as $name) {
            $fields[$name] = $name;
        }
        if (!$format->hasHouseNumber()) {
            unset($fields['HouseNumber']);
        }
    }

    /**
     * Compose the separate house number back into the address line for single-string rendering (toString/getTitle),
     * so stored/displayed addresses stay whole for countries that split it.
     *
     * @param array<int, string|int|null> $fields the flat address values core joins into a string, by reference
     */
    public function updateToString(array &$fields): void
    {
        $owner = $this->getOwner();
        $houseNumber = trim((string) $owner->HouseNumber);
        $street = (string) $owner->Address;
        if ($houseNumber === '' || $street === '' || !$this->formatFor()->hasHouseNumber()) {
            return;
        }

        foreach ($fields as $i => $value) {
            if ((string) $value === $street) {
                $fields[$i] = trim($street . ' ' . $houseNumber);
                break;
            }
        }
    }

    /**
     * Enforce the per-country postcode pattern on the model (mirrors the client-side `pattern`). Presence is handled
     * by the required-fields rules, so an empty postcode is left to those.
     */
    public function updateValidate(ValidationResult $result): void
    {
        $postcode = (string) $this->getOwner()->PostalCode;
        if (trim($postcode) === '' || $this->formatFor()->postcodeValid($postcode)) {
            return;
        }

        $result->addFieldError(
            'PostalCode',
            _t(self::class . '.InvalidPostcode', 'Please enter a valid postcode for the selected country.')
        );
    }

    /**
     * Normalise the postcode to the country's canonical form on save (e.g. NL "1234ab" → "1234 AB").
     */
    public function onBeforeWrite(): void
    {
        $owner = $this->getOwner();
        $postcode = (string) $owner->PostalCode;
        if (trim($postcode) === '') {
            return;
        }

        $normalised = $this->formatFor()->normalisePostcode($postcode);
        if ($normalised !== $postcode) {
            $owner->PostalCode = $normalised;
        }
    }

    private function formatFor(): AddressFormat
    {
        return AddressFormatRegistry::singleton()->formatFor((string) $this->getOwner()->Country);
    }

    /**
     * The injected rules JSON + client switcher (style + script). Overridable by a project/theme template at
     * `SilverShop/AddressFormats/Includes/AddressFormatScript.ss`.
     */
    private function switcherField(): LiteralField
    {
        $json = json_encode(
            AddressFormatRegistry::singleton()->rulesForJs(),
            JSON_HEX_TAG | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );

        $html = (string) ArrayData::create(['RulesJson' => $json])
            ->renderWith('SilverShop/AddressFormats/Includes/AddressFormatScript');

        return LiteralField::create('AddressFormatScript', $html);
    }
}
