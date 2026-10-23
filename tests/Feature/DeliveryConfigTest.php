<?php

namespace Tests\Feature;

use App\Mail\InvoiceMail;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\LocalServers;
use Tests\TestCase;

/**
 * Instance-wide delivery settings (DELIVERY_* env vars, config/delivery.php) and the
 * DELIVERY_PER_REQUEST_ENABLED switch that locks delivery settings out of the request body.
 */
class DeliveryConfigTest extends TestCase
{
    use LocalServers;

    private static string $ftpDir;
    private static int $ftpPort;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$ftpDir = sys_get_temp_dir() . '/ias-delivery-test-' . getmypid();
        mkdir(self::$ftpDir, 0777, true);
        self::$ftpPort = self::startFtpServer(self::$ftpDir);
    }

    public static function tearDownAfterClass(): void
    {
        self::stopProcesses();
        parent::tearDownAfterClass();
    }

    protected function setUp(): void
    {
        parent::setUp();
        foreach (glob(self::$ftpDir . '/*') as $file) {
            unlink($file);
        }
    }

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
            'company' => ['summary' => 'Company Inc', 'address_line_1' => '1 Company street'],
        ], $override);
    }

    private function envFtp(array $override = []): void
    {
        config(['delivery.ftp' => $override + [
            'host' => '127.0.0.1',
            'port' => (string) self::$ftpPort,
            'ssl' => 'false',
            'passive' => 'true',
            'username' => 'user',
            'password' => 'pass',
            'path' => self::$ftpDir,
        ]]);
    }

    // --- per-request switch -------------------------------------------------

    public function testPerRequestDeliveryIsEnabledByDefault(): void
    {
        $this->assertTrue(config('delivery.per_request_enabled'));
    }

    #[DataProvider('switchValueProvider')]
    public function testPerRequestSwitchParsesEnvValue(?string $env, bool $expected): void
    {
        $previous = getenv('DELIVERY_PER_REQUEST_ENABLED');
        $env === null ? putenv('DELIVERY_PER_REQUEST_ENABLED') : putenv("DELIVERY_PER_REQUEST_ENABLED={$env}");

        try {
            $config = require base_path('config/delivery.php');
        } finally {
            $previous === false ? putenv('DELIVERY_PER_REQUEST_ENABLED') : putenv("DELIVERY_PER_REQUEST_ENABLED={$previous}");
        }

        $this->assertSame($expected, $config['per_request_enabled']);
    }

    public static function switchValueProvider(): array
    {
        return [
            'unset' => [null, true],
            'empty (as left in .env.dist)' => ['', true],
            'true' => ['true', true],
            '1' => ['1', true],
            'false' => ['false', false],
            '0' => ['0', false],
            'off' => ['off', false],
            'garbage fails closed' => ['maybe', false],
        ];
    }

    public function testDisabledSwitchRejectsEmailInBody(): void
    {
        Mail::fake();
        config(['delivery.per_request_enabled' => false]);

        $this->postJson('/api/invoice/generate', $this->payload(['email' => ['to' => 'john@customer.test']]))
            ->assertStatus(422)
            ->assertJsonStructure(['delivery']);

        Mail::assertNothingSent();
    }

    #[DataProvider('bodyChannelProvider')]
    public function testDisabledSwitchRejectsEveryChannelInBody(array $channel): void
    {
        config(['delivery.per_request_enabled' => false]);

        $this->postJson('/api/invoice/generate', $this->payload($channel))
            ->assertStatus(422)
            ->assertJsonStructure(['delivery']);
    }

    public static function bodyChannelProvider(): array
    {
        return [
            's3' => [['s3' => ['presigned_url' => 'https://bucket.example.com/a.pdf']]],
            'ftp' => [['ftp' => ['host' => 'ftp.example.com']]],
            'webhook' => [['webhook' => ['url' => 'https://hook.example.com']]],
            'zapier' => [['zapier' => ['zap_url' => 'https://hooks.zapier.com/hooks/catch/1/2']]],
            'email' => [['email' => ['to' => 'a@b.test']]],
            'non-array value' => [['ftp' => 'ftp.example.com']],
        ];
    }

    public function testDisabledSwitchRejectsBodyChannelEvenWhenEnvConfiguresIt(): void
    {
        $this->envFtp();
        config(['delivery.per_request_enabled' => false]);

        $this->postJson('/api/invoice/generate', $this->payload(['ftp' => ['host' => 'evil.example.com']]))
            ->assertStatus(422);

        $this->assertSame([], glob(self::$ftpDir . '/*'));
    }

    public function testDisabledSwitchAcceptsBodyWithOnlyNullChannelValues(): void
    {
        config(['delivery.per_request_enabled' => false]);

        $this->postJson('/api/invoice/generate', $this->payload(['s3' => ['presigned_url' => null]]))
            ->assertStatus(201)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function testDisabledSwitchStillRunsEnvConfiguredChannels(): void
    {
        Mail::fake();
        config(['delivery.per_request_enabled' => false, 'delivery.email.to' => 'ops@instance.test']);

        $this->postJson('/api/invoice/generate', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('email.sent', true)
            ->assertJsonPath('email.recipients', ['ops@instance.test']);

        Mail::assertSent(InvoiceMail::class, fn (InvoiceMail $mail) => $mail->hasTo('ops@instance.test'));
    }

    // --- env defaults -------------------------------------------------------

    public function testNothingIsDeliveredWithoutBodyOrEnv(): void
    {
        Mail::fake();

        $this->postJson('/api/invoice/generate', $this->payload())
            ->assertStatus(201)
            ->assertHeader('Content-Type', 'application/pdf');

        Mail::assertNothingSent();
    }

    public function testEnvEmailIsSentWithoutBodyOption(): void
    {
        Mail::fake();
        config(['delivery.email' => [
            'to' => 'ops@instance.test',
            'cc' => ' a@instance.test, ,b@instance.test ',
            'bcc' => 'archive@instance.test',
            'template' => 'default',
        ]]);

        $this->postJson('/api/invoice/generate', $this->payload())
            ->assertStatus(201)
            ->assertJsonPath('email.recipients', ['ops@instance.test', 'a@instance.test', 'b@instance.test', 'archive@instance.test']);
    }

    #[DataProvider('blankEnvProvider')]
    public function testBlankEnvValuesAreTreatedAsUnset(mixed $blank): void
    {
        Mail::fake();
        config(['delivery.email' => ['to' => $blank, 'cc' => $blank, 'bcc' => $blank, 'template' => $blank]]);
        config(['delivery.ftp.host' => $blank, 'delivery.webhook.url' => $blank, 'delivery.zapier.zap_url' => $blank]);

        $this->postJson('/api/invoice/generate', $this->payload())
            ->assertStatus(201)
            ->assertHeader('Content-Type', 'application/pdf');

        Mail::assertNothingSent();
    }

    public static function blankEnvProvider(): array
    {
        return ['empty string' => [''], 'null' => [null], 'spaces' => ['   ']];
    }

    public function testBodyFieldOverridesEnvAndOtherEnvFieldsSurvive(): void
    {
        Mail::fake();
        config(['delivery.email' => ['to' => 'ops@instance.test', 'cc' => 'cc@instance.test']]);

        $this->postJson('/api/invoice/generate', $this->payload(['email' => ['to' => 'john@customer.test']]))
            ->assertStatus(201)
            ->assertJsonPath('email.recipients', ['john@customer.test', 'cc@instance.test']);
    }

    public function testBodyListReplacesEnvListInsteadOfAppending(): void
    {
        Mail::fake();
        config(['delivery.email' => ['to' => 'ops@instance.test', 'cc' => 'cc@instance.test']]);

        $this->postJson('/api/invoice/generate', $this->payload(['email' => ['cc' => ['mine@customer.test']]]))
            ->assertStatus(201)
            ->assertJsonPath('email.recipients', ['ops@instance.test', 'mine@customer.test']);
    }

    public function testNullBodyValueDoesNotEraseEnvValue(): void
    {
        Mail::fake();
        config(['delivery.email.to' => 'ops@instance.test']);

        $this->postJson('/api/invoice/generate', $this->payload(['email' => ['to' => null]]))
            ->assertStatus(201)
            ->assertJsonPath('email.recipients', ['ops@instance.test']);
    }

    public function testEnvFtpUploadsWithoutBodyOption(): void
    {
        $this->envFtp();

        $response = $this->postJson('/api/invoice/generate', $this->payload());

        $response->assertStatus(201)->assertJsonPath('ftp.uploaded', true);
        $files = glob(self::$ftpDir . '/invoice_INV-42_*.pdf');
        $this->assertCount(1, $files);
        $this->assertStringStartsWith('%PDF-', file_get_contents($files[0]));
    }

    public function testBodyFtpPasswordOverridesEnvPassword(): void
    {
        $this->envFtp();

        $this->postJson('/api/invoice/generate', $this->payload(['ftp' => ['password' => 'wrong']]))
            ->assertStatus(201)
            ->assertJsonPath('ftp.uploaded', false);

        $this->assertSame([], glob(self::$ftpDir . '/*'));
    }

    public function testEnvValuesAreValidatedLikeBodyValues(): void
    {
        Mail::fake();
        config(['delivery.email.to' => 'not-an-email']);

        $this->postJson('/api/invoice/generate', $this->payload())
            ->assertStatus(422)
            ->assertJsonStructure(['email.to']);

        Mail::assertNothingSent();
    }

    public function testMalformedEnvWebhookHeadersFailLoudly(): void
    {
        config(['delivery.webhook' => ['url' => 'https://hook.example.com', 'headers' => '{not json']]);
        $this->withoutExceptionHandling();

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('DELIVERY_WEBHOOK_HEADERS');

        $this->postJson('/api/invoice/generate', $this->payload());
    }

    public function testEnvWebhookHeadersMustBeAJsonObject(): void
    {
        config(['delivery.webhook' => ['url' => 'https://hook.example.com', 'headers' => '"just a string"']]);
        $this->withoutExceptionHandling();

        $this->expectException(\UnexpectedValueException::class);

        $this->postJson('/api/invoice/generate', $this->payload());
    }
}
