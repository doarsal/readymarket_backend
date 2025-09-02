<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Illuminate\Support\Facades\Log;

class MicrosoftAccountEmailService
{
    /**
     * Enviar credenciales de cuenta Microsoft por email
     */
    public function sendCredentials(array $accountData, string $password): bool
    {
        try {
            $microsoftName = trim($accountData['first_name'] . ' ' . $accountData['last_name']);
            $microsoftURL = 'https://admin.microsoft.com/#/homepage';
            $microsoftUserAdmin = 'admin@' . $accountData['domain_concatenated'];

            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->isSMTP();
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            $mail->SMTPAutoTLS = false;
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Host = 'smtp.office365.com';
            $mail->Port = 587;
            $mail->Username = 'myreadymarket@readymind.ms';
            $mail->Password = 'Ready2024!';
            $mail->setFrom('myreadymarket@readymind.ms', 'ReadyMarket');
            $mail->addAddress($accountData['email']);
            $mail->isHTML(true);
            $mail->Subject = 'Datos de acceso a tu cuenta Microsoft';

            $mail->Body = "<!doctype html><html><head><meta charset='utf-8'><title>{$microsoftName}</title></head>
<body style='font-family:Lato'>
  <table bgcolor='#ffffff' width='100%' style='border-radius:10px;' border='0'>
    <tr><td align='left'><img height='50' src='https://simplesystems.mx/readymarketV3/assets/media/logos/logo_RMKT.png'></td>
        <td align='right'><img height='50' src='https://simplesystems.mx/readymarketV4/assets/media/logos/Microsoft_logo.png'></td></tr>
    <tr><td colspan='2'>
      <p>Hola {$microsoftName},</p>
      <p><strong>Estos son los datos de acceso a tu cuenta OnMicrosoft:</strong></p>
      <p>
        <strong>URL:</strong> {$microsoftURL}<br>
        <strong>Usuario:</strong> {$microsoftUserAdmin}<br>
        <strong>Contraseña:</strong> {$password}
      </p>
      <p><em>ReadyMarket no genera ni almacena estos datos.</em></p>
    </td></tr>
  </table>
  <div style='text-align:center;background:#d4d4d4;padding:30px;border:1px solid #ccc;'>
    <a href='https://simplesystems.mx/readymarketV4/mx/'><strong>ReadyMarket©</strong></a> |
    <a href='https://simplesystems.mx/readymarketV4/mx/aviso-de-privacidad'><strong>Aviso de privacidad</strong></a>
  </div>
</body></html>";

            $mail->send();

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
