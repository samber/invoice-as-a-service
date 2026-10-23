<?php

namespace Tests\Feature;

use Tests\Support\LocalServers;
use Tests\TestCase;

/**
 * Posts full invoices to the API and checks the PDF reaches the FTP stand-in.
 *
 * HTTP uploads are covered by StorageTest: the API only accepts hosts that resolve in DNS
 * (`active_url`), which excludes the 127.0.0.1 stand-in.
 */
class InvoiceUploadTest extends TestCase
{
    use LocalServers;

    private static string $workDir;
    private static string $ftpDir;
    private static int $ftpPort;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$workDir = sys_get_temp_dir() . '/ias-upload-test-' . getmypid();
        self::$ftpDir = self::$workDir . '/ftp';
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

    public function testFtpUploadStoresPdfAndReturnsJson(): void
    {
        $payload = $this->payload(['ftp' => [
            'host' => '127.0.0.1',
            'port' => self::$ftpPort,
            'username' => 'user',
            'password' => 'pass',
            'path' => self::$ftpDir,
        ]]);

        $response = $this->postJson('/api/invoice/generate', $payload);

        $response->assertStatus(201)->assertJsonPath('ftp.uploaded', true);
        $files = glob(self::$ftpDir . '/invoice_INV-42_*.pdf');
        $this->assertCount(1, $files);
        $this->assertStringStartsWith('%PDF-', file_get_contents($files[0]));
        $this->assertSame($response->json('ftp.path'), $files[0]);
    }

    public function testFtpBadPasswordIsReportedWithoutFailingTheRequest(): void
    {
        $payload = $this->payload(['ftp' => [
            'host' => '127.0.0.1',
            'port' => self::$ftpPort,
            'username' => 'user',
            'password' => 'wrong',
            'path' => self::$ftpDir,
        ]]);

        $this->postJson('/api/invoice/generate', $payload)
            ->assertStatus(201)
            ->assertJsonPath('ftp.uploaded', false);
        $this->assertSame([], glob(self::$ftpDir . '/*'));
    }

    public function testFtpRequiresCredentialsWhenHostIsSet(): void
    {
        $this->postJson('/api/invoice/generate', $this->payload(['ftp' => ['host' => '127.0.0.1']]))
            ->assertStatus(422)
            ->assertJsonStructure(['ftp.username', 'ftp.password', 'ftp.path']);
    }
}
