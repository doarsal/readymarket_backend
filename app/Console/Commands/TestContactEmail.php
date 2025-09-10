<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Config;

class TestContactEmail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:contact-email';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test contact form email sending';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('=== TESTING CONTACT EMAIL ===');

        // Mostrar configuración actual
        $this->info('Mail Configuration:');
        $this->info('MAIL_MAILER: ' . config('mail.default'));
        $this->info('MAIL_HOST: ' . config('mail.mailers.smtp.host'));
        $this->info('MAIL_PORT: ' . config('mail.mailers.smtp.port'));
        $this->info('MAIL_USERNAME: ' . config('mail.mailers.smtp.username'));
        $this->info('MAIL_ENCRYPTION: ' . config('mail.mailers.smtp.encryption'));
        $this->info('MAIL_FROM_ADDRESS: ' . config('mail.from.address'));

        // Obtener destinatarios
        $notificationEmails = explode(',', env('CONTACT_FORM_NOTIFICATION_EMAIL', 'info@readymind.ms'));
        $notificationEmails = array_map('trim', $notificationEmails);
        $this->info('Recipients: ' . implode(', ', $notificationEmails));

        // Datos de prueba
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'subject' => 'Test Contact Form',
            'message' => 'This is a test message from the contact form.'
        ];

        try {
            $this->info('Attempting to send email...');

            Mail::to($notificationEmails)->send(new \App\Mail\ContactFormMail($data));

            $this->info('✅ Email sent successfully!');

        } catch (\Exception $e) {
            $this->error('❌ Email failed to send:');
            $this->error('Error: ' . $e->getMessage());
            $this->error('File: ' . $e->getFile() . ':' . $e->getLine());

            // Mostrar más detalles del error
            if ($e->getPrevious()) {
                $this->error('Previous error: ' . $e->getPrevious()->getMessage());
            }
        }

        return 0;
    }
}
