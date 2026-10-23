<?php

namespace App\Helpers;

use Log;
use App\Mail\InvoiceMail;
use Illuminate\Support\Facades\Mail;

class Email
{
    private $doc;

    public function __construct($doc)
    {
        $this->doc = $doc;
    }

    /**
     * Sends the PDF through the SMTP server configured in the MAIL_* environment variables.
     *
     * @param  array       $email         Validated `email` request object
     * @param  string      $fileName      Attachment name
     * @param  string      $defaultSubject
     * @param  string|null $replyTo
     * @param  array       $view          Variables for the default template
     * @return array                      Delivery result
     */
    public function send($email, $fileName, $defaultSubject, $replyTo, $view)
    {
        Log::info("Sending invoice by email => " . $email['to']);

        $recipients = array_merge([$email['to']], $email['cc'] ?? [], $email['bcc'] ?? []);

        try {
            Mail::send(new InvoiceMail($this->doc, $email, $fileName, $defaultSubject, $replyTo, $view));
        } catch (\Throwable $e) {
            // Mailer failures (connection, auth, rejected recipient) must not fail the whole request:
            // the PDF is still generated and other deliveries may have succeeded.
            Log::error("Invoice email failed: " . $e->getMessage());
            return [
                'sent' => false,
                'message' => $e->getMessage(),
                'recipients' => $recipients,
            ];
        }

        return [
            'sent' => true,
            'message' => null,
            'recipients' => $recipients,
        ];
    }
}
