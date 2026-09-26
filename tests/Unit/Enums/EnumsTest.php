<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Enums\BySquare\BySquareVersion;
use RoundlyConsulting\Qr\Enums\BySquare\DirectDebitScheme;
use RoundlyConsulting\Qr\Enums\BySquare\DirectDebitType;
use RoundlyConsulting\Qr\Enums\BySquare\Month;
use RoundlyConsulting\Qr\Enums\BySquare\PaymentType;
use RoundlyConsulting\Qr\Enums\BySquare\Periodicity;
use RoundlyConsulting\Qr\Enums\DataUriEncoding;
use RoundlyConsulting\Qr\Enums\EciMode;
use RoundlyConsulting\Qr\Enums\EpcCharset;
use RoundlyConsulting\Qr\Enums\EpcVersion;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Enums\FinderStyle;
use RoundlyConsulting\Qr\Enums\Mode;
use RoundlyConsulting\Qr\Enums\ModuleStyle;
use RoundlyConsulting\Qr\Enums\OtpType;
use RoundlyConsulting\Qr\Enums\Segmentation;
use RoundlyConsulting\Qr\Enums\Sensitivity;
use RoundlyConsulting\Qr\Enums\SmsFormat;
use RoundlyConsulting\Qr\Enums\WifiSecurity;
use RoundlyConsulting\Qr\Exceptions\InvalidQrConfigException;

it('pins the backing values of every enum', function (string $enum, array $values): void {
    expect($enum::values()->all())->toBe($values);
})->with([
    [ErrorCorrection::class, ['L', 'M', 'Q', 'H']],
    [Mode::class, [1, 2, 4, 8, 7]],
    [Segmentation::class, ['optimal', 'single', 'byte']],
    [EciMode::class, ['auto', 'always', 'never']],
    [ModuleStyle::class, ['square', 'rounded', 'dots']],
    [FinderStyle::class, ['square', 'rounded']],
    [Sensitivity::class, ['public', 'personal', 'secret']],
    [DataUriEncoding::class, ['percent', 'base64']],
    [WifiSecurity::class, ['WPA', 'WEP', 'SAE', 'nopass']],
    [SmsFormat::class, ['smsto', 'sms']],
    [OtpType::class, ['totp', 'hotp']],
    [EpcVersion::class, ['001', '002']],
    [EpcCharset::class, ['utf-8', 'iso-8859-1', 'iso-8859-2', 'iso-8859-4', 'iso-8859-5', 'iso-8859-7', 'iso-8859-10', 'iso-8859-15']],
    [BySquareVersion::class, [0, 1, 2]],
    [PaymentType::class, [1, 2, 4]],
    [Periodicity::class, ['d', 'w', 'b', 'm', 'B', 'q', 's', 'a']],
    [Month::class, [1, 2, 4, 8, 16, 32, 64, 128, 256, 512, 1024, 2048]],
    [DirectDebitScheme::class, [0, 1]],
    [DirectDebitType::class, [0, 1]],
]);

it('maps error correction levels to ISO format bits, table rows and recovery', function (): void {
    expect(array_map(static fn (ErrorCorrection $e): int => $e->formatBits(), ErrorCorrection::cases()))->toBe([1, 0, 3, 2])
        ->and(array_map(static fn (ErrorCorrection $e): int => $e->ordinal(), ErrorCorrection::cases()))->toBe([0, 1, 2, 3])
        ->and(array_map(static fn (ErrorCorrection $e): int => $e->recoveryPercent(), ErrorCorrection::cases()))->toBe([7, 15, 25, 30])
        ->and(ErrorCorrection::Low->stronger())->toBe(ErrorCorrection::Medium)
        ->and(ErrorCorrection::Medium->stronger())->toBe(ErrorCorrection::Quartile)
        ->and(ErrorCorrection::Quartile->stronger())->toBe(ErrorCorrection::High)
        ->and(ErrorCorrection::High->stronger())->toBeNull();
});

it('parses error correction input leniently', function (mixed $input, ?ErrorCorrection $expected): void {
    expect(ErrorCorrection::tryFromInput($input))->toBe($expected);
})->with([
    ['l', ErrorCorrection::Low],
    [' M ', ErrorCorrection::Medium],
    ['quartile', ErrorCorrection::Quartile],
    ['HIGH', ErrorCorrection::High],
    ['low', ErrorCorrection::Low],
    ['medium', ErrorCorrection::Medium],
    [ErrorCorrection::High, ErrorCorrection::High],
    ['X', null],
    [3, null],
]);

it('rejects an unknown configured error correction level naming the key', function (): void {
    ErrorCorrection::fromConfig('Z');
})->throws(InvalidQrConfigException::class, 'qr.error_correction');

it('knows the character count indicator lengths per version group', function (Mode $mode, array $bits): void {
    expect([$mode->charCountBits(1), $mode->charCountBits(9), $mode->charCountBits(10), $mode->charCountBits(26), $mode->charCountBits(27), $mode->charCountBits(40)])
        ->toBe($bits);
})->with([
    [Mode::Numeric, [10, 10, 12, 12, 14, 14]],
    [Mode::Alphanumeric, [9, 9, 11, 11, 13, 13]],
    [Mode::Byte, [8, 8, 16, 16, 16, 16]],
    [Mode::Kanji, [8, 8, 10, 10, 12, 12]],
    [Mode::Eci, [0, 0, 0, 0, 0, 0]],
]);

it('labels modes', function (): void {
    expect(Mode::Alphanumeric->key())->toBe('alphanumeric');
});

it('derives cache behaviour from sensitivity', function (): void {
    expect(Sensitivity::Public->isCacheable())->toBeTrue()
        ->and(Sensitivity::Personal->isCacheable())->toBeFalse()
        ->and(Sensitivity::Secret->isCacheable())->toBeFalse()
        ->and(Sensitivity::Public->cacheControl(60))->toBe('public, max-age=60')
        ->and(Sensitivity::Public->cacheControl(60, true))->toBe('public, max-age=60, immutable')
        ->and(Sensitivity::Personal->cacheControl(60, true))->toBe('private, max-age=60')
        ->and(Sensitivity::Secret->cacheControl(60, true))->toBe('no-store, max-age=0');
});

it('maps EPC character sets to their codes and mbstring names', function (): void {
    expect(array_map(static fn (EpcCharset $c): int => $c->code(), EpcCharset::cases()))->toBe([1, 2, 3, 4, 5, 6, 7, 8])
        ->and(EpcCharset::Iso885915->mbEncoding())->toBe('ISO-8859-15')
        ->and(EpcCharset::tryFromCode(2))->toBe(EpcCharset::Iso88591)
        ->and(EpcCharset::tryFromCode(9))->toBeNull();

    foreach (EpcCharset::cases() as $charset) {
        expect(in_array($charset->mbEncoding(), array_map('strtoupper', mb_list_encodings()), true))->toBeTrue();
    }
});

it('describes by square versions', function (): void {
    expect(BySquareVersion::V1_0_0->semver())->toBe('1.0.0')
        ->and(BySquareVersion::V1_1_0->semver())->toBe('1.1.0')
        ->and(BySquareVersion::V1_2_0->semver())->toBe('1.2.0')
        ->and(BySquareVersion::tryFromSemver(' 1.1.0'))->toBe(BySquareVersion::V1_1_0)
        ->and(BySquareVersion::tryFromSemver('2.0.0'))->toBeNull()
        ->and(BySquareVersion::V1_2_0->requiresBeneficiaryName())->toBeTrue()
        ->and(BySquareVersion::V1_1_0->requiresBeneficiaryName())->toBeFalse()
        ->and(BySquareVersion::V1_0_0->hasBeneficiaryBlock())->toBeFalse()
        ->and(BySquareVersion::V1_1_0->hasBeneficiaryBlock())->toBeTrue();
});

it('builds and splits month masks', function (): void {
    expect(Month::mask())->toBe(0)
        ->and(Month::mask(Month::January, Month::March, Month::December))->toBe(2053)
        ->and(Month::mask(...Month::cases()))->toBe(4095)
        ->and(Month::fromMask(2053))->toBe([Month::January, Month::March, Month::December])
        ->and(Month::fromMask(0))->toBe([]);
});

it('knows which periodicities address weekdays', function (): void {
    expect(Periodicity::Weekly->usesWeekday())->toBeTrue()
        ->and(Periodicity::Biweekly->usesWeekday())->toBeTrue()
        ->and(Periodicity::Monthly->usesWeekday())->toBeFalse();
});
