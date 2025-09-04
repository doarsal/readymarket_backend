<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class MicrosoftAccountEmailService
{
    /**
     * Enviar credenciales de cuenta Microsoft por email
     */
    public function sendCredentials(array $accountData, string $password): bool
    {
        try {
            $fullName = trim($accountData['first_name'] . ' ' . $accountData['last_name']);
            $microsoftUrl = 'https://admin.microsoft.com/#/homepage';
            $adminEmail = 'admin@' . $accountData['domain_concatenated'];

            // Datos para la plantilla de correo
            $emailData = [
                'full_name' => $fullName,
                'microsoft_url' => $microsoftUrl,
                'admin_email' => $adminEmail,
                'password' => $password,
                'domain' => $accountData['domain_concatenated'],
                'organization' => $accountData['company_name'] ?? $fullName
            ];

            // Enviar correo utilizando la plantilla Blade
            Mail::send('emails.microsoft-credentials', $emailData, function ($message) use ($accountData, $fullName) {
                $message->to($accountData['email'])
                    ->subject($fullName . ' - Credenciales Microsoft');
            });

            Log::info('Microsoft Account: Credentials email sent', [
                'domain' => $accountData['domain_concatenated'],
                'recipient' => $accountData['email']
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Microsoft Account: Failed to send credentials email', [
                'error' => $e->getMessage(),
                'account_data' => $accountData
            ]);
            return false;
        }
    }
}
