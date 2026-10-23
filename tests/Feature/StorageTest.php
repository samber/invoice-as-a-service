<?php

namespace Tests\Feature;

use App\Helpers\Storage;
use Tests\Support\LocalServers;
use Tests\TestCase;

/**
 * Runs the upload helpers against local stand-ins for S3, a webhook, Zapier and an FTP server.
 */
class StorageTest extends TestCase
{
    use LocalServers;

    private const PDF = "%PDF-1.4 fake document\n";

    private static string $workDir;
    private static string $captureFile;
    private static string $baseUrl;
    private static string $ftpDir;
    private static int $ftpPort;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$workDir = sys_get_temp_dir() . '/ias-storage-test-' . getmypid();
        self::$ftpDir = self::$workDir . '/ftp';
        mkdir(self::$ftpDir, 0777, true);
        self::$captureFile = self::$workDir . '/requests.jsonl';

        self::$baseUrl = self::startHttpServer(self::$captureFile);
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
        file_put_contents(self::$captureFile, '');
        foreach (glob(self::$ftpDir . '/*') as $file) {
            unlink($file);
        }
    }

    /** @return array[] requests received by the HTTP stand-in since setUp */
    private function captured(): array
    {
        $lines = array_filter(explode("\n", file_get_contents(self::$captureFile)));

        return array_map(fn ($line) => json_decode($line, true), array_values($lines));
    }

    private function ftpCredentials(array $override = []): array
    {
        return $override + [
            'host' => '127.0.0.1',
            'port' => self::$ftpPort,
            'username' => 'user',
            'password' => 'pass',
            'path' => self::$ftpDir,
        ];
    }

    // S3

    public function testS3UploadsPdfWithPut(): void
    {
        $url = self::$baseUrl . '/bucket/key.pdf?X-Amz-Signature=secret';

        $result = (new Storage(self::PDF))->uploadS3($url);

        $this->assertTrue($result['uploaded']);
        // The signature must not leak back into the response.
        $this->assertSame(self::$baseUrl . '/bucket/key.pdf', $result['path']);
        $requests = $this->captured();
        $this->assertCount(1, $requests);
        $this->assertSame('PUT', $requests[0]['method']);
        $this->assertSame('/bucket/key.pdf', $requests[0]['path']);
        $this->assertStringContainsString(self::PDF, $requests[0]['body']);
    }

    public function testS3ReportsRejection(): void
    {
        $result = (new Storage(self::PDF))->uploadS3(self::$baseUrl . '/status/403');

        $this->assertFalse($result['uploaded']);
        $this->assertSame('Forbidden', $result['reason']);
        $this->assertContains('status 403', $result['message']);
    }

    public function testS3ReportsServerError(): void
    {
        $result = (new Storage(self::PDF))->uploadS3(self::$baseUrl . '/status/500');

        $this->assertFalse($result['uploaded']);
        $this->assertSame('Internal Server Error', $result['reason']);
    }

    // Webhook

    public function testWebhookPostsFileRequestAndHeaders(): void
    {
        $result = (new Storage(self::PDF))->uploadWebhook(
            ['url' => self::$baseUrl . '/hook', 'headers' => ['X-Token' => 'abc']],
            'invoice.pdf',
            ['id' => 'INV-1'],
        );

        $this->assertSame(['uploaded' => true, 'path' => self::$baseUrl . '/hook', 'method' => 'POST'], $result);
        $request = $this->captured()[0];
        $this->assertSame('POST', $request['method']);
        $this->assertSame('abc', $request['headers']['X-Token']);
        $this->assertSame('invoice.pdf', $request['files']['file']['name']);
        $this->assertSame(self::PDF, $request['files']['file']['content']);
        $this->assertSame(['id' => 'INV-1'], json_decode($request['fields']['request'], true));
    }

    public function testWebhookReportsRejection(): void
    {
        $result = (new Storage(self::PDF))->uploadWebhook(
            ['url' => self::$baseUrl . '/status/401', 'headers' => []],
            'invoice.pdf',
            ['id' => 'INV-1'],
        );

        $this->assertFalse($result['uploaded']);
        $this->assertSame('Unauthorized', $result['reason']);
    }

    // Zapier

    public function testZapierPostsFileAndMetadata(): void
    {
        $result = (new Storage(self::PDF))->uploadZapier(
            ['zap_url' => self::$baseUrl . '/zap'],
            'invoice.pdf',
            ['id' => 'INV-1'],
        );

        $this->assertTrue($result['uploaded']);
        $request = $this->captured()[0];
        $this->assertSame('invoice.pdf', $request['files']['file']['name']);
        $this->assertSame('invoice.pdf', $request['fields']['filename']);
        $this->assertSame('pdf', $request['fields']['extension']);
        $this->assertSame(['id' => 'INV-1'], json_decode($request['fields']['request'], true));
    }

    public function testZapierCustomFilenameOverridesDefault(): void
    {
        (new Storage(self::PDF))->uploadZapier(
            ['zap_url' => self::$baseUrl . '/zap', 'filename' => 'custom.pdf'],
            'invoice.pdf',
            ['id' => 'INV-1'],
        );

        $request = $this->captured()[0];
        $this->assertSame('custom.pdf', $request['files']['file']['name']);
        $this->assertSame('custom.pdf', $request['fields']['filename']);
    }

    public function testZapierReportsServerError(): void
    {
        $result = (new Storage(self::PDF))->uploadZapier(
            ['zap_url' => self::$baseUrl . '/status/502'],
            'invoice.pdf',
            ['id' => 'INV-1'],
        );

        $this->assertFalse($result['uploaded']);
        $this->assertSame('Bad Gateway', $result['reason']);
    }

    // FTP

    public function testFtpUploadsPdf(): void
    {
        $result = (new Storage(self::PDF))->uploadFTP($this->ftpCredentials(), 'invoice.pdf');

        $this->assertTrue($result['uploaded'], (string) $result['message']);
        $this->assertNull($result['message']);
        $this->assertSame(self::$ftpDir . '/invoice.pdf', $result['path']);
        $this->assertSame(self::PDF, file_get_contents(self::$ftpDir . '/invoice.pdf'));
    }

    public function testFtpKeepsTrailingSlashOfPath(): void
    {
        $result = (new Storage(self::PDF))->uploadFTP($this->ftpCredentials(['path' => self::$ftpDir . '/']), 'invoice.pdf');

        $this->assertTrue($result['uploaded'], (string) $result['message']);
        $this->assertSame(self::$ftpDir . '/invoice.pdf', $result['path']);
    }

    public function testFtpReportsBadCredentials(): void
    {
        $result = (new Storage(self::PDF))->uploadFTP($this->ftpCredentials(['password' => 'wrong']), 'invoice.pdf');

        $this->assertFalse($result['uploaded']);
        $this->assertNull($result['path']);
        $this->assertFileDoesNotExist(self::$ftpDir . '/invoice.pdf');
    }

    public function testFtpReportsUnreachableHost(): void
    {
        $result = (new Storage(self::PDF))->uploadFTP($this->ftpCredentials(['port' => 1]), 'invoice.pdf');

        $this->assertFalse($result['uploaded']);
        $this->assertNull($result['path']);
    }
}
