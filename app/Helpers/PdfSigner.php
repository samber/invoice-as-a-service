<?php

namespace App\Helpers;

use RuntimeException;

/**
 * Adds an invisible CMS digital signature (SubFilter adbe.pkcs7.detached) to a finished PDF.
 *
 * The signature is appended as a PDF incremental update: the original bytes are left untouched
 * and only the catalog and the first page are re-written (to reference the signature field).
 * It only supports what Dompdf emits: a single classic xref table and an inline page /Annots array.
 */
class PdfSigner
{
    // Room reserved for the hex-encoded CMS blob in /Contents. A signature of one certificate
    // is ~2 KB with RSA-4096 / ECDSA; the rest is headroom for long subject names.
    private const SIGNATURE_RESERVED_BYTES = 8192;

    // Width of each /ByteRange number. Fixed so the placeholder can be replaced in place
    // without shifting the offsets it describes.
    private const BYTE_RANGE_DIGITS = 10;

    // Annotation flags: Print (4) + Locked (128), as required for a signature widget.
    private const WIDGET_FLAGS = 132;

    // AcroForm /SigFlags: SignaturesExist (1) + AppendOnly (2), so readers warn on later edits.
    private const SIG_FLAGS = 3;

    private const FIELD_NAME = 'Signature1';

    private function __construct(
        private readonly string $certificate,
        private readonly mixed $privateKey,
        private readonly string $signerName,
    ) {
    }

    /**
     * Builds a signer from services.pdf_sign, or returns null when no key is configured.
     * A partial or invalid configuration throws: shipping an unsigned invoice silently is worse.
     */
    public static function fromConfig(): ?self
    {
        $config = config('services.pdf_sign');

        $cert = self::readPem($config['cert'] ?? null, $config['cert_file_path'] ?? null, 'PDF_SIGN_CERT');
        $key = self::readPem($config['key'] ?? null, $config['key_file_path'] ?? null, 'PDF_SIGN_KEY');

        if ($cert === null && $key === null) {
            return null;
        }
        if ($cert === null || $key === null) {
            throw new RuntimeException('PDF signing needs both a certificate (PDF_SIGN_CERT or PDF_SIGN_CERT_FILE_PATH) and a private key (PDF_SIGN_KEY or PDF_SIGN_KEY_FILE_PATH).');
        }

        $passphrase = $config['key_passphrase'] ?? '';
        // openssl_* functions raise warnings on bad input; Laravel turns those into ErrorException.
        $parsedCert = @openssl_x509_read($cert);
        if ($parsedCert === false) {
            throw new RuntimeException('PDF signing certificate is not a valid PEM X.509 certificate.');
        }
        $privateKey = @openssl_pkey_get_private($key, $passphrase);
        if ($privateKey === false) {
            throw new RuntimeException('PDF signing private key is not valid, or PDF_SIGN_KEY_PASSPHRASE is wrong.');
        }
        if (!openssl_x509_check_private_key($parsedCert, $privateKey)) {
            throw new RuntimeException('PDF signing private key does not match the certificate.');
        }

        $subject = openssl_x509_parse($parsedCert)['subject'] ?? [];
        $name = $subject['CN'] ?? '';

        return new self($cert, $privateKey, is_array($name) ? implode(', ', $name) : $name);
    }

    public function sign(string $pdf): string
    {
        $base = $this->parse($pdf);

        $sigId = $base['size'];
        $widgetId = $base['size'] + 1;

        $placeholderRange = '[0 ' . str_repeat('0', self::BYTE_RANGE_DIGITS) . ' ' . str_repeat('0', self::BYTE_RANGE_DIGITS) . ' ' . str_repeat('0', self::BYTE_RANGE_DIGITS) . ']';
        $placeholderContents = str_repeat('0', self::SIGNATURE_RESERVED_BYTES * 2);

        $sigDict = '<< /Type /Sig /Filter /Adobe.PPKLite /SubFilter /adbe.pkcs7.detached'
            . " /ByteRange $placeholderRange"
            . " /Contents <$placeholderContents>"
            . " /M (D:" . gmdate('YmdHis') . "+00'00')"
            . ($this->signerName !== '' ? ' /Name ' . self::pdfText($this->signerName) : '')
            . ' >>';
        $widgetDict = '<< /Type /Annot /Subtype /Widget /FT /Sig /T (' . self::FIELD_NAME . ')'
            . ' /F ' . self::WIDGET_FLAGS . ' /Rect [0 0 0 0]'
            . " /P {$base['pageId']} 0 R /V $sigId 0 R >>";

        $catalog = self::insertIntoDict(
            $base['catalogBody'],
            "/AcroForm << /Fields [$widgetId 0 R] /SigFlags " . self::SIG_FLAGS . ' >>',
        );
        $page = self::addAnnotation($base['pageBody'], $widgetId);

        // Objects are written in ascending number so the xref subsections stay contiguous.
        $objects = [
            $base['rootId'] => $catalog,
            $base['pageId'] => $page,
            $sigId => $sigDict,
            $widgetId => $widgetDict,
        ];
        ksort($objects);

        $out = $pdf . (str_ends_with($pdf, "\n") ? '' : "\n");
        $offsets = [];
        foreach ($objects as $id => $body) {
            $offsets[$id] = strlen($out);
            $out .= "$id 0 obj\n$body\nendobj\n";
        }

        $xrefOffset = strlen($out);
        $out .= $this->xrefSection($offsets);
        $out .= "trailer\n<< /Size " . ($widgetId + 1) . " /Root {$base['rootId']} 0 R"
            . ($base['info'] !== null ? " /Info {$base['info']}" : '')
            . ($base['id'] !== null ? " /ID {$base['id']}" : '')
            . " /Prev {$base['prev']} >>\nstartxref\n$xrefOffset\n%%EOF\n";

        return $this->embedSignature($out, $placeholderRange, $placeholderContents);
    }

    /**
     * Fills /ByteRange, signs everything but the /Contents hex string, then writes the signature in.
     */
    private function embedSignature(string $pdf, string $placeholderRange, string $placeholderContents): string
    {
        $contentsToken = '/Contents <' . $placeholderContents . '>';
        $tokenPos = strrpos($pdf, $contentsToken);
        $hexStart = $tokenPos + strlen('/Contents <');
        $hexEnd = $hexStart + strlen($placeholderContents);

        // Byte range covers the file minus "<hex>" itself (delimiters included).
        $first = $hexStart - 1;
        $secondStart = $hexEnd + 1;
        $range = sprintf(
            '[0 %0' . self::BYTE_RANGE_DIGITS . 'd %0' . self::BYTE_RANGE_DIGITS . 'd %0' . self::BYTE_RANGE_DIGITS . 'd]',
            $first,
            $secondStart,
            strlen($pdf) - $secondStart,
        );
        $rangePos = strrpos(substr($pdf, 0, $tokenPos), $placeholderRange);
        $pdf = substr_replace($pdf, $range, $rangePos, strlen($placeholderRange));

        $signed = substr($pdf, 0, $first) . substr($pdf, $secondStart);
        $hex = strtoupper(bin2hex($this->cms($signed)));
        if (strlen($hex) > strlen($placeholderContents)) {
            throw new RuntimeException('PDF signature is larger than the ' . self::SIGNATURE_RESERVED_BYTES . ' bytes reserved for it.');
        }

        return substr_replace($pdf, str_pad($hex, strlen($placeholderContents), '0'), $hexStart, strlen($placeholderContents));
    }

    /**
     * Detached CMS (DER) signature of $data. openssl only reads files, hence the temp files.
     */
    private function cms(string $data): string
    {
        $input = tempnam(sys_get_temp_dir(), 'pdfsig');
        $output = tempnam(sys_get_temp_dir(), 'pdfsig');
        try {
            if ($input === false || $output === false || file_put_contents($input, $data) === false) {
                throw new RuntimeException('Cannot write temporary files to sign the PDF.');
            }
            $ok = openssl_cms_sign(
                $input,
                $output,
                $this->certificate,
                $this->privateKey,
                [],
                OPENSSL_CMS_DETACHED | OPENSSL_CMS_BINARY,
                OPENSSL_ENCODING_DER,
            );
            $der = $ok ? file_get_contents($output) : false;
            if ($der === false || $der === '') {
                throw new RuntimeException('PDF signing failed: ' . openssl_error_string());
            }

            return $der;
        } finally {
            foreach ([$input, $output] as $file) {
                if (is_string($file) && file_exists($file)) {
                    unlink($file);
                }
            }
        }
    }

    /**
     * Reads the bits of the original file the incremental update must reference or re-write.
     */
    private function parse(string $pdf): array
    {
        if (!preg_match('/startxref\s+(\d+)\s+%%EOF\s*$/', $pdf, $m)) {
            throw new RuntimeException('Cannot sign PDF: startxref not found.');
        }
        $prev = (int) $m[1];

        $trailerPos = strrpos($pdf, 'trailer');
        if ($trailerPos === false || !preg_match('/trailer\s*<<(.*)>>\s*startxref/s', $pdf, $t, 0, $trailerPos)) {
            throw new RuntimeException('Cannot sign PDF: trailer not found (xref streams are not supported).');
        }
        $trailer = $t[1];
        if (str_contains($trailer, '/Prev') || !preg_match('/\/Size\s+(\d+)/', $trailer, $size) || !preg_match('/\/Root\s+(\d+)\s+0\s+R/', $trailer, $root)) {
            throw new RuntimeException('Cannot sign PDF: unsupported trailer.');
        }
        preg_match('/\/Info\s+(\d+\s+0\s+R)/', $trailer, $info);
        preg_match('/\/ID\s*(\[.*?\])/s', $trailer, $id);

        $offsets = self::readXref($pdf, $prev);
        $rootId = (int) $root[1];
        $catalogBody = self::objectBody($pdf, $offsets, $rootId);
        if (str_contains($catalogBody, '/AcroForm')) {
            throw new RuntimeException('Cannot sign PDF: it already has an AcroForm.');
        }

        if (!preg_match('/\/Pages\s+(\d+)\s+0\s+R/', $catalogBody, $pages)) {
            throw new RuntimeException('Cannot sign PDF: page tree not found.');
        }
        $pagesBody = self::objectBody($pdf, $offsets, (int) $pages[1]);
        if (!preg_match('/\/Kids\s*\[\s*(\d+)\s+0\s+R/', $pagesBody, $kid)) {
            throw new RuntimeException('Cannot sign PDF: first page not found.');
        }
        $pageId = (int) $kid[1];

        return [
            'prev' => $prev,
            'size' => (int) $size[1],
            'rootId' => $rootId,
            'info' => $info[1] ?? null,
            'id' => $id[1] ?? null,
            'catalogBody' => $catalogBody,
            'pageId' => $pageId,
            'pageBody' => self::objectBody($pdf, $offsets, $pageId),
        ];
    }

    /**
     * @return array<int,int> object number => byte offset, for in-use entries of a classic xref table
     */
    private static function readXref(string $pdf, int $offset): array
    {
        if (substr($pdf, $offset, 4) !== 'xref') {
            throw new RuntimeException('Cannot sign PDF: classic xref table expected.');
        }
        $pos = $offset + 4;
        $offsets = [];
        while (preg_match('/\G\s*(\d+)\s+(\d+)[ \t]*\r?\n/', $pdf, $m, 0, $pos)) {
            $pos += strlen($m[0]);
            for ($i = 0; $i < (int) $m[2]; $i++) {
                if (!preg_match('/\G(\d{10}) (\d{5}) ([nf])\s?\r?\n?/', $pdf, $e, 0, $pos)) {
                    throw new RuntimeException('Cannot sign PDF: malformed xref entry.');
                }
                $pos += 20;
                if ($e[3] === 'n') {
                    $offsets[(int) $m[1] + $i] = (int) $e[1];
                }
            }
        }

        return $offsets;
    }

    private static function objectBody(string $pdf, array $offsets, int $id): string
    {
        if (!isset($offsets[$id])) {
            throw new RuntimeException("Cannot sign PDF: object $id not found.");
        }
        $start = $offsets[$id];
        $end = strpos($pdf, 'endobj', $start);
        if ($end === false || !preg_match('/\G' . $id . '\s+0\s+obj\s*/', $pdf, $m, 0, $start)) {
            throw new RuntimeException("Cannot sign PDF: object $id is malformed.");
        }

        return rtrim(substr($pdf, $start + strlen($m[0]), $end - $start - strlen($m[0])));
    }

    private static function insertIntoDict(string $dict, string $entry): string
    {
        $close = strrpos($dict, '>>');
        if (!str_starts_with($dict, '<<') || $close === false) {
            throw new RuntimeException('Cannot sign PDF: object is not a dictionary.');
        }

        return substr($dict, 0, $close) . "\n$entry\n>>";
    }

    private static function addAnnotation(string $pageDict, int $widgetId): string
    {
        if (preg_match('/\/Annots\s+\d+\s+0\s+R/', $pageDict)) {
            throw new RuntimeException('Cannot sign PDF: indirect /Annots array is not supported.');
        }
        if (preg_match('/\/Annots\s*\[/', $pageDict)) {
            return preg_replace('/\/Annots\s*\[/', "/Annots [$widgetId 0 R ", $pageDict, 1);
        }

        return self::insertIntoDict($pageDict, "/Annots [$widgetId 0 R]");
    }

    /**
     * @param array<int,int> $offsets object number => offset, ascending
     */
    private function xrefSection(array $offsets): string
    {
        $out = "xref\n";
        $ids = array_keys($offsets);
        $i = 0;
        while ($i < count($ids)) {
            $j = $i;
            while ($j + 1 < count($ids) && $ids[$j + 1] === $ids[$j] + 1) {
                $j++;
            }
            $out .= $ids[$i] . ' ' . ($j - $i + 1) . "\n";
            for ($k = $i; $k <= $j; $k++) {
                // Each entry is exactly 20 bytes: offset, generation, flag, space + LF.
                $out .= sprintf("%010d 00000 n \n", $offsets[$ids[$k]]);
            }
            $i = $j + 1;
        }

        return $out;
    }

    // PDF text string: UTF-16BE with BOM, hex encoded (safe for any character).
    private static function pdfText(string $text): string
    {
        return '<' . strtoupper(bin2hex("\xFE\xFF" . mb_convert_encoding($text, 'UTF-16BE', 'UTF-8'))) . '>';
    }

    /**
     * Inline value wins over the file path. Literal "\n" sequences are expanded, since a PEM
     * stored in a single-line env var cannot hold real newlines.
     */
    private static function readPem(?string $inline, ?string $path, string $name): ?string
    {
        if ($inline !== null && $inline !== '') {
            return str_contains($inline, "\n") ? $inline : str_replace('\n', "\n", $inline);
        }
        if ($path === null || $path === '') {
            return null;
        }
        $content = is_file($path) && is_readable($path) ? file_get_contents($path) : false;
        if ($content === false || $content === '') {
            throw new RuntimeException("Cannot read $name file: $path");
        }

        return $content;
    }
}
