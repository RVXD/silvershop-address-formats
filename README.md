# SilverShop Address Formats

Per-country address field rules for [SilverShop](https://github.com/silvershop/silvershop-core) — labels,
required/hidden fields, postcode patterns and a reusable address formatter — plus a fix for the hardcoded
**`State`-required checkout blocker**.

Out of the box silvershop's customer `Address` requires `State` for *every* country, so NL / BE / DE / GB
customers can't complete checkout. This module makes address rules **per-country and config-driven**, with a
safe `default` that keeps existing behaviour where no country rule applies.

## What it adds

- **`AddressFormatRegistry`** + **`AddressFormat`** — per-ISO-country rules merged over a `default` entry, so a
  country only declares what differs: field `labels` (Postcode / ZIP / PLZ, State → Province / County),
  `required`, `hidden` (e.g. no State for NL), `postcode_regex`, and a display `format`.
- **`CustomerAddressExtension`** on `SilverShop\Model\Address` — relabels/hides fields per country
  (`updateFormFields`) and sets the per-country required fields (`updateRequiredFields`), **fixing the
  State-required checkout blocker**.
- **`AddressFormatter`** — renders address-like data to a one-line or multi-line address for a country. Reused
  by [store-profile](https://github.com/RVXD/silvershop-store-profile), shipping labels and invoicing.
- Ships rules for **NL, BE, DE, FR, GB, US, NZ, AU** + a `default`; add or override any country in YAML.

## Installation

```bash
composer require silvershop/address-formats
```

Then run `dev/build`.

## Adding or overriding a country

Rules live under `AddressFormatRegistry.formats`, keyed by ISO-3166-1 alpha-2 code. Each entry is merged over
`default`, so you only set what changes:

```yaml
SilverShop\AddressFormats\AddressFormatRegistry:
  formats:
    IE:
      required: ['Address', 'City']
      hidden: ['State']
      labels:
        PostalCode: 'Eircode'
      postcode_regex: ''
```

## Status

v1 — per-country labels / required / hidden / formatter + the checkout blocker fix. **i18n:** the per-country
field labels are configuration *data* (e.g. `Postcode`, `Plaats`, `PLZ`), not translatable UI strings, so the
module ships no `lang/` by design. Planned (v2): optional `HouseNumber` split (NL/DE), more countries, an
optional geocoding bridge. See [`PLAN-store-profile-address.md`](https://github.com/RVXD/silvershop-store-profile)
in the consuming project for the full roadmap.

## Licence

BSD-3-Clause.
