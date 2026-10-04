# Changelog

All notable changes to `qr-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

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
