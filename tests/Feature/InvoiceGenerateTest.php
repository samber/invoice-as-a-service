<?php

namespace Tests\Feature;

use Tests\TestCase;

class InvoiceGenerateTest extends TestCase
{
    // 1x1 transparent PNG.
    private const PNG_B64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==';

    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'id' => 'INV-1',
            'currency' => 'EUR',
            'lang' => 'en',
            'date' => 1700000000,
            'due_date' => 1702592000,
            'items' => [
                ['title' => 'Consulting', 'price' => 100, 'quantity' => 2, 'tax' => 20],
            ],
            'customer' => ['summary' => 'Customer Inc', 'address_line_1' => '1 Customer street'],
            'company' => ['summary' => 'Company Inc', 'address_line_1' => '1 Company street'],
        ], $override);
    }

    public function testGenerateReturnsPdf(): void
    {
        $response = $this->postJson('/api/invoice/generate', $this->payload());

        $response->assertStatus(201);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
    }

    // Info dictionary strings are stored as UTF-16BE with a BOM (see Cpdf::o_info).
    private function pdfInfoString(string $label, string $value): string
    {
        return "/$label (\xFE\xFF" . mb_convert_encoding($value, 'UTF-16BE', 'UTF-8') . ')';
    }

    public function testGenerateSetsPdfMetadata(): void
    {
        $before = time();
        $pdf = $this->postJson('/api/invoice/generate', $this->payload())->getContent();

        $this->assertStringContainsString($this->pdfInfoString('Author', 'Company Inc'), $pdf);
        $this->assertStringContainsString($this->pdfInfoString('Creator', 'invoice-as-a-service'), $pdf);
        $this->assertStringContainsString($this->pdfInfoString('Producer', 'invoice-as-a-service'), $pdf);
        $this->assertStringContainsString($this->pdfInfoString('Subject', 'Invoice for Customer Inc'), $pdf);

        // Dates are plain ASCII, in UTC, set at generation time (not the invoice date).
        $this->assertSame(1, preg_match("/\\/CreationDate \\(D:(\\d{14})\\+00'00'\\)/", $pdf, $m));
        $created = \DateTimeImmutable::createFromFormat('YmdHis', $m[1], new \DateTimeZone('UTC'))->getTimestamp();
        $this->assertGreaterThanOrEqual($before, $created);
        $this->assertLessThanOrEqual(time(), $created);
    }

    public function testGenerateSetsPdfAuthorWithNonAsciiCompanyName(): void
    {
        $payload = $this->payload(['company' => ['summary' => 'Café Ünï Inc']]);
        $pdf = $this->postJson('/api/invoice/generate', $payload)->getContent();

        $this->assertStringContainsString($this->pdfInfoString('Author', 'Café Ünï Inc'), $pdf);
    }

    public function testGenerateWithoutQuantityDefaultsToOne(): void
    {
        $payload = $this->payload();
        unset($payload['items'][0]['quantity']);

        $this->postJson('/api/invoice/generate', $payload)->assertStatus(201);
    }

    public function testGenerateInSpanish(): void
    {
        $this->postJson('/api/invoice/generate', $this->payload(['lang' => 'es']))->assertStatus(201);
    }

    public function testGenerateWithBase64Logo(): void
    {
        $payload = $this->payload(['company' => ['logo_b64' => self::PNG_B64]]);

        $this->postJson('/api/invoice/generate', $payload)->assertStatus(201);
    }

    public function testGenerateRejectsInvalidBase64Logo(): void
    {
        $payload = $this->payload(['company' => ['logo_b64' => 'data:image/png;base64,' . base64_encode('not an image')]]);

        $this->postJson('/api/invoice/generate', $payload)
            ->assertStatus(422)
            ->assertJsonStructure(['company.logo_b64']);
    }

    public function testGenerateRejectsBase64LogoWithWrongMime(): void
    {
        $payload = $this->payload(['company' => ['logo_b64' => 'data:text/plain;base64,aGVsbG8=']]);

        $this->postJson('/api/invoice/generate', $payload)->assertStatus(422);
    }

    public function testGenerateRejectsEmptyBody(): void
    {
        $this->postJson('/api/invoice/generate', [])
            ->assertStatus(422)
            ->assertJsonStructure(['id', 'currency', 'lang', 'date', 'due_date', 'items', 'customer.summary', 'company.summary']);
    }

    public function testGenerateRejectsEmptyItems(): void
    {
        $payload = $this->payload();
        $payload['items'] = [];

        $this->postJson('/api/invoice/generate', $payload)->assertStatus(422);
    }

    public function testGenerateRejectsUnsupportedLanguage(): void
    {
        $this->postJson('/api/invoice/generate', $this->payload(['lang' => 'fr']))->assertStatus(422);
    }

    public function testGenerateRejectsMalformedOtherField(): void
    {
        $payload = $this->payload(['customer' => ['other' => [['title' => 'only title']]]]);

        $this->postJson('/api/invoice/generate', $payload)->assertStatus(422);
    }

    public function testGenerateAcceptsOtherFields(): void
    {
        $payload = $this->payload(['customer' => ['other' => ['plain', ['title' => 'VAT', 'content' => 'FR123']]]]);

        $this->postJson('/api/invoice/generate', $payload)->assertStatus(201);
    }

    public function testGenerateAcceptsPurchaseOrder(): void
    {
        $this->postJson('/api/invoice/generate', $this->payload(['purchase_order' => 'PO-42']))->assertStatus(201);
    }

    public function testGenerateAcceptsNullPurchaseOrder(): void
    {
        $this->postJson('/api/invoice/generate', $this->payload(['purchase_order' => null]))->assertStatus(201);
    }

    public function testGenerateRejectsTooLongPurchaseOrder(): void
    {
        $this->postJson('/api/invoice/generate', $this->payload(['purchase_order' => str_repeat('a', 256)]))
            ->assertStatus(422)
            ->assertJsonStructure(['purchase_order']);
    }

    public function testGenerateRejectsNonStringPurchaseOrder(): void
    {
        $this->postJson('/api/invoice/generate', $this->payload(['purchase_order' => ['PO-42']]))
            ->assertStatus(422)
            ->assertJsonStructure(['purchase_order']);
    }

    public function testTemplateRendersPurchaseOrderOnlyWhenSet(): void
    {
        $render = function (?string $purchaseOrder, string $lang): string {
            app()->setLocale($lang);
            $pdf = new \App\Helpers\PDF(
                'INV-1', 'EUR', $lang, '01 Jan 2024', '01 Feb 2024', false, null, $purchaseOrder, null,
                [['title' => 'Consulting', 'price' => 100, 'quantity' => 1, 'tax' => 20, 'total' => 100]],
                ['summary' => 'Customer Inc', 'address_line_1' => '1 Customer street'],
                ['summary' => 'Company Inc', 'address_line_1' => '1 Company street'],
                100, 20, 120
            );

            return view('invoices.default', ['data' => $pdf])->render();
        };

        $this->assertStringContainsString('Purchase order', $render('PO-42', 'en'));
        $this->assertStringContainsString('PO-42', $render('PO-42', 'en'));
        $this->assertStringContainsString('Orden de compra', $render('PO-42', 'es'));
        $this->assertStringNotContainsString('Purchase order', $render(null, 'en'));
    }

    public function testGenerateRejectsZapierUrlOutsideZapier(): void
    {
        $payload = $this->payload(['zapier' => ['zap_url' => 'https://example.com/hooks/catch/1/abc/']]);

        $this->postJson('/api/invoice/generate', $payload)->assertStatus(422)->assertJsonStructure(['zapier.zap_url']);
    }

    public function testGenerateRejectsUnresolvableWebhookUrl(): void
    {
        $payload = $this->payload(['webhook' => ['url' => 'https://no-such-host.invalid/hook']]);

        $this->postJson('/api/invoice/generate', $payload)->assertStatus(422)->assertJsonStructure(['webhook.url']);
    }

    public function testGenerateRejectsUnresolvableS3Url(): void
    {
        $payload = $this->payload(['s3' => ['presigned_url' => 'https://no-such-host.invalid/key.pdf']]);

        $this->postJson('/api/invoice/generate', $payload)->assertStatus(422)->assertJsonStructure(['s3.presigned_url']);
    }

    public function testFtpConnectionFailureIsReported(): void
    {
        // Port 1 is reserved and closed: the connection is refused immediately.
        $payload = $this->payload(['ftp' => [
            'host' => '127.0.0.1',
            'port' => 1,
            'username' => 'user',
            'password' => 'pass',
            'path' => '/invoices',
        ]]);

        $this->postJson('/api/invoice/generate', $payload)
            ->assertStatus(201)
            ->assertJsonPath('ftp.uploaded', false)
            ->assertJsonPath('ftp.path', null);
    }

    public function testApiErrorsRenderAsJsonWithoutAcceptHeader(): void
    {
        $response = $this->call('POST', '/api/does-not-exist');

        $response->assertStatus(404);
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
    }

    public function testCorsPreflightIsAllowed(): void
    {
        $this->call('OPTIONS', '/api/invoice/generate', [], [], [], [
            'HTTP_ORIGIN' => 'https://example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        ])->assertHeader('Access-Control-Allow-Origin', '*');
    }
}
