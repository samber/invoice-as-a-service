<?php

namespace App\Helpers;

use Log;
use GuzzleHttp;

class Storage
{

    private $local_path;

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
                $message = \GuzzleHttp\Psr7\str($err->getResponse());
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

}
