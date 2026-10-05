<!-- roundly-hero:start -->
<p align="center">
  <a href="https://roundly-consulting.com/open-source/docs/qr-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=qr-for-laravel">
    <img src="https://raw.githubusercontent.com/roundly-consulting/qr-for-laravel/main/art/hero.png" alt="QR for Laravel — Roundly open source" width="100%">
  </a>
</p>
<!-- roundly-hero:end -->

<!-- roundly-badges:start -->
<p align="center">
  <a href="https://packagist.org/packages/roundly-consulting/qr-for-laravel"><img src="https://img.shields.io/packagist/v/roundly-consulting/qr-for-laravel?style=flat-square&label=release" alt="Latest release"></a>
  <a href="https://github.com/roundly-consulting/qr-for-laravel/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/qr-for-laravel/run-tests.yml?branch=main&style=flat-square&label=tests" alt="Tests"></a>
  <a href="https://github.com/roundly-consulting/qr-for-laravel/actions/workflows/fix-php-code-style-issues.yml"><img src="https://img.shields.io/github/actions/workflow/status/roundly-consulting/qr-for-laravel/fix-php-code-style-issues.yml?branch=main&style=flat-square&label=code%20style" alt="Code style"></a>
  <a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/donate-support%20our%20open%20source-F24E29?style=flat-square&logo=stripe&logoColor=white" alt="Donate"></a>
  <a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/patreon-become%20a%20patron-F96854?style=flat-square&logo=patreon&logoColor=white" alt="Patreon"></a>
  <a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=qr-for-laravel#crypto"><img src="https://img.shields.io/badge/crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=flat-square&logo=bitcoin&logoColor=white" alt="Crypto"></a>
</p>
<!-- roundly-badges:end -->

# QR for Laravel

Native QR codes for Laravel: an ISO/IEC 18004 encoder, one optimised SVG renderer, and typed
payloads for 2FA setup codes, links, contacts, Wi-Fi, SEPA credit transfers (EPC069-12) and
Slovak PAY by square payments. No third-party runtime libraries.

## Installation

Requires PHP 8.4 (`ext-mbstring`, `ext-bcmath`) and Laravel 12 or 13.

```bash
composer require roundly-consulting/qr-for-laravel
```

## Usage

Build a code fluently and render it as SVG — or return the builder from a route:

```php
use RoundlyConsulting\Qr\Facades\Qr;

$svg = Qr::url('https://example.com/menu/12')
    ->size(240)
    ->errorCorrection('Q')
    ->title('Scan to order')
    ->svg();                                       // Svg value object, Htmlable

$svg->toString();                                  // the <svg> markup
Qr::url('https://example.com')->toDataUri();       // data:image/svg+xml;charset=utf-8,…

Route::get('/tables/{table}/qr', fn (Table $table) => Qr::url(route('menu', $table))); // image/svg+xml
```

Payments and 2FA enrolment codes are typed payloads too:

```php
use RoundlyConsulting\Money\Money;
use RoundlyConsulting\Qr\Payloads\Payments\Epc\EpcPayment;

$payment = new EpcPayment(
    name: 'VetClinic s.r.o.',
    iban: 'SK96 1100 0000 0029 1859 9669',
    amount: Money::ofMinor(12550, 'EUR'),
    reference: 'RF18539007547034',
);

Qr::epc($payment)->svg();
Qr::otpauth($setup->provisioningUri)->svg();      // never cached, served with no-store
```

Or drop one into a view:

```blade
<x-qr-code :data="$payment" :size="200" title="Scan to pay" />
```

<!-- roundly-docs:start -->
## Documentation

The full documentation — configuration, every feature and its API, and testing — lives on our
website: **[roundly-consulting.com/open-source/docs/qr-for-laravel](https://roundly-consulting.com/open-source/docs/qr-for-laravel?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=qr-for-laravel)**

Release notes are in [CHANGELOG.md](CHANGELOG.md). To contribute, see the
[contributing guide](https://github.com/roundly-consulting/.github/blob/main/CONTRIBUTING.md).
<!-- roundly-docs:end -->

<!-- roundly-support:start -->
## Support our work

This package is free and open source, built and maintained by
[Roundly Consulting](https://roundly-consulting.com/open-source?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=qr-for-laravel).
If it saves you time, please consider supporting our open-source work — a one-time donation, a
monthly pledge on Patreon or a crypto donation helps fund maintenance, new features and new
packages.

<a href="https://donate.stripe.com/dRmeVe8FX5PF1Qd9pXcEw00"><img src="https://img.shields.io/badge/Donate-Support%20Roundly%20open%20source-F24E29?style=for-the-badge&logo=stripe&logoColor=white" alt="Donate to Roundly open source"></a>
<a href="https://www.patreon.com/cw/roundly"><img src="https://img.shields.io/badge/Patreon-Become%20a%20patron-F96854?style=for-the-badge&logo=patreon&logoColor=white" alt="Become a patron on Patreon"></a>
<a href="https://roundly-consulting.com/support-us?utm_source=github&utm_medium=readme&utm_campaign=open-source&utm_content=qr-for-laravel#crypto"><img src="https://img.shields.io/badge/Crypto-BTC%20%C2%B7%20ETH%20%C2%B7%20BNB%20%C2%B7%20SOL-F7931A?style=for-the-badge&logo=bitcoin&logoColor=white" alt="Donate crypto: BTC, ETH, BNB or SOL"></a>
<!-- roundly-support:end -->

## License

The MIT License (MIT). See [LICENSE.md](LICENSE.md).
