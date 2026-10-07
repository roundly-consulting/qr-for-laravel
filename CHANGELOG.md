# Changelog

All notable changes to `qr-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

### Changed

- With `ModuleStyle::Rounded`, `FinderStyle::Square` finders are now always drawn as square rings and centres. Before, they were traced with the data modules and came out rounded, unless a different finder colour was set, which made them square: the colour changed the shape. **Visual change:** hosts that render `ModuleStyle::Rounded` with the default square finders now get square finders; choose `FinderStyle::Rounded` for rounded finders. The shipped configuration (square modules) renders exactly as before.

### Fixed

- `EpcPayment` now rejects a `purpose` code with a trailing newline (`"GDDS\n"`); before, the newline shifted the creditor reference onto the unstructured-text line of the payment code.
- `Iban` and `CreditorReference` now reject check digits 00, 01 and 99. They pass the MOD 97-10 test, but the standard's generation rule only ever produces 02–98, so no real IBAN or RF reference carries them. The `Iban` and `CreditorReference` validation rules, the `AsIban` cast and the payment payloads inherit the check.
- `PayBySquare::encode()` with deburring on now throws `InvalidPayloadException` (`beneficiary.name`, unrepresentable) when the beneficiary name has no ASCII form (for example `李明`), instead of writing an empty or blank name the package's own decoder and 1.2.0 readers reject. Deburred names, streets, cities and notes are trimmed, and `PayBySquare::decode()` reads a blank name written by earlier versions as no beneficiary.
- `PendingQr::withOptions()` with only `QrOptions::$minVersion` or only `$maxVersion`, and `qr:make` with only `--min-version` or `--max-version`, now keep the configured other bound (`qr.versions.*`) instead of widening it to 1 or 40. A single bound outside the configured window narrows it rather than throwing.
- `Url` now rejects a backslash in the authority of an `http`/`https` (and other WHATWG special-scheme) URL. Browsers end the host at `\`, so `https://evil.example\@bank.example/` opened `evil.example` while `Url::$host` and the SVG description named `bank.example`.
- `Email` now percent-encodes `,` in the `mailto:` address, so a valid quoted address such as `"x,attacker@evil.com,"@example.com` cannot read as an extra recipient in mail clients that split on commas.
- Raw text that starts with a byte order mark or a Unicode space (no-break space, em space, …) before `WIFI:`, `otpauth:` or `otpauth-migration:` is now recognised as Secret, like plain whitespace was. Before, such a Wi-Fi password or 2FA seed was treated as public: memoised, cached and served with public cache headers.
- `Otpauth::fromUri()` now rejects a `digits`, `period` or `counter` value with a trailing newline (`digits=6%0A`) instead of passing it through to the enrolment code.
- `Svg::withAttributes()` and `Svg::toImgTag()` now reject an attribute name with a trailing newline (`"aria-label\n"`), and `<x-qr-code>` drops one from its attribute bag. Before, the name was written verbatim, duplicating the attribute and breaking the XML.

## 1.0.2 - 2026-10-04

### Fixed

- The `qr::qr.errors.locked_by_payload` message no longer blames "the payment standard" when the locked option comes from a non-payment payload (for example `sensitivity` on an otpauth 2FA seed); it now reads "The :field option is fixed by this content type." in English and Slovak.
- The English `qr::qr.errors.color` message now spells "color" like the rest of the package's API ("The :field color is not allowed.").

## 1.0.1 - 2026-10-04

### Changed

- Maintenance: `composer.json` `homepage` and `support.docs` now point to the documentation site.

### Fixed

- Slovak (`sk`) translations now ship alongside English for every language file.

## 1.0.0 - 2026-10-03

Initial public release.

### Added

- A native ISO/IEC 18004 QR Code Model 2 encoder: versions 1–40, error correction L/M/Q/H,
  optimal numeric / alphanumeric / byte / kanji segmentation, UTF-8 via ECI and ISO mask scoring.
- One optimised SVG renderer with square, rounded or dot modules, rounded finders, colours and an
  accessible `<title>` / `<desc>`.
- The `Qr` facade over the injectable `QrFactory` contract, with an immutable, fluent builder
  (`size()`, `margin()`, …), a `QrOptions` one-call form, `Qr::matrix()` and one-call
  `Qr::info()` for encoding details.
- `Qr::fits($data, ?$level, ?$maxVersion, ?$segmentation)` and `PendingQr::fits()`: whether data
  would encode — under the payload's requirements and the configuration — without building the
  symbol. The `FitsInQrCode` rule is its validation face.
- Typed payloads: text, URL, e-mail, phone, SMS, Wi-Fi (WPA / WEP / WPA3 SAE), vCard, geo and
  `otpauth` 2FA enrolment codes.
- SEPA credit transfer codes (EPC069-12) via `Qr::epc()` and Slovak PAY by square payments,
  standing orders and direct debits via `Qr::payBySquare()`, with amounts as money-for-laravel
  `Money`.
- Banking primitives `Iban`, `Bic` and `CreditorReference`, the `Iban`, `Bic`,
  `CreditorReference` and `FitsInQrCode` validation rules, and an `AsIban` Eloquent cast.
- The `<x-qr-code>` Blade component, data URIs, `<img>` tags and SVG HTTP responses with
  download support.
- Sensitivity-aware caching and headers: public, personal and secret payloads — 2FA seeds and
  Wi-Fi passwords are never cached or served cacheably.
- The `qr:make` command to render a code (or print its encoding details) from the CLI.
