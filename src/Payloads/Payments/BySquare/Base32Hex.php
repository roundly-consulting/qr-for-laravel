<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\BySquare;

use RoundlyConsulting\Qr\Exceptions\BySquareDecodeException;

/**
 * RFC 4648 base32 with the extended-hex alphabet 0–9A–V, most significant bit first, the
 * last group zero-padded and no `=` padding — a subset of the QR alphanumeric set, so the
 * whole code fits one alphanumeric segment.
 *
 * @internal
 */
final class Base32Hex
{
    public const string ALPHABET = '0123456789ABCDEFGHIJKLMNOPQRSTUV';

    public static function encode(string $bytes): string
    {
        $bits = '';

        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $output = '';

        foreach (str_split($bits, 5) as $group) {
            $output .= self::ALPHABET[(int) bindec(str_pad($group, 5, '0'))];
        }

        return $bytes === '' ? '' : $output;
    }

    /**
     * Case-insensitive; `=` padding is ignored and trailing sub-byte bits are dropped.
     *
     * @throws BySquareDecodeException
     */
    public static function decode(string $text): string
    {
        $text = rtrim(strtoupper(trim($text)), '=');

        if ($text === '' || strspn($text, self::ALPHABET) !== strlen($text)) {
            throw BySquareDecodeException::base32hex();
        }

        $bits = '';

        foreach (str_split($text) as $character) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $character)), 5, '0', STR_PAD_LEFT);
        }

        $bytes = '';

        foreach (str_split(substr($bits, 0, intdiv(strlen($bits), 8) * 8), 8) as $byte) {
            $bytes .= chr((int) bindec($byte));
        }

        return strlen($bits) < 8 ? '' : $bytes;
    }
}
