<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Enums;

use RoundlyConsulting\Enums\Helpers;

/**
 * EPC069-12 v3.1 §2.2 "Character set" codes 1–8.
 */
enum EpcCharset: string
{
    use Helpers;

    case Utf8 = 'utf-8';
    case Iso88591 = 'iso-8859-1';
    case Iso88592 = 'iso-8859-2';
    case Iso88594 = 'iso-8859-4';
    case Iso88595 = 'iso-8859-5';
    case Iso88597 = 'iso-8859-7';
    case Iso885910 = 'iso-8859-10';
    case Iso885915 = 'iso-8859-15';

    public function code(): int
    {
        return match ($this) {
            self::Utf8 => 1,
            self::Iso88591 => 2,
            self::Iso88592 => 3,
            self::Iso88594 => 4,
            self::Iso88595 => 5,
            self::Iso88597 => 6,
            self::Iso885910 => 7,
            self::Iso885915 => 8,
        };
    }

    /**
     * The encoding name `mb_convert_encoding()` understands.
     */
    public function mbEncoding(): string
    {
        return strtoupper($this->value);
    }

    public static function tryFromCode(int $code): ?self
    {
        foreach (self::cases() as $case) {
            if ($case->code() === $code) {
                return $case;
            }
        }

        return null;
    }
}
