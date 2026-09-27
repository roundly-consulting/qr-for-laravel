# Changelog

All notable changes to `qr-for-laravel` are documented in this file. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the project uses
[Semantic Versioning](https://semver.org/).

## Unreleased

Initial public release.

### Added

- A native ISO/IEC 18004 QR Code Model 2 encoder: versions 1–40, error correction L/M/Q/H,
  optimal numeric / alphanumeric / byte / kanji segmentation, UTF-8 via ECI and ISO mask scoring.
- One optimised SVG renderer with square, rounded or dot modules, rounded finders, colours and an
  accessible `<title>` / `<desc>`.
- The `Qr` facade with an immutable, fluent builder (`size()`, `margin()`, …), a `QrOptions`
  one-call form, `Qr::matrix()` and `info()` for encoding details.
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
