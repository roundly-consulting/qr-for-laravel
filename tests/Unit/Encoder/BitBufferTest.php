<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\BitBuffer;

it('appends MSB-first bits and exposes codewords', function (): void {
    $buffer = new BitBuffer;
    $buffer->append(0b0100, 4);
    $buffer->append(0, 0);
    $buffer->append(0xFFF, 4);
    $buffer->appendBits('00010001');

    expect($buffer->length())->toBe(16)
        ->and($buffer->toString())->toBe('0100111100010001')
        ->and($buffer->toCodewords())->toBe([0x4F, 0x11])
        ->and((new BitBuffer)->toCodewords())->toBe([]);
});
