<?php

/*
 * Router script for `php -S`, used by StorageTest as a stand-in for S3, a webhook and Zapier.
 *
 * Every request is appended as one JSON line to the file named by the CAPTURE_FILE
 * environment variable. The response status comes from the path: /status/<code>
 * answers with <code>, any other path answers 200.
 */

$files = [];
foreach ($_FILES as $name => $file) {
    $files[$name] = [
        'name' => $file['name'],
        'content' => file_get_contents($file['tmp_name']),
    ];
}

file_put_contents(getenv('CAPTURE_FILE'), json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'path' => parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH),
    'headers' => getallheaders(),
    'fields' => $_POST,
    // PHP only parses multipart bodies of POST requests: keep the raw body of other methods (S3 PUT).
    'body' => $_SERVER['REQUEST_METHOD'] === 'POST' ? null : file_get_contents('php://input'),
    'files' => $files,
]) . "\n", FILE_APPEND);

$status = preg_match('#^/status/(\d{3})$#', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), $m) ? (int) $m[1] : 200;
http_response_code($status);
echo "status {$status}";
