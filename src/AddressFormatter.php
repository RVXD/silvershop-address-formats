<?php

declare(strict_types=1);

namespace SilverShop\AddressFormats;

use SilverStripe\Core\Convert;
use SilverStripe\Core\Injector\Injectable;

/**
 * Renders address-like data to a country-appropriate multi-line (or one-line) string, using the line templates from
 * {@link AddressFormatRegistry}. Data is a plain map of field => value (e.g. Address, AddressLine2, PostalCode, City,
 * State, Country, Company, Name); missing tokens collapse away so a template line never shows stray punctuation.
 */
class AddressFormatter
{
    use Injectable;

    /**
     * @param array<string, string|int|null> $data
     */
    public function lines(array $data, ?string $country): array
    {
        $format = AddressFormatRegistry::singleton()->formatFor($country);
        $templates = $format->getFormat();
        if ($templates === []) {
            $templates = ['{Address}', '{AddressLine2}', '{PostalCode} {City}', '{State}', '{Country}'];
        }

        $lines = [];
        foreach ($templates as $template) {
            $line = preg_replace_callback('/\{(\w+)\}/', function ($m) use ($data) {
                return isset($data[$m[1]]) ? trim((string) $data[$m[1]]) : '';
            }, $template);

            // Collapse the gaps left by empty tokens; drop the line if nothing is left.
            $line = trim(preg_replace('/\s{2,}/', ' ', (string) $line));
            $line = trim($line, " ,");
            if ($line !== '') {
                $lines[] = $line;
            }
        }

        return $lines;
    }

    /**
     * @param array<string, string|int|null> $data
     */
    public function format(array $data, ?string $country, bool $html = false): string
    {
        $lines = $this->lines($data, $country);
        if ($html) {
            return implode('<br>', array_map(fn ($l) => Convert::raw2xml($l), $lines));
        }

        return implode("\n", $lines);
    }
}
