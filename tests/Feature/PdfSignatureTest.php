<?php

namespace Tests\Feature;

use App\Helpers\PdfSigner;
use RuntimeException;
use Tests\TestCase;

class PdfSignatureTest extends TestCase
{
    private const PASSPHRASE = 's3cret';

    private string $dir;
    private string $certPem;
    private string $keyPem;
    private string $encryptedKeyPem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir() . '/pdfsig-test-' . bin2hex(random_bytes(4));
        mkdir($this->dir, 0700);

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        $csr = openssl_csr_new(['commonName' => 'Test Company Ünï'], $key);
        $cert = openssl_csr_sign($csr, null, $key, 1);
        // Export through locals: by-reference output cannot target uninitialized typed properties.
        openssl_x509_export($cert, $certPem);
        openssl_pkey_export($key, $keyPem);
        openssl_pkey_export($key, $encryptedKeyPem, self::PASSPHRASE);
        $this->certPem = $certPem;
        $this->keyPem = $keyPem;
        $this->encryptedKeyPem = $encryptedKeyPem;

        $this->configure();
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->dir);
        parent::tearDown();
    }

    private function configure(array $override = []): void
    {
        config(['services.pdf_sign' => array_replace([
            'cert' => null,
            'cert_file_path' => null,
            'key' => null,
            'key_file_path' => null,
            'key_passphrase' => null,
        ], $override)]);
    }

    private function writeFile(string $name, string $content): string
    {
        $path = $this->dir . '/' . $name;
        file_put_contents($path, $content);

        return $path;
    }

    // Info dictionary strings are stored as UTF-16BE with a BOM.
    private function pdfInfoString(string $label, string $value): string
    {
        return "/$label (\xFE\xFF" . mb_convert_encoding($value, 'UTF-16BE', 'UTF-8') . ')';
    }

    private function generate(): string
    {
        $response = $this->postJson('/api/invoice/generate', [
            'id' => 'INV-1',
            'currency' => 'EUR',
            'lang' => 'en',
            'date' => 1700000000,
            'due_date' => 1702592000,
            'items' => [['title' => 'Consulting', 'price' => 100, 'quantity' => 2, 'tax' => 20]],
            'customer' => ['summary' => 'Customer Inc', 'address_line_1' => '1 Customer street'],
            'company' => ['summary' => 'Company Inc', 'address_line_1' => '1 Company street'],
        ]);
        $response->assertStatus(201);

        return $response->getContent();
    }

    /**
     * /Contents is zero padded: keep only the outer DER SEQUENCE (tag, long-form length, body).
     */
    private function trimDer(string $padded): string
    {
        $this->assertSame("\x30", $padded[0]);
        $lengthBytes = ord($padded[1]) & 0x7F;
        $this->assertTrue((ord($padded[1]) & 0x80) !== 0 && $lengthBytes > 0, 'expected DER long-form length');
        $length = 0;
        for ($i = 0; $i < $lengthBytes; $i++) {
            $length = ($length << 8) | ord($padded[2 + $i]);
        }

        return substr($padded, 0, 2 + $lengthBytes + $length);
    }

    /**
     * Checks the CMS signature against the bytes covered by /ByteRange, like a PDF reader does.
     * Chain validation is skipped: the test certificate is self-signed.
     */
    private function signatureIsValid(string $pdf): bool
    {
        $this->assertSame(1, preg_match('/\/ByteRange \[(\d+) (\d+) (\d+) (\d+)\]/', $pdf, $r), 'no /ByteRange');
        [, $a, $b, $c, $d] = array_map('intval', $r);

        $this->assertSame(0, $a);
        $this->assertSame(strlen($pdf), $c + $d, 'byte range must end at EOF');
        $this->assertSame('<', $pdf[$b], '/Contents must start right after first range');
        $this->assertSame('>', $pdf[$c - 1], '/Contents must end right before second range');

        $hex = substr($pdf, $b + 1, $c - $b - 2);
        $der = $this->trimDer(hex2bin($hex));

        $signed = $this->writeFile('signed.bin', substr($pdf, 0, $b) . substr($pdf, $c));
        $sig = $this->writeFile('sig.der', $der);

        // PHP's openssl_cms_verify() cannot check a detached DER signature, so use the CLI.
        exec(sprintf(
            'openssl cms -verify -inform DER -in %s -content %s -binary -noverify -out /dev/null 2>&1',
            escapeshellarg($sig),
            escapeshellarg($signed),
        ), $output, $status);

        return $status === 0;
    }

    public function testNoKeyConfiguredLeavesPdfUnsigned(): void
    {
        $this->assertNull(PdfSigner::fromConfig());

        $pdf = $this->generate();
        $this->assertStringNotContainsString('/ByteRange', $pdf);
    }

    public function testSignsWithInlineCertAndKey(): void
    {
        $this->configure(['cert' => $this->certPem, 'key' => $this->keyPem]);

        $pdf = $this->generate();

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertStringContainsString('/SubFilter /adbe.pkcs7.detached', $pdf);
        $this->assertStringContainsString('/SigFlags 3', $pdf);
        $this->assertTrue($this->signatureIsValid($pdf));
    }

    public function testSignsWithFilePaths(): void
    {
        $this->configure([
            'cert_file_path' => $this->writeFile('c.pem', $this->certPem),
            'key_file_path' => $this->writeFile('k.pem', $this->keyPem),
        ]);

        $this->assertTrue($this->signatureIsValid($this->generate()));
    }

    public function testSignsWithPassphraseProtectedKey(): void
    {
        $this->configure([
            'cert' => $this->certPem,
            'key' => $this->encryptedKeyPem,
            'key_passphrase' => self::PASSPHRASE,
        ]);

        $this->assertTrue($this->signatureIsValid($this->generate()));
    }

    public function testInlineValueWinsOverFilePath(): void
    {
        $this->configure([
            'cert' => $this->certPem,
            'key' => $this->keyPem,
            'cert_file_path' => $this->dir . '/missing.pem',
            'key_file_path' => $this->dir . '/missing.pem',
        ]);

        $this->assertTrue($this->signatureIsValid($this->generate()));
    }

    public function testInlinePemWithEscapedNewlines(): void
    {
        $this->configure([
            'cert' => str_replace("\n", '\n', trim($this->certPem)),
            'key' => str_replace("\n", '\n', trim($this->keyPem)),
        ]);

        $this->assertTrue($this->signatureIsValid($this->generate()));
    }

    public function testOriginalContentAndMetadataArePreserved(): void
    {
        $unsigned = $this->generate();
        $this->configure(['cert' => $this->certPem, 'key' => $this->keyPem]);
        $signed = $this->generate();

        // Incremental update: the metadata of the original document stays in the signed file.
        $this->assertStringContainsString($this->pdfInfoString('Author', 'Company Inc'), $signed);
        $this->assertStringContainsString($this->pdfInfoString('Producer', 'invoice-as-a-service'), $signed);
        $this->assertGreaterThan(strlen($unsigned), strlen($signed));
        $this->assertSame(2, substr_count($signed, '%%EOF'));
    }

    public function testSignsAnyPdfWithAnnotations(): void
    {
        // Payment link renders as a page /Annots entry: it must be kept next to the widget.
        $this->configure(['cert' => $this->certPem, 'key' => $this->keyPem]);
        $response = $this->postJson('/api/invoice/generate', [
            'id' => 'INV-2',
            'currency' => 'EUR',
            'lang' => 'en',
            'date' => 1700000000,
            'due_date' => 1702592000,
            'payment_link' => 'https://example.com/pay',
            'items' => [['title' => 'Consulting', 'price' => 100, 'quantity' => 1, 'tax' => 0]],
            'customer' => ['summary' => 'Customer Inc', 'address_line_1' => '1 Customer street'],
            'company' => ['summary' => 'Company Inc', 'address_line_1' => '1 Company street'],
        ]);
        $response->assertStatus(201);
        $pdf = $response->getContent();

        $this->assertStringContainsString('/Subtype /Link', $pdf);
        $this->assertSame(1, preg_match('/\/Annots \[(\d+) 0 R\s+(\d+) 0 R/', $pdf));
        $this->assertTrue($this->signatureIsValid($pdf));
    }

    public function testTamperedContentFailsVerification(): void
    {
        $this->configure(['cert' => $this->certPem, 'key' => $this->keyPem]);
        $pdf = $this->generate();

        // Flip one byte of the PDF version in the header, inside the first signed range.
        $this->assertSame('7', $pdf[7]);
        $tampered = substr_replace($pdf, '6', 7, 1);
        $this->assertTrue($this->signatureIsValid($pdf), 'control: untouched PDF must verify');

        $this->assertFalse($this->signatureIsValid($tampered));
    }

    public function testCertWithoutKeyThrows(): void
    {
        $this->configure(['cert' => $this->certPem]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('needs both');
        PdfSigner::fromConfig();
    }

    public function testKeyWithoutCertThrows(): void
    {
        $this->configure(['key' => $this->keyPem]);

        $this->expectException(RuntimeException::class);
        PdfSigner::fromConfig();
    }

    public function testMissingKeyFileThrows(): void
    {
        $this->configure(['cert' => $this->certPem, 'key_file_path' => $this->dir . '/nope.pem']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Cannot read');
        PdfSigner::fromConfig();
    }

    public function testEmptyKeyFileThrows(): void
    {
        $this->configure(['cert' => $this->certPem, 'key_file_path' => $this->writeFile('empty.pem', '')]);

        $this->expectException(RuntimeException::class);
        PdfSigner::fromConfig();
    }

    public function testWrongPassphraseThrows(): void
    {
        $this->configure(['cert' => $this->certPem, 'key' => $this->encryptedKeyPem, 'key_passphrase' => 'wrong']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('PDF_SIGN_KEY_PASSPHRASE');
        PdfSigner::fromConfig();
    }

    public function testMissingPassphraseThrows(): void
    {
        $this->configure(['cert' => $this->certPem, 'key' => $this->encryptedKeyPem]);

        $this->expectException(RuntimeException::class);
        PdfSigner::fromConfig();
    }

    public function testGarbageCertThrows(): void
    {
        $this->configure(['cert' => 'not a cert', 'key' => $this->keyPem]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('certificate');
        PdfSigner::fromConfig();
    }

    public function testKeyNotMatchingCertThrows(): void
    {
        $other = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($other, $otherPem);
        $this->configure(['cert' => $this->certPem, 'key' => $otherPem]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('does not match');
        PdfSigner::fromConfig();
    }

    public function testSignRejectsNonPdf(): void
    {
        $this->configure(['cert' => $this->certPem, 'key' => $this->keyPem]);

        $this->expectException(RuntimeException::class);
        PdfSigner::fromConfig()->sign('hello');
    }

    public function testSignRejectsEmptyInput(): void
    {
        $this->configure(['cert' => $this->certPem, 'key' => $this->keyPem]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('startxref not found');
        PdfSigner::fromConfig()->sign('');
    }

    public function testSignRejectsPdfWithoutClassicTrailer(): void
    {
        $this->configure(['cert' => $this->certPem, 'key' => $this->keyPem]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('trailer not found');
        PdfSigner::fromConfig()->sign("%PDF-1.5\nstartxref\n9\n%%EOF\n");
    }

    public function testSignRejectsStartxrefPointingAwayFromXrefTable(): void
    {
        $unsigned = $this->generate();
        $this->configure(['cert' => $this->certPem, 'key' => $this->keyPem]);
        $pdf = preg_replace('/startxref\s+\d+/', "startxref\n0", $unsigned);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('classic xref table expected');
        PdfSigner::fromConfig()->sign($pdf);
    }

    public function testSignRejectsAlreadySignedPdf(): void
    {
        $this->configure(['cert' => $this->certPem, 'key' => $this->keyPem]);
        $signed = $this->generate();
        $this->assertTrue($this->signatureIsValid($signed), 'control: first signature must be valid');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('unsupported trailer');
        PdfSigner::fromConfig()->sign($signed);
    }

    public function testSignedPdfNamesTheCertificateSubject(): void
    {
        $this->configure(['cert' => $this->certPem, 'key' => $this->keyPem]);
        $pdf = $this->generate();

        $this->assertStringContainsString('/Name <' . strtoupper(bin2hex("\xFE\xFF" . mb_convert_encoding('Test Company Ünï', 'UTF-16BE', 'UTF-8'))) . '>', $pdf);
    }

    public function testEmptyInlineValueFallsBackToFilePath(): void
    {
        $this->configure([
            'cert' => '',
            'key' => '',
            'cert_file_path' => $this->writeFile('cert.pem', $this->certPem),
            'key_file_path' => $this->writeFile('key.pem', $this->keyPem),
        ]);

        $this->assertTrue($this->signatureIsValid($this->generate()));
    }

    public function testEmptyConfigValuesLeavePdfUnsigned(): void
    {
        $this->configure(['cert' => '', 'key' => '', 'cert_file_path' => '', 'key_file_path' => '']);

        $this->assertNull(PdfSigner::fromConfig());
        $this->assertStringNotContainsString('/adbe.pkcs7.detached', $this->generate());
    }

    public function testGenerateFailsWhenSigningIsMisconfigured(): void
    {
        // A half-configured signer must not silently ship an unsigned invoice.
        $this->configure(['cert' => $this->certPem]);

        $this->postJson('/api/invoice/generate', [
            'id' => 'INV-1',
            'currency' => 'EUR',
            'lang' => 'en',
            'date' => 1700000000,
            'due_date' => 1702592000,
            'items' => [['title' => 'Consulting', 'price' => 100, 'quantity' => 1, 'tax' => 0]],
            'customer' => ['summary' => 'Customer Inc', 'address_line_1' => '1 Customer street'],
            'company' => ['summary' => 'Company Inc', 'address_line_1' => '1 Company street'],
        ])->assertStatus(500);
    }
}
