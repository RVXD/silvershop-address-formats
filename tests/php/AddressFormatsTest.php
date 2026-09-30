<?php

declare(strict_types=1);

namespace SilverShop\AddressFormats\Tests;

use SilverShop\AddressFormats\AddressFormatRegistry;
use SilverShop\AddressFormats\AddressFormatter;
use SilverShop\Model\Address;
use SilverStripe\Dev\SapphireTest;

class AddressFormatsTest extends SapphireTest
{
    protected $usesDatabase = true;

    public function testRegistryMergesCountryOverDefault(): void
    {
        $registry = AddressFormatRegistry::singleton();

        $nl = $registry->formatFor('nl'); // lower-case tolerated
        $this->assertTrue($nl->isHidden('State'));
        $this->assertFalse($nl->isRequired('State'));
        $this->assertSame('Postcode', $nl->labelFor('PostalCode', 'Postal Code'));
        $this->assertTrue($nl->postcodeValid('1011 AB'));
        $this->assertFalse($nl->postcodeValid('ABCDE'));

        $us = $registry->formatFor('US');
        $this->assertFalse($us->isHidden('State'));
        $this->assertTrue($us->isRequired('State'));

        $nz = $registry->formatFor('NZ');
        $this->assertTrue($nz->isHidden('State'));
        $this->assertTrue($nz->postcodeValid('1010'));
        $this->assertFalse($nz->postcodeValid('10101'));

        $unknown = $registry->formatFor('ZZ');
        $this->assertSame(['Address', 'City', 'PostalCode'], $unknown->getRequired());
    }

    public function testAddressRequiredFieldsAreCountryAware(): void
    {
        $nl = Address::create(['Country' => 'NL']);
        $required = $nl->getRequiredFields();
        $this->assertArrayNotHasKey('State', $required, 'State must not be required for NL');
        $this->assertArrayHasKey('PostalCode', $required);
        $this->assertArrayHasKey('City', $required);

        $us = Address::create(['Country' => 'US']);
        $this->assertArrayHasKey('State', $us->getRequiredFields(), 'State stays required for US');
    }

    public function testAddressFrontEndFieldsRelabelAndHide(): void
    {
        $nl = Address::create(['Country' => 'NL']);
        $fields = $nl->getFrontEndFields();

        $this->assertNull($fields->dataFieldByName('State'), 'State field hidden for NL');
        $this->assertSame('Postcode', $fields->dataFieldByName('PostalCode')->Title());
    }

    public function testFormatterCountryOrdering(): void
    {
        $formatter = AddressFormatter::singleton();

        $data = [
            'Address' => 'Voorbeeldstraat 1',
            'PostalCode' => '1011 AB',
            'City' => 'Amsterdam',
            'Country' => 'NL',
        ];
        $this->assertSame(
            "Voorbeeldstraat 1\n1011 AB Amsterdam\nNL",
            $formatter->format($data, 'NL')
        );

        $us = [
            'Address' => '1 Market St',
            'City' => 'San Francisco',
            'State' => 'CA',
            'PostalCode' => '94105',
            'Country' => 'US',
        ];
        $this->assertSame(
            "1 Market St\nSan Francisco, CA 94105\nUS",
            $formatter->format($us, 'US')
        );
    }
}
