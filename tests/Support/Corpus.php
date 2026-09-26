<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\Tests\Support;

/**
 * Deterministic LZMA test inputs (seeded mt_rand), covering literals, long matches,
 * every distance band and repeated distances.
 */
final class Corpus
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        mt_srand(7064);

        $random = static function (int $length): string {
            $bytes = '';

            for ($i = 0; $i < $length; $i++) {
                $bytes .= chr(mt_rand(0, 255));
            }

            return $bytes;
        };

        $distances = '';

        foreach ([1, 3, 7, 20, 100, 200, 600, 2000, 5000, 20000] as $gap) {
            $block = $random(12);
            $distances .= $block.$random($gap).$block;
        }

        $reps = '';

        for ($i = 0; $i < 400; $i++) {
            $reps .= ['alpha', 'beta', 'gamma', 'delta'][$i % 4].chr(48 + $i % 10).['alpha', 'beta', 'gamma', 'delta'][($i + 2) % 4];
        }

        return [
            'empty' => '',
            'one byte' => 'x',
            'two bytes' => 'xy',
            'all literal' => $random(3000),
            'highly repetitive' => str_repeat('a', 10000),
            'longer than 273' => 'head'.str_repeat('abc', 400).'tail',
            'distance bands' => $distances,
            'rep distances' => $reps,
            'short reps' => str_repeat('abababab'.chr(0).'cdcd', 300),
            'utf-8 text' => str_repeat('Príspevok na kávu – ďakujeme! ', 80),
        ];
    }
}
