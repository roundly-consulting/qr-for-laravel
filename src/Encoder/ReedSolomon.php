<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Encoder;

/**
 * Reed-Solomon error-correction codewords (ISO/IEC 18004:2015 §7.5.2): the remainder of
 * the data polynomial times x^n divided by the generator ∏(x − α^i), i = 0..n−1.
 *
 * @internal
 */
final class ReedSolomon
{
    /** @var array<int, list<int>> generator coefficients (highest degree first, leading 1 omitted) per degree */
    private static array $generators = [];

    /**
     * Generator polynomial of the given degree, as field elements for x^(n−1)..x^0.
     *
     * @return list<int>
     */
    public static function generator(int $degree): array
    {
        if (isset(self::$generators[$degree])) {
            return self::$generators[$degree];
        }

        // Start from the constant polynomial 1 and multiply in (x − α^i) for each root.
        $poly = [1];

        for ($i = 0; $i < $degree; $i++) {
            $root = GaloisField::exp($i);
            $next = array_fill(0, count($poly) + 1, 0);

            foreach ($poly as $j => $coefficient) {
                $next[$j] ^= $coefficient;
                $next[$j + 1] ^= GaloisField::multiply($coefficient, $root);
            }

            $poly = $next;
        }

        return self::$generators[$degree] = array_slice($poly, 1);
    }

    /**
     * @param  list<int>  $data
     * @return list<int> the $degree error-correction codewords
     */
    public static function remainder(array $data, int $degree): array
    {
        $generator = self::generator($degree);
        $remainder = array_fill(0, $degree, 0);

        foreach ($data as $codeword) {
            $factor = $codeword ^ $remainder[0];
            array_shift($remainder);
            $remainder[] = 0;

            if ($factor === 0) {
                continue;
            }

            foreach ($generator as $i => $coefficient) {
                $remainder[$i] ^= GaloisField::multiply($coefficient, $factor);
            }
        }

        return array_values($remainder);
    }

    /**
     * True when the codeword block (data followed by ECC) is a valid code word, i.e. every
     * syndrome S_i = C(α^i), i = 0..n−1, is zero.
     *
     * @param  list<int>  $block
     */
    public static function isValid(array $block, int $degree): bool
    {
        for ($i = 0; $i < $degree; $i++) {
            $root = GaloisField::exp($i);
            $sum = 0;

            foreach ($block as $codeword) {
                $sum = GaloisField::multiply($sum, $root) ^ $codeword;
            }

            if ($sum !== 0) {
                return false;
            }
        }

        return true;
    }
}
