<?php

namespace App\Observers;

use App\Mail\AmexNewClientMail;
use App\Models\AmexNewClientForm;
use Config;
use Illuminate\Support\Facades\Mail;

class AmexNewClientFormObserver
{
    public function created(AmexNewClientForm $amexNewClientForm): void
    {
        $notificationEmails = Config::get('mail.notification_contacts.amex_new_client');
        $emails             = explode(',', $notificationEmails);

        Mail::to($emails)->send(new AmexNewClientMail($amexNewClientForm));
    }
}
