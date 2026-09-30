<?php

declare(strict_types=1);

namespace SilverShop\AddressFormats\Extension;

use SilverShop\AddressFormats\AddressFormatRegistry;
use SilverStripe\Core\Extension;
use SilverStripe\Forms\FieldList;

/**
 * Applied to {@link \SilverShop\Model\Address}. Makes the customer address form country-aware: relabels and hides
 * fields, and drives the required fields from {@link AddressFormatRegistry} instead of the static, State-always-required
 * core default. This fixes checkout for countries that don't use a State/Province (NL, BE, DE, GB, …).
 *
 * @property \SilverShop\Model\Address $owner
 */
class CustomerAddressExtension extends Extension
{
    /**
     * Relabel and hide address fields on the front-end form per the address's country.
     */
    public function updateFormFields(FieldList $fields): void
    {
        $format = AddressFormatRegistry::singleton()->formatFor($this->getCountry());

        foreach (['Address', 'AddressLine2', 'City', 'State', 'PostalCode'] as $name) {
            $field = $fields->dataFieldByName($name);
            if (!$field) {
                continue;
            }
            if ($format->isHidden($name)) {
                $fields->removeByName($name);
                continue;
            }
            $field->setTitle($format->labelFor($name, (string) $field->Title()));
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
        $format = AddressFormatRegistry::singleton()->formatFor($this->getCountry());

        if (!$format->isRequired('State') || $format->isHidden('State')) {
            unset($fields['State']);
        }
        foreach ($format->getRequired() as $name) {
            $fields[$name] = $name;
        }
    }

    private function getCountry(): string
    {
        return (string) $this->getOwner()->Country;
    }
}
