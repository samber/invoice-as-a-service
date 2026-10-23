<?php

namespace Tests\Unit;

use App\Helpers\PDF;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

class PdfNumberFormatTest extends TestCase
{
    private function pdf($numberFormat, $decimals = 2): PDF
    {
        return new PDF(
            'INV-1', 'EUR', 'en', '01 Jan 2026', '31 Jan 2026', false, null, null, null,
            [['title' => 'Consulting', 'price' => 1234.5, 'quantity' => 1.5, 'tax' => 5.5, 'total' => 1851.75]],
            ['summary' => 'Customer Inc', 'address_line_1' => '1 Customer street'],
            ['summary' => 'Company Inc', 'address_line_1' => '1 Company street'],
            1851.75, 101.84, 1953.59,
            $decimals,
            $numberFormat
        );
    }

    private function html(PDF $pdf): string
    {
        return View::make('invoices.default', ['data' => $pdf])->render();
    }

    public function testViewPrintsEnglishFormatByDefault(): void
    {
        config(['invoice.number_format' => '1,234.56']);

        $html = $this->html($this->pdf(null));

        $this->assertStringContainsString('1,234.50 EUR', $html);
        $this->assertStringContainsString('1,851.75 EUR', $html);
        $this->assertStringContainsString('101.84 EUR', $html);
        $this->assertStringContainsString('1,953.59 EUR', $html);
        $this->assertStringContainsString('1.5<', $html);
        $this->assertStringContainsString('5.5 %', $html);
    }

    public function testViewPrintsRequestedFormat(): void
    {
        $html = $this->html($this->pdf('1.234,56'));

        $this->assertStringContainsString('1.234,50 EUR', $html);
        $this->assertStringContainsString('1.851,75 EUR', $html);
        $this->assertStringContainsString('1.953,59 EUR', $html);
        $this->assertStringContainsString('1,5<', $html);
        $this->assertStringContainsString('5,5 %', $html);
    }

    public function testViewUsesInstanceFormatWhenRequestHasNone(): void
    {
        config(['invoice.number_format' => '1.234,56']);

        $this->assertStringContainsString('1.953,59 EUR', $this->html($this->pdf(null)));
    }

    public function testRequestFormatWinsOverInstanceFormat(): void
    {
        config(['invoice.number_format' => '1.234,56']);

        $this->assertStringContainsString('1,953.59 EUR', $this->html($this->pdf('1,234.56')));
    }

    public function testDecimalsApplyToTotals(): void
    {
        $html = $this->html($this->pdf('1,234.56', 0));

        $this->assertStringContainsString('1,954 EUR', $html);
        $this->assertStringContainsString('1,852 EUR', $html);
    }
}
