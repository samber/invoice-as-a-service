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
