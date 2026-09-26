<?php

declare(strict_types=1);

use RoundlyConsulting\Qr\Encoder\ShiftJis;

it('compacts Shift JIS kanji into 13-bit values (ISO §7.4.6 example)', function (): void {
    // 点 = 0x935F → 0x135F → 0x13 × 0xC0 + 0x5F = 0x0D9F; 茗 = 0xE4AA → 0x236A → 0x1AAA.
    expect(ShiftJis::kanjiValue('点'))->toBe(0x0D9F)
        ->and(ShiftJis::kanjiValue('茗'))->toBe(0x1AAA)
        ->and(ShiftJis::fromKanjiValue(0x0D9F))->toBe('点')
        ->and(ShiftJis::fromKanjiValue(0x1AAA))->toBe('茗');
});

it('rejects characters without a double-byte kanji form', function (string $character): void {
    expect(ShiftJis::kanjiValue($character))->toBeNull()
        ->and(ShiftJis::isKanji($character))->toBeFalse();
})->with(['a', 'ｱ', 'ž', '😀']);
