<?php

namespace App\Mail;

use App\Models\AmexNewClientForm;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AmexNewClientMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public AmexNewClientForm $amexNewClientForm;

    public function __construct(AmexNewClientForm $amexNewClientForm)
    {
        $this->amexNewClientForm = $amexNewClientForm;
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'ReadyMarket: Nuevo cliente Amex');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.amex-new-client',
            with: [
                'form' => $this->amexNewClientForm,
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
