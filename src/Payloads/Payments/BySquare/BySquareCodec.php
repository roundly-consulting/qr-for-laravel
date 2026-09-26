<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Payloads\Payments\BySquare;

use RoundlyConsulting\Qr\Compression\Lzma\LzmaDecoder;
use RoundlyConsulting\Qr\Compression\Lzma\LzmaEncoder;
use RoundlyConsulting\Qr\Enums\BySquare\BySquareVersion;
use RoundlyConsulting\Qr\Exceptions\BySquareDecodeException;
use RoundlyConsulting\Qr\Exceptions\PaymentPayloadTooLongException;

/**
 * PAY by square framing:
 *
 *   checked = CRC-32 (IEEE, little-endian) ‖ serialised data
 *   body    = raw LZMA1 stream of `checked` (lc=3, lp=0, pb=2, 128 KiB dictionary, no
 *             header, terminated with the end-of-payload marker)
 *   frame   = header (by square type 0 / version, document type 0 / reserved)
 *             ‖ uint16 little-endian length of `checked` ‖ body
 *   code    = base32hex(frame)
 *
 * @internal
 */
final class BySquareCodec
{
    public const int MAX_LENGTH = 65535;

    /** V40-L alphanumeric capacity — nothing longer can come from a QR code. */
    public const int MAX_ENCODED_LENGTH = 4296;

    /**
     * @throws PaymentPayloadTooLongException
     */
    public static function encode(string $serialized, BySquareVersion $version): string
    {
        $checked = pack('V', crc32($serialized)).$serialized;

        if (strlen($checked) > self::MAX_LENGTH) {
            throw PaymentPayloadTooLongException::bytes(PayBySquare::TYPE, strlen($checked), self::MAX_LENGTH);
        }

        $header = chr($version->value).chr(0).pack('v', strlen($checked));

        return Base32Hex::encode($header.LzmaEncoder::encode($checked));
    }

    /**
     * @throws BySquareDecodeException
     */
    public static function decode(string $code): BySquareFrame
    {
        if (strlen($code) > self::MAX_ENCODED_LENGTH) {
            throw BySquareDecodeException::base32hex();
        }

        $bytes = Base32Hex::decode($code);

        if (strlen($bytes) < 4) {
            throw BySquareDecodeException::header();
        }

        $type = ord($bytes[0]) >> 4;
        $version = BySquareVersion::tryFrom(ord($bytes[0]) & 0x0F);

        if ($type !== 0 || $version === null || (ord($bytes[1]) >> 4) !== 0) {
            throw BySquareDecodeException::header();
        }

        $length = ord($bytes[2]) | (ord($bytes[3]) << 8);

        if ($length < 4) {
            throw BySquareDecodeException::length();
        }

        $checked = LzmaDecoder::decode(substr($bytes, 4), $length);

        $serialized = substr($checked, 4);

        if (pack('V', crc32($serialized)) !== substr($checked, 0, 4)) {
            throw BySquareDecodeException::crc();
        }

        if (! mb_check_encoding($serialized, 'UTF-8')) {
            throw BySquareDecodeException::fields();
        }

        return new BySquareFrame($version, $serialized);
    }
}
