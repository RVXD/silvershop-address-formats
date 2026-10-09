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
- **Live country switching** (default on) — the front-end form re-labels, shows/hides and re-requires fields *as the
  customer changes Country*, with a no-JS baseline. Overridable template + JS (see below).
- **Postcode validation + normalisation** — the per-country `postcode_regex` is enforced on the model *and* exposed as
  an HTML5 `pattern`, and postcodes are normalised on save (e.g. NL `1234ab` → `1234 AB`, GB/CA upper-cased).
- Ships rules for **~23 countries** (NL, BE, DE, FR, GB, US, NZ, AU, AT, CH, CZ, DK, ES, FI, IE, IT, LU, NO, PL, PT,
  SE, CA, JP) + a `default`; add or override any country in YAML.

## Live country switching

With `live_country_switching` on (default), the address form keeps every field in the DOM and ships a small script
that adapts it client-side on Country change: it toggles a `addressformat-hidden` class on the holder, swaps labels,
updates `required`/`aria-required`, and sets the postcode `pattern`. It reads a per-country rules JSON and dispatches
an `addressformat:changed` event. Everything is overridable:

- Override the template `SilverShop/AddressFormats/Includes/AddressFormatScript.ss` in your theme/project.
- Fields are tagged `data-addressformat-field="Address|AddressLine2|City|State|PostalCode"` — style/script against that.
- Turn it off (revert to server-side field removal, no client switching):

```yaml
SilverShop\AddressFormats\AddressFormatRegistry:
  live_country_switching: false
```

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

## Postcode rules

Each country may set a `postcode_regex` (enforced on the model + surfaced as an HTML5 `pattern`) and an optional
`postcode_transform` applied on save — `upper`, `nl` (`1234ab` → `1234 AB`) or `ca` (`m5h2n2` → `M5H 2N2`). An empty
regex skips validation; presence is governed by `required`.

## Status

v1.1 — per-country labels / required / hidden / formatter + the checkout blocker fix, **live country switching**, and
**postcode validation + normalisation** across ~23 countries. **i18n:** the per-country field *labels* are
configuration data (not translatable); the only UI string is the postcode validation message, shipped in en + nl +
de + fr + es + it. Planned: `HouseNumber` split (NL/DE) and a pluggable libaddressinput provider for full global
coverage — see [`PLAN-address-formats-v1.1.md`](https://github.com/RVXD/silvershop-store-profile) in the consuming
project for the roadmap.

## Licence

BSD-3-Clause.
