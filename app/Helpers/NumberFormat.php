<?php

namespace App\Helpers;

use Log;

/**
 * Formats numbers on invoices. A format is an example string of the number 1234.56,
 * for instance "1,234.56", "1.234,56" or "1 234,56": the separators are read from it.
 */
class NumberFormat
{
    public const DEFAULT_EXAMPLE = '1,234.56';

    // Plain spaces in an example become this character, so a number never wraps across two lines.
    private const NARROW_NO_BREAK_SPACE = "\u{202F}";

    // Digits are fixed so that the thousands and decimal separators are unambiguous.
    // The thousands separator is optional and is any single character that is not a letter,
    // a digit or a control character (space, quote, dot, comma...).
    private const EXAMPLE_PATTERN = '/^1([^\p{L}\p{N}\p{C}]?)234([.,])56$/Du';

    // Upper bound of the `decimals` request field.
    private const MAX_DECIMALS = 10;

    /**
     * @return array{thousands: string, decimal: string}|null null when the example is not valid
     */
    public static function parse($example)
    {
        if (!is_string($example) || preg_match(self::EXAMPLE_PATTERN, $example, $m) !== 1) {
            return null;
        }

        $thousands = $m[1] === ' ' ? self::NARROW_NO_BREAK_SPACE : $m[1];
        $decimal = $m[2];

        // "1.234.56" is ambiguous.
        if ($thousands === $decimal) {
            return null;
        }

        return ['thousands' => $thousands, 'decimal' => $decimal];
    }

    /**
     * Picks the format of a request: the request value, else the INVOICE_NUMBER_FORMAT
     * instance setting, else the default. An invalid instance setting must not break every
     * request, so it is logged and ignored. An invalid request value is rejected earlier by
     * the NumberFormatExample validation rule.
     */
    public static function resolve($requested)
    {
        if (is_string($requested) && self::parse($requested) !== null) {
            return $requested;
        }

        $configured = config('invoice.number_format');
        if (is_string($configured) && $configured !== '') {
            if (self::parse($configured) !== null) {
                return $configured;
            }
            Log::warning('Ignoring invalid INVOICE_NUMBER_FORMAT, expected an example of 1234.56 such as "1,234.56"', [
                'value' => $configured,
            ]);
        }

        return self::DEFAULT_EXAMPLE;
    }

    /**
     * Formats with exactly $decimals decimals (rounded half up).
     */
    public static function format($number, $decimals, $example)
    {
        $separators = self::parse($example) ?? self::parse(self::DEFAULT_EXAMPLE);

        $rounded = round((float) $number, $decimals);
        // number_format would print "-0.00" for a negative number that rounds to zero.
        if ($rounded == 0) {
            $rounded = 0.0;
        }

        return number_format($rounded, $decimals, $separators['decimal'], $separators['thousands']);
    }

    /**
     * Formats with at least $minDecimals decimals and as many more as the number needs
     * (up to 10), so that a price of 0.125 is not shown as 0.13 and a quantity of 1.5 is
     * not shown as 2.
     */
    public static function formatAuto($number, $minDecimals, $example)
    {
        $number = (float) $number;

        // sprintf avoids the exponent notation of PHP's float to string cast on tiny numbers.
        $fraction = explode('.', sprintf('%.' . self::MAX_DECIMALS . 'F', abs($number)))[1];
        $needed = strlen(rtrim($fraction, '0'));

        return self::format($number, max($minDecimals, $needed), $example);
    }
}
