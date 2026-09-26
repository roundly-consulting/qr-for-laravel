# Changelog

All notable changes to `qr-for-laravel` will be documented in this file.

## 1.0.0 — unreleased

- QR Code Model 2 encoder (ISO/IEC 18004:2015): versions 1–40, levels L/M/Q/H with optional
  boost, numeric/alphanumeric/byte/kanji segments with optimal segmentation, ECI 26, automatic or
  forced masks.
- SVG renderer: single even-odd path, square/rounded/dot modules, rounded finders, finder colour,
  accessible title/description, data URIs, `<img>` tags, HTTP responses with sensitivity-aware
  caching headers, downloads.
- Fluent `PendingQr` builder, `Qr` facade, `QrOptions`, `<x-qr-code>` Blade component,
  `qr:make` command, `FitsInQrCode` rule.
- Payloads: text, URL, e-mail, phone, SMS, Wi-Fi (WPA/WEP/SAE), vCard 3.0, geo, otpauth
  (TOTP/HOTP), EPC069-12 v3.1 SEPA credit transfer, PAY by square 1.0.0/1.1.0/1.2.0 (encode and
  decode).
- Banking primitives: IBAN, BIC, ISO 11649 creditor reference, validation rules, `AsIban` cast.
- In-process matrix memo and optional rendered-SVG cache for public payloads.
- `Testing\MatrixDecoder` for host test suites.
