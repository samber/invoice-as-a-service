<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class InvoiceMail extends Mailable
{
    /**
     * @param string      $doc      Raw PDF bytes
     * @param array       $email    Validated `email` request object
     * @param string|null $replyTo  Company email, so replies reach the invoice issuer
     * @param array       $templateData    Variables for the default template
     */
    public function __construct(
        private string $doc,
        private array $email,
        private string $fileName,
        private string $defaultSubject,
        private ?string $replyAddress,
        private array $templateData,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            to: [$this->email['to']],
            cc: $this->email['cc'] ?? [],
            bcc: $this->email['bcc'] ?? [],
            replyTo: $this->replyAddress ? [$this->replyAddress] : [],
            subject: $this->defaultSubject,
        );
    }

    public function content(): Content
    {
        // Template names are whitelisted by the request validation (`email.template`).
        $template = $this->email['template'] ?? 'default';

        return new Content(view: 'emails.' . $template, with: $this->templateData);
    }

    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->doc, $this->fileName)->withMime('application/pdf'),
        ];
    }
}
