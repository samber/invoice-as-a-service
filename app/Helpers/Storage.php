<?php

namespace App\Helpers;

use Log;
use GuzzleHttp;

use Illuminate\Support\Str;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemException;
use League\Flysystem\Ftp\FtpAdapter;
use League\Flysystem\Ftp\FtpConnectionOptions;

class Storage
{

    private $doc;

    public function __construct($doc)
    {
        $this->doc = $doc;
    }

    public function uploadS3($presigned_url)
    {
        Log::info("Uploading invoice to S3 => " . $presigned_url);

        try {
            $client = new GuzzleHttp\Client();
            $res = $client->request('PUT', $presigned_url, [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $this->doc,
                    ],
                ],
                'timeout' => config('services.s3.timeout'),
            ]);
        } catch (GuzzleHttp\Exception\RequestException $err) {
            $reason = NULL;
            $message = NULL;
            if ($err->hasResponse()) {
                $message = \GuzzleHttp\Psr7\Message::toString($err->getResponse());
                Log::error($message);
                $message = explode("\r\n", $message);
                $reason = $err->getResponse()->getReasonPhrase();
            }
            return [
                'uploaded' => false,
                'reason' => $reason,
                'message' => $message,
            ];
        }

        return [
            'uploaded' => true,
            'path' => preg_replace('/\?.*/', '', $presigned_url),
        ];
    }


    /**
     * [uploadFTP description]
     * @param  array $FTPcredentials The credentials for the FTP server
     * @return array                 Upload's result
     */
    public function uploadFTP($FTPcredentials, $fileName) {
        if (!Str::endsWith($FTPcredentials["path"], "/")) {
            $FTPcredentials["path"] = $FTPcredentials["path"] . "/";
        }

        $adapter = new FtpAdapter(FtpConnectionOptions::fromArray([
            'host' => $FTPcredentials["host"],
            'username' => $FTPcredentials["username"],
            'password' => $FTPcredentials["password"],
            'port' => isset($FTPcredentials["port"]) ? $FTPcredentials["port"] : 21,
            'root' => $FTPcredentials["path"],
            'passive' => isset($FTPcredentials["passive"]) ? $FTPcredentials["passive"] : true,
            'ssl' => isset($FTPcredentials["ssl"]) ? $FTPcredentials["ssl"] : false,
            'timeout' => 300
        ]));

        $filesystem = new Filesystem($adapter, ['visibility' => 'public']);

        // Flysystem connects lazily: connection and write errors both surface from write().
        try {
            $filesystem->write($fileName, $this->doc);
        } catch (FilesystemException $e) {
            return [
                'uploaded' => false,
                'message' => $e->getMessage(),
                'path' => null,
            ];
        }

        return [
            'uploaded' => true,
            'message' => null,
            'path' => $FTPcredentials["path"] . $fileName
        ];
    }

    public function uploadWebhook($webhookSettings, $fileName, $request) {
        $url = $webhookSettings['url'];
        $headers = $webhookSettings['headers'];
        $strRequest = json_encode($request);

        Log::info("Uploading invoice to webhook => " . $url);

        try {
            $client = new GuzzleHttp\Client();
            $res = $client->request('POST', $url, [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $this->doc,
                        'filename' => $fileName,
                    ],
                    [
                        'name' => 'request',
                        'contents' => $strRequest,
                    ],
                ],
                'headers' => $headers,
                'timeout' => 30,
            ]);
        } catch (GuzzleHttp\Exception\RequestException $err) {
            $reason = NULL;
            $message = NULL;
            if ($err->hasResponse()) {
                $message = \GuzzleHttp\Psr7\Message::toString($err->getResponse());
                Log::error($message);
                $message = explode("\r\n", $message);
                $reason = $err->getResponse()->getReasonPhrase();
            }
            return [
                'uploaded' => false,
                'reason' => $reason,
                'message' => $message,
            ];
        }

        return [
            'uploaded' => true,
            'path' => $url,
            'method' => 'POST',
        ];
    }

    public function uploadZapier($zapierSettings, $fileName, $request) {
        $url = $zapierSettings['zap_url'];
        $strRequest = json_encode($request);
        if (isset($zapierSettings['filename']))
            $fileName = $zapierSettings['filename'];

        Log::info("Uploading invoice to Zapier => " . $url);

        try {
            $client = new GuzzleHttp\Client();
            $res = $client->request('POST', $url, [
                'multipart' => [
                    [
                        'name' => 'file',
                        'contents' => $this->doc,
                        'filename' => $fileName,
                    ],
                    [
                        'name' => 'filename',
                        'contents' => $fileName,
                    ],
                    [
                        'name' => 'extension',
                        'contents' => 'pdf',
                    ],
                    [
                        'name' => 'request',
                        'contents' => $strRequest,
                    ],
                ],
              ' json' => [
                  [
                    'request' => $request,
                    'file' => $this->doc,
                    'filename' => $fileName,
                  ],
              ],
              'timeout' => 30,
            ]);
        } catch (GuzzleHttp\Exception\RequestException $err) {
            $reason = NULL;
            $message = NULL;
            if ($err->hasResponse()) {
                $message = \GuzzleHttp\Psr7\Message::toString($err->getResponse());
                Log::error($message);
                $message = explode("\r\n", $message);
                $reason = $err->getResponse()->getReasonPhrase();
            }
            return [
                'uploaded' => false,
                'reason' => $reason,
                'message' => $message,
            ];
        }

        return [
            'uploaded' => true,
            'path' => $url,
            'method' => 'POST',
        ];
    }

}
