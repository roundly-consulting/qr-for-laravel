<?php

declare(strict_types=1);

namespace RoundlyConsulting\Qr\ValueObjects;

use RoundlyConsulting\Qr\Exceptions\InvalidColorException;

/**
 * An allow-listed SVG paint value. Anything that is not a hex colour, a numeric
 * rgb()/rgba(), one of the 148 CSS named colours, `transparent`/`none` or `currentColor`
 * is rejected, so no attribute-breaking or url()/var()/expression() value can ever reach
 * the markup.
 */
final readonly class Color
{
    /** The CSS Color Module Level 4 named colours. */
    public const array NAMED = [
        'aliceblue', 'antiquewhite', 'aqua', 'aquamarine', 'azure', 'beige', 'bisque', 'black',
        'blanchedalmond', 'blue', 'blueviolet', 'brown', 'burlywood', 'cadetblue', 'chartreuse',
        'chocolate', 'coral', 'cornflowerblue', 'cornsilk', 'crimson', 'cyan', 'darkblue',
        'darkcyan', 'darkgoldenrod', 'darkgray', 'darkgreen', 'darkgrey', 'darkkhaki',
        'darkmagenta', 'darkolivegreen', 'darkorange', 'darkorchid', 'darkred', 'darksalmon',
        'darkseagreen', 'darkslateblue', 'darkslategray', 'darkslategrey', 'darkturquoise',
        'darkviolet', 'deeppink', 'deepskyblue', 'dimgray', 'dimgrey', 'dodgerblue', 'firebrick',
        'floralwhite', 'forestgreen', 'fuchsia', 'gainsboro', 'ghostwhite', 'gold', 'goldenrod',
        'gray', 'green', 'greenyellow', 'grey', 'honeydew', 'hotpink', 'indianred', 'indigo',
        'ivory', 'khaki', 'lavender', 'lavenderblush', 'lawngreen', 'lemonchiffon', 'lightblue',
        'lightcoral', 'lightcyan', 'lightgoldenrodyellow', 'lightgray', 'lightgreen', 'lightgrey',
        'lightpink', 'lightsalmon', 'lightseagreen', 'lightskyblue', 'lightslategray',
        'lightslategrey', 'lightsteelblue', 'lightyellow', 'lime', 'limegreen', 'linen', 'magenta',
        'maroon', 'mediumaquamarine', 'mediumblue', 'mediumorchid', 'mediumpurple',
        'mediumseagreen', 'mediumslateblue', 'mediumspringgreen', 'mediumturquoise',
        'mediumvioletred', 'midnightblue', 'mintcream', 'mistyrose', 'moccasin', 'navajowhite',
        'navy', 'oldlace', 'olive', 'olivedrab', 'orange', 'orangered', 'orchid', 'palegoldenrod',
        'palegreen', 'paleturquoise', 'palevioletred', 'papayawhip', 'peachpuff', 'peru', 'pink',
        'plum', 'powderblue', 'purple', 'rebeccapurple', 'red', 'rosybrown', 'royalblue',
        'saddlebrown', 'salmon', 'sandybrown', 'seagreen', 'seashell', 'sienna', 'silver',
        'skyblue', 'slateblue', 'slategray', 'slategrey', 'snow', 'springgreen', 'steelblue', 'tan',
        'teal', 'thistle', 'tomato', 'turquoise', 'violet', 'wheat', 'white', 'whitesmoke',
        'yellow', 'yellowgreen',
    ];

    private const string CHANNEL = '\s*(?:\d{1,3}(?:\.\d+)?%?)\s*';

    private const string ALPHA = '\s*(?:\d*\.?\d+%?)\s*';

    public string $value;

    /**
     * @throws InvalidColorException
     */
    public function __construct(string $value, string $option = 'color')
    {
        $this->value = self::normalize($value) ?? throw InvalidColorException::invalid($option);
    }

    /**
     * @throws InvalidColorException
     */
    public static function parse(string $value, string $option = 'color'): self
    {
        return new self($value, $option);
    }

    public static function isValid(string $value): bool
    {
        return self::normalize($value) !== null;
    }

    /**
     * The normalised paint value (`none` for a transparent colour).
     */
    public function toSvg(): string
    {
        return $this->value;
    }

    public function isTransparent(): bool
    {
        return $this->value === 'none';
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private static function normalize(string $value): ?string
    {
        $value = trim($value);
        $lower = strtolower($value);

        if ($lower === 'transparent' || $lower === 'none') {
            return 'none';
        }

        if ($lower === 'currentcolor') {
            return 'currentColor';
        }

        if (preg_match('/^#(?:[0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/', $lower) === 1) {
            return $lower;
        }

        if (in_array($lower, self::NAMED, true)) {
            return $lower;
        }

        $channels = self::CHANNEL.','.self::CHANNEL.','.self::CHANNEL;

        if (preg_match('/^rgb\('.$channels.'\)$/', $lower) === 1
            || preg_match('/^rgba\('.$channels.','.self::ALPHA.'\)$/', $lower) === 1) {
            return (string) preg_replace('/\s+/', '', $lower);
        }

        return null;
    }
}
