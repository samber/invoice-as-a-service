<?php

namespace Tests\Unit;

use App\Rules\NumberFormatExample;
use Tests\TestCase;

class NumberFormatExampleRuleTest extends TestCase
{
    public function testPassesOnValidExamples(): void
    {
        $rule = new NumberFormatExample();

        foreach (['1,234.56', '1.234,56', '1 234,56', "1'234.56", '1234.56', '1234,56'] as $example) {
            $this->assertTrue($rule->passes('number_format', $example), $example);
        }
    }

    public function testFailsOnInvalidExamples(): void
    {
        $rule = new NumberFormatExample();

        foreach (['', '1.234.56', '1,234', 'abc', '12,34.56'] as $example) {
            $this->assertFalse($rule->passes('number_format', $example), $example);
        }
    }

    public function testFailsOnNonStrings(): void
    {
        $rule = new NumberFormatExample();

        foreach ([1234.56, 1234, true, ['1,234.56'], new \stdClass()] as $value) {
            $this->assertFalse($rule->passes('number_format', $value));
        }
    }

    public function testMessageShowsExamples(): void
    {
        $this->assertStringContainsString('1,234.56', (new NumberFormatExample())->message());
    }
}
