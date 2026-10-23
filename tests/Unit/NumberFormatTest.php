<?php

namespace Tests\Unit;

use App\Helpers\NumberFormat;
use Tests\TestCase;

class NumberFormatTest extends TestCase
{
    private const NNBSP = "\u{202F}";

    public static function validExamples(): array
    {
        return [
            'english' => ['1,234.56', ',', '.'],
            'european' => ['1.234,56', '.', ','],
            'space thousands' => ['1 234,56', self::NNBSP, ','],
            'space thousands, dot decimal' => ['1 234.56', self::NNBSP, '.'],
            'swiss apostrophe' => ["1'234.56", "'", '.'],
            'narrow no-break space kept' => ["1\u{202F}234,56", self::NNBSP, ','],
            'no-break space kept' => ["1\u{00A0}234,56", "\u{00A0}", ','],
            'no thousands, dot' => ['1234.56', '', '.'],
            'no thousands, comma' => ['1234,56', '', ','],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('validExamples')]
    public function testParseExtractsSeparators(string $example, string $thousands, string $decimal): void
    {
        $this->assertSame(['thousands' => $thousands, 'decimal' => $decimal], NumberFormat::parse($example));
    }

    public static function invalidExamples(): array
    {
        return [
            'empty' => [''],
            'same separators' => ['1.234.56'],
            'same separators comma' => ['1,234,56'],
            'no decimal part' => ['1,234'],
            'plain integer' => ['1234'],
            'wrong digits' => ['12,34.56'],
            'wrong decimals digits' => ['1,234.5'],
            'too many decimals digits' => ['1,234.567'],
            'letter as thousands' => ['1a234.56'],
            'digit as thousands' => ['1 2234.56'],
            'two-char thousands' => ['1,,234.56'],
            'decimal is not . or ,' => ['1,234;56'],
            'leading text' => ['x1,234.56'],
            'trailing text' => ['1,234.56 EUR'],
            'trailing newline' => ["1,234.56\n"],
            'newline as thousands' => ["1\n234.56"],
            'tab as thousands' => ["1\t234.56"],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidExamples')]
    public function testParseRejectsInvalidExamples(string $example): void
    {
        $this->assertNull(NumberFormat::parse($example));
    }

    public function testFormatEnglish(): void
    {
        $this->assertSame('1,234.50', NumberFormat::format(1234.5, 2, '1,234.56'));
    }

    public function testFormatEuropean(): void
    {
        $this->assertSame('1.234,50', NumberFormat::format(1234.5, 2, '1.234,56'));
    }

    public function testFormatSpaceExampleOutputsNarrowNoBreakSpace(): void
    {
        $this->assertSame('1' . self::NNBSP . '234,50', NumberFormat::format(1234.5, 2, '1 234,56'));
    }

    public function testFormatWithoutThousandsSeparator(): void
    {
        $this->assertSame('1234567,89', NumberFormat::format(1234567.891, 2, '1234,56'));
    }

    public function testFormatSwissApostrophe(): void
    {
        $this->assertSame("1'234'567.89", NumberFormat::format(1234567.891, 2, "1'234.56"));
    }

    public function testFormatZero(): void
    {
        $this->assertSame('0.00', NumberFormat::format(0, 2, '1,234.56'));
    }

    public function testFormatNegative(): void
    {
        $this->assertSame('-1,234.50', NumberFormat::format(-1234.5, 2, '1,234.56'));
    }

    public function testFormatNegativeRoundingToZeroHasNoMinusSign(): void
    {
        $this->assertSame('0.00', NumberFormat::format(-0.001, 2, '1,234.56'));
    }

    public function testFormatLargeNumber(): void
    {
        $this->assertSame('999,999,999.99', NumberFormat::format(999999999.99, 2, '1,234.56'));
    }

    public function testFormatBelowThousandHasNoSeparator(): void
    {
        $this->assertSame('999.00', NumberFormat::format(999, 2, '1,234.56'));
        $this->assertSame('1,000.00', NumberFormat::format(1000, 2, '1,234.56'));
    }

    public function testFormatZeroDecimalsDropsDecimalSeparator(): void
    {
        $this->assertSame('1,235', NumberFormat::format(1234.56, 0, '1,234.56'));
    }

    public function testFormatTenDecimals(): void
    {
        $this->assertSame('0.1234567890', NumberFormat::format(0.123456789, 10, '1,234.56'));
    }

    public function testFormatRoundsHalfUpAtBoundary(): void
    {
        $this->assertSame('1.01', NumberFormat::format(1.005, 2, '1,234.56'));
        $this->assertSame('1.00', NumberFormat::format(1.0049, 2, '1,234.56'));
    }

    public function testFormatAcceptsNumericStrings(): void
    {
        $this->assertSame('1,234.50', NumberFormat::format('1234.5', 2, '1,234.56'));
    }

    public function testFormatWithInvalidExampleFallsBackToDefault(): void
    {
        $this->assertSame('1,234.50', NumberFormat::format(1234.5, 2, 'garbage'));
    }

    public function testFormatAutoKeepsMinimumDecimals(): void
    {
        $this->assertSame('10.50', NumberFormat::formatAuto(10.5, 2, '1,234.56'));
        $this->assertSame('3', NumberFormat::formatAuto(3, 0, '1,234.56'));
    }

    public function testFormatAutoKeepsExtraPrecision(): void
    {
        $this->assertSame('0.125', NumberFormat::formatAuto(0.125, 2, '1,234.56'));
        $this->assertSame('1.234,125', NumberFormat::formatAuto(1234.125, 2, '1.234,56'));
    }

    public function testFormatAutoLocalisesFractionalQuantity(): void
    {
        $this->assertSame('1,5', NumberFormat::formatAuto(1.5, 0, '1.234,56'));
        $this->assertSame('5,5', NumberFormat::formatAuto(5.5, 0, '1.234,56'));
    }

    public function testResolvePrefersRequestOverConfig(): void
    {
        config(['invoice.number_format' => '1.234,56']);

        $this->assertSame('1234,56', NumberFormat::resolve('1234,56'));
    }

    public function testResolveFallsBackToConfigWhenRequestIsNull(): void
    {
        config(['invoice.number_format' => '1.234,56']);

        $this->assertSame('1.234,56', NumberFormat::resolve(null));
    }

    public function testResolveFallsBackToDefaultWhenConfigIsInvalid(): void
    {
        config(['invoice.number_format' => 'nonsense']);

        $this->assertSame(NumberFormat::DEFAULT_EXAMPLE, NumberFormat::resolve(null));
    }

    public function testResolveFallsBackToDefaultWhenConfigIsEmptyOrMissing(): void
    {
        config(['invoice.number_format' => '']);
        $this->assertSame(NumberFormat::DEFAULT_EXAMPLE, NumberFormat::resolve(null));

        config(['invoice.number_format' => null]);
        $this->assertSame(NumberFormat::DEFAULT_EXAMPLE, NumberFormat::resolve(null));
    }
}
