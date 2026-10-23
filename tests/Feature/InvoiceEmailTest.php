<?php

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Posts invoices with an `email` option and checks the message handed to the mailer.
 */
class InvoiceEmailTest extends TestCase
{
    private function payload(array $override = []): array
    {
        return array_replace_recursive([
            'id' => 'INV-42',
            'currency' => 'EUR',
            'lang' => 'en',
            'date' => 1700000000,
            'due_date' => 1702592000,
            'items' => [['title' => 'Consulting', 'price' => 100, 'quantity' => 2, 'tax' => 20]],
            'customer' => ['summary' => 'Customer Inc', 'address_line_1' => '1 Customer street'],
            'company' => ['summary' => 'Company Inc', 'address_line_1' => '1 Company street', 'email' => 'billing@company.test'],
        ], $override);
    }

    public function testEmailSendsPdfAttachmentAndReturnsJson(): void
    {
        Mail::fake();

        $this->postJson('/api/invoice/generate', $this->payload(['email' => [
            'to' => 'john@customer.test',
            'cc' => ['cc@customer.test'],
            'bcc' => ['bcc@customer.test'],
        ]]))
            ->assertStatus(201)
            ->assertJsonPath('email.sent', true)
            ->assertJsonPath('email.recipients', ['john@customer.test', 'cc@customer.test', 'bcc@customer.test']);

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            $this->assertTrue($mail->hasTo('john@customer.test'));
            $this->assertTrue($mail->hasCc('cc@customer.test'));
            $this->assertTrue($mail->hasBcc('bcc@customer.test'));
            $this->assertTrue($mail->hasReplyTo('billing@company.test'));
            $this->assertSame('Invoice INV-42 from Company Inc', $mail->envelope()->subject);
            $this->assertCount(1, $mail->attachments());
            $attachment = $mail->attachments()[0];
            $this->assertSame('application/pdf', $attachment->mime);
            $this->assertMatchesRegularExpression('/^invoice_INV-42_\d+\.pdf$/', $attachment->as);
            $pdf = $attachment->attachWith(fn () => $this->fail('PDF must be attached from memory'), fn ($data) => $data());
            $this->assertStringStartsWith('%PDF-', $pdf);
            $this->assertStringContainsString('240.00 EUR', $mail->render());

            return true;
        });
    }

    public function testEmailAcceptsNamedTemplate(): void
    {
        Mail::fake();

        $this->postJson('/api/invoice/generate', $this->payload(['email' => [
            'to' => 'john@customer.test',
            'template' => 'default',
        ]]))->assertStatus(201);

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => str_contains($mail->render(), 'INV-42'));
    }

    private function attachedPdf(InvoiceMail $mail): string
    {
        return $mail->attachments()[0]->attachWith(fn () => $this->fail('PDF must be attached from memory'), fn ($data) => $data());
    }

    public function testEmailWithoutCcBccNorCompanyEmailHasOnlyToRecipient(): void
    {
        Mail::fake();
        $payload = $this->payload(['email' => ['to' => 'john@customer.test']]);
        unset($payload['company']['email']);

        $this->postJson('/api/invoice/generate', $payload)
            ->assertStatus(201)
            ->assertJsonPath('email.recipients', ['john@customer.test']);

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            $this->assertEmpty($mail->envelope()->cc);
            $this->assertEmpty($mail->envelope()->bcc);
            $this->assertEmpty($mail->envelope()->replyTo);

            return true;
        });
    }

    public function testEmailIsSkippedWhenToIsMissing(): void
    {
        Mail::fake();

        // No JSON envelope: the response is the bare PDF.
        $response = $this->postJson('/api/invoice/generate', $this->payload(['email' => ['cc' => ['cc@customer.test']]]));
        $response->assertStatus(201);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));

        Mail::assertNothingSent();
    }

    public function testEmailBodyShowsPurchaseOrderAndPaymentLinkWhenSet(): void
    {
        Mail::fake();

        $this->postJson('/api/invoice/generate', $this->payload([
            'purchase_order' => 'PO-4242',
            'payment_link' => 'https://example.com/pay',
            'email' => ['to' => 'john@customer.test'],
        ]))->assertStatus(201);

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            $html = $mail->render();
            $this->assertStringContainsString('Purchase order: PO-4242', $html);
            $this->assertStringContainsString('href="https://example.com/pay"', $html);

            return true;
        });
    }

    public function testEmailBodyHidesPurchaseOrderAndPaymentLinkWhenAbsent(): void
    {
        Mail::fake();

        $this->postJson('/api/invoice/generate', $this->payload(['email' => ['to' => 'john@customer.test']]))
            ->assertStatus(201);

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            $html = $mail->render();
            $this->assertStringNotContainsString('Purchase order', $html);
            $this->assertStringNotContainsString('<a href', $html);

            return true;
        });
    }

    public function testEmailBodyIsTranslated(): void
    {
        Mail::fake();

        $this->postJson('/api/invoice/generate', $this->payload([
            'lang' => 'es',
            'purchase_order' => 'PO-1',
            'email' => ['to' => 'john@customer.test'],
        ]))->assertStatus(201);

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            $this->assertStringContainsString('Orden de compra: PO-1', $mail->render());

            return true;
        });
    }

    public function testEmailAttachesSignedPdfWhenSigningIsConfigured(): void
    {
        Mail::fake();
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Mail Signer'], $key);
        openssl_x509_export(openssl_csr_sign($csr, null, $key, 1), $certPem);
        openssl_pkey_export($key, $keyPem);
        config(['services.pdf_sign' => ['cert' => $certPem, 'key' => $keyPem]]);

        $this->postJson('/api/invoice/generate', $this->payload(['email' => ['to' => 'john@customer.test']]))
            ->assertStatus(201);

        Mail::assertSent(InvoiceMail::class, function (InvoiceMail $mail) {
            $this->assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $this->attachedPdf($mail));

            return true;
        });
    }

    public function testEmailIsSkippedWhenNotRequested(): void
    {
        Mail::fake();

        $this->postJson('/api/invoice/generate', $this->payload())->assertStatus(201);

        Mail::assertNothingSent();
    }

    public function testEmailAcceptsTenCcAndTenBcc(): void
    {
        Mail::fake();
        $list = array_map(fn ($i) => "user$i@customer.test", range(1, 10));

        $this->postJson('/api/invoice/generate', $this->payload(['email' => ['to' => 'john@customer.test', 'cc' => $list, 'bcc' => $list]]))
            ->assertStatus(201)
            ->assertJsonPath('email.sent', true);

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => count($mail->envelope()->cc) === 10 && count($mail->envelope()->bcc) === 10);
    }

    public function testEmailTemplateIsTranslated(): void
    {
        Mail::fake();

        $this->postJson('/api/invoice/generate', $this->payload(['lang' => 'es', 'email' => ['to' => 'john@customer.test']]))
            ->assertStatus(201);

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->envelope()->subject === 'Factura INV-42 de Company Inc');
    }

    public function testMailerFailureIsReportedWithoutFailingTheRequest(): void
    {
        Mail::shouldReceive('send')->once()->andThrow(new \RuntimeException('Connection refused'));

        $this->postJson('/api/invoice/generate', $this->payload(['email' => ['to' => 'john@customer.test']]))
            ->assertStatus(201)
            ->assertJsonPath('email.sent', false)
            ->assertJsonPath('email.message', 'Connection refused');
    }

    #[DataProvider('invalidEmailProvider')]
    public function testInvalidEmailOptionIsRejected(array $email, string $errorKey): void
    {
        Mail::fake();

        $this->postJson('/api/invoice/generate', $this->payload(['email' => $email]))
            ->assertStatus(422)
            ->assertJsonStructure([$errorKey]);

        Mail::assertNothingSent();
    }

    public static function invalidEmailProvider(): array
    {
        return [
            'malformed to' => [['to' => 'not-an-email'], 'email.to'],
            'malformed cc entry' => [['to' => 'a@b.test', 'cc' => ['nope']], 'email.cc.0'],
            'cc not an array' => [['to' => 'a@b.test', 'cc' => 'a@b.test'], 'email.cc'],
            'too many cc' => [['to' => 'a@b.test', 'cc' => array_fill(0, 11, 'a@b.test')], 'email.cc'],
            'bcc not an array' => [['to' => 'a@b.test', 'bcc' => 'a@b.test'], 'email.bcc'],
            'too many bcc' =>[['to' => 'a@b.test', 'bcc' => array_fill(0, 11, 'a@b.test')], 'email.bcc'],
            'unknown template' => [['to' => 'a@b.test', 'template' => 'missing'], 'email.template'],
            'path traversal template' => [['to' => 'a@b.test', 'template' => '../invoices/default'], 'email.template'],
        ];
    }
}
