<?php

declare(strict_types=1);

namespace SilverShop\AddressFormats\Tests;

use SilverShop\AddressFormats\AddressFormatRegistry;
use SilverShop\AddressFormats\AddressFormatter;
use SilverShop\AddressFormats\Provider\AddressingRuleProvider;
use SilverShop\Model\Address;
use SilverStripe\Core\Config\Config;
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

    public function testExpandedCountryCoverage(): void
    {
        $registry = AddressFormatRegistry::singleton();

        $es = $registry->formatFor('ES');
        $this->assertSame('Código postal', $es->labelFor('PostalCode', 'x'));
        $this->assertTrue($es->isHidden('State'));
        $this->assertTrue($es->postcodeValid('28013'));

        $pl = $registry->formatFor('PL');
        $this->assertTrue($pl->postcodeValid('00-950'));
        $this->assertFalse($pl->postcodeValid('00950'));

        $ca = $registry->formatFor('CA');
        $this->assertTrue($ca->isRequired('State'));
        $this->assertSame('Province', $ca->labelFor('State', 'x'));
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

    public function testFrontEndFieldsKeepAndMarkHiddenWithLiveSwitching(): void
    {
        // Default: live_country_switching on → hidden fields stay in the DOM, marked, and the switcher is injected.
        $nl = Address::create(['Country' => 'NL']);
        $fields = $nl->getFrontEndFields();

        $state = $fields->dataFieldByName('State');
        $this->assertNotNull($state, 'State kept in the DOM with live switching on');
        $this->assertStringContainsString('addressformat-hidden', (string) $state->extraClass());
        $this->assertSame('State', $state->getAttribute('data-addressformat-field'));

        $postal = $fields->dataFieldByName('PostalCode');
        $this->assertSame('Postcode', $postal->Title());
        $this->assertNotSame('', (string) $postal->getAttribute('pattern'), 'postcode pattern set for NL');

        $this->assertNotNull($fields->fieldByName('AddressFormatScript'), 'switcher script injected');
    }

    public function testFrontEndFieldsRemoveHiddenWhenLiveSwitchingOff(): void
    {
        Config::modify()->set(AddressFormatRegistry::class, 'live_country_switching', false);

        $nl = Address::create(['Country' => 'NL']);
        $fields = $nl->getFrontEndFields();

        $this->assertNull($fields->dataFieldByName('State'), 'State removed when live switching is off');
        $this->assertNull($fields->fieldByName('AddressFormatScript'), 'no switcher when off');
        $this->assertSame('Postcode', $fields->dataFieldByName('PostalCode')->Title());
    }

    public function testRulesForJs(): void
    {
        $rules = AddressFormatRegistry::singleton()->rulesForJs();

        $this->assertArrayHasKey('default', $rules);
        $this->assertArrayHasKey('NL', $rules);
        $this->assertContains('State', $rules['NL']['hidden']);
        $this->assertNotSame('', $rules['NL']['postcodePattern']);
        $this->assertSame('Postcode', $rules['NL']['labels']['PostalCode']);
        $this->assertContains('State', $rules['US']['required']);
    }

    public function testHtmlPostcodePatternStripsDelimitersAndAnchors(): void
    {
        $pattern = AddressFormatRegistry::singleton()->formatFor('NL')->htmlPostcodePattern();
        $this->assertSame('[0-9]{4}\s?[A-Za-z]{2}', $pattern);
    }

    public function testPostcodeValidationOnModel(): void
    {
        $base = ['Country' => 'NL', 'Address' => 'Straat', 'HouseNumber' => '1', 'City' => 'Amsterdam'];

        $bad = Address::create($base + ['PostalCode' => 'ABCDE']);
        $this->assertFalse($bad->validate()->isValid(), 'invalid NL postcode rejected');

        $good = Address::create($base + ['PostalCode' => '1011 AB']);
        $this->assertTrue($good->validate()->isValid(), 'valid NL postcode accepted');

        // Country without a regex (unknown) never fails on the pattern.
        $zz = Address::create(['Country' => 'ZZ', 'Address' => 'x', 'City' => 'y', 'PostalCode' => 'whatever']);
        $this->assertTrue($zz->validate()->isValid());
    }

    public function testPostcodeNormalisedOnWrite(): void
    {
        $nl = Address::create(['Country' => 'NL', 'Address' => 'Straat', 'HouseNumber' => '1', 'City' => 'Amsterdam', 'PostalCode' => '1011ab']);
        $nl->write();
        $this->assertSame('1011 AB', $nl->PostalCode, 'NL postcode normalised to 4 digits + space + upper letters');

        $ca = Address::create(['Country' => 'CA', 'Address' => '1 St', 'City' => 'Toronto', 'State' => 'ON', 'PostalCode' => 'm5h2n2']);
        $ca->write();
        $this->assertSame('M5H 2N2', $ca->PostalCode);

        $gb = Address::create(['Country' => 'GB', 'Address' => '1 St', 'City' => 'London', 'PostalCode' => 'sw1a 1aa']);
        $gb->write();
        $this->assertSame('SW1A 1AA', $gb->PostalCode);
    }

    public function testHouseNumberFieldForOptedInCountry(): void
    {
        $nl = Address::create(['Country' => 'NL']);
        $fields = $nl->getFrontEndFields();

        $hn = $fields->dataFieldByName('HouseNumber');
        $this->assertNotNull($hn, 'HouseNumber field added for NL');
        $this->assertSame('Huisnr.', $hn->Title());
        $this->assertStringNotContainsString('addressformat-hidden', (string) $hn->extraClass(), 'shown for NL');
        $this->assertSame('Straat', $fields->dataFieldByName('Address')->Title());
        $this->assertArrayHasKey('HouseNumber', $nl->getRequiredFields());

        $us = Address::create(['Country' => 'US']);
        $usFields = $us->getFrontEndFields();
        $usHn = $usFields->dataFieldByName('HouseNumber');
        $this->assertNotNull($usHn, 'HouseNumber kept in the DOM (live switching) for US');
        $this->assertStringContainsString('addressformat-hidden', (string) $usHn->extraClass(), 'hidden for US');
        $this->assertArrayNotHasKey('HouseNumber', $us->getRequiredFields());
    }

    public function testHouseNumberComposedIntoAddressString(): void
    {
        $nl = Address::create([
            'Country' => 'NL',
            'Address' => 'Voorbeeldstraat',
            'HouseNumber' => '1',
            'City' => 'Amsterdam',
            'PostalCode' => '1011 AB',
        ]);
        $this->assertStringContainsString('Voorbeeldstraat 1', $nl->toString());

        $line = AddressFormatter::singleton()->format(
            ['Address' => 'Voorbeeldstraat', 'HouseNumber' => '1', 'PostalCode' => '1011 AB', 'City' => 'Amsterdam', 'Country' => 'NL'],
            'NL'
        );
        $this->assertStringContainsString('Voorbeeldstraat 1', $line);
    }

    public function testRulesForJsHidesHouseNumberWhereUnused(): void
    {
        $rules = AddressFormatRegistry::singleton()->rulesForJs();
        $this->assertContains('HouseNumber', $rules['US']['hidden']);
        $this->assertNotContains('HouseNumber', $rules['NL']['hidden']);
    }

    public function testAddressingRuleProviderGivesGlobalCoverage(): void
    {
        if (!AddressingRuleProvider::available()) {
            $this->markTestSkipped('commerceguys/addressing is not installed');
        }

        $registry = AddressFormatRegistry::singleton();

        // Config mode (default): a country absent from YAML has no postcode rule.
        $this->assertSame('', $registry->formatFor('BR')->getPostcodeRegex());

        // Addressing mode: libaddressinput fills it in globally.
        Config::modify()->set(AddressFormatRegistry::class, 'rule_provider', 'addressing');
        $br = $registry->formatFor('BR');
        $this->assertNotSame('', $br->getPostcodeRegex(), 'BR postcode pattern from libaddressinput');
        $this->assertTrue($br->postcodeValid('01310-100'));
        $this->assertFalse($br->isHidden('State'), 'BR uses an administrative area');

        // YAML per-country overrides still win over the dataset.
        $this->assertTrue($registry->formatFor('NL')->hasHouseNumber(), 'NL YAML override applied over addressing');
    }

    public function testAddressingRuleProviderShape(): void
    {
        if (!AddressingRuleProvider::available()) {
            $this->markTestSkipped('commerceguys/addressing is not installed');
        }

        $rules = AddressingRuleProvider::singleton()->rulesFor('US');
        $this->assertNotNull($rules);
        $this->assertContains('State', $rules['required']);
        $this->assertSame('ZIP code', $rules['labels']['PostalCode']);
        $this->assertContains('{City}, {State} {PostalCode}', $rules['format']);
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
