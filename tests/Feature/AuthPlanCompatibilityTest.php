<?php

declare(strict_types=1);

use RoundlyConsulting\Crypto\Otp\ProvisioningUri;
use RoundlyConsulting\Qr\DataTransferObjects\QrOptions;
use RoundlyConsulting\Qr\Enums\ErrorCorrection;
use RoundlyConsulting\Qr\Facades\Qr;
use RoundlyConsulting\Qr\Support\MatrixMemo;
use RoundlyConsulting\Qr\Testing\MatrixDecoder;

/**
 * Pins the exact calls the authentication package's QR adapter makes, so a rename here
 * fails in this package first.
 */
it('supports the fluent otpauth chain used for 2FA enrolment', function (): void {
    $uri = ProvisioningUri::totp('JBSWY3DPEHPK3PXP', 'user@acme.io', 'Acme: Admin');
    app(MatrixMemo::class)->flush();

    $markup = Qr::otpauth($uri)->size(240)->errorCorrection(ErrorCorrection::Medium)->title('Scan with your authenticator app')->svg()->toString();

    $document = new DOMDocument;

    expect($markup)->toBeString()
        ->and($document->loadXML($markup))->toBeTrue()
        ->and($markup)->toContain('width="240"')
        ->and($markup)->toContain('<title>Scan with your authenticator app</title>')
        ->and(app(MatrixMemo::class)->count())->toBe(0);

    expect(MatrixDecoder::decode(Qr::otpauth($uri)->matrix())->bytes)->toBe($uri);
});

it('supports the one-call options form', function (): void {
    $uri = ProvisioningUri::totp('JBSWY3DPEHPK3PXP', 'user@acme.io', 'Acme');
    $svg = Qr::svg($uri, new QrOptions(size: 200, errorCorrection: ErrorCorrection::Medium));

    expect($svg->width())->toBe(200)
        ->and(MatrixDecoder::decode($svg->matrix())->bytes)->toBe($uri);
});
