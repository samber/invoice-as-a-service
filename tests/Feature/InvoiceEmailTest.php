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

    public function testEmailIsSkippedWhenNotRequested(): void
    {
        Mail::fake();

        $this->postJson('/api/invoice/generate', $this->payload())->assertStatus(201);

        Mail::assertNothingSent();
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
            'too many bcc' => [['to' => 'a@b.test', 'bcc' => array_fill(0, 11, 'a@b.test')], 'email.bcc'],
            'unknown template' => [['to' => 'a@b.test', 'template' => 'missing'], 'email.template'],
            'path traversal template' => [['to' => 'a@b.test', 'template' => '../invoices/default'], 'email.template'],
        ];
    }
}
