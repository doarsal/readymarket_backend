<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use App\Models\ContactMessage;
use App\Mail\ContactFormMail;

class ContactController extends Controller
{
    /**
     * Enviar mensaje de contacto
     */
    public function sendMessage(Request $request)
    {
        try {
            // Validar los datos del formulario
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|max:255',
                'subject' => 'required|string|max:255',
                'message' => 'required|string|max:5000',
            ], [
                'name.required' => 'El nombre completo es obligatorio.',
                'name.max' => 'El nombre no puede exceder 255 caracteres.',
                'email.required' => 'El correo electrónico es obligatorio.',
                'email.email' => 'El correo electrónico debe tener un formato válido.',
                'email.max' => 'El correo electrónico no puede exceder 255 caracteres.',
                'subject.required' => 'El asunto es obligatorio.',
                'subject.max' => 'El asunto no puede exceder 255 caracteres.',
                'message.required' => 'El mensaje es obligatorio.',
                'message.max' => 'El mensaje no puede exceder 5000 caracteres.',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Error en la validación de datos.',
                    'errors' => $validator->errors()
                ], 422);
            }

            $data = $validator->validated();

            // Guardar el mensaje en la base de datos
            $contactMessage = ContactMessage::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'subject' => $data['subject'],
                'message' => $data['message'],
                'metadata' => [
                    'ip_address' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'created_from' => 'contact_form',
                    'timestamp' => now()->toISOString(),
                ]
            ]);

            // Obtener emails de destinatarios desde .env
            $notificationEmails = explode(',', env('CONTACT_FORM_NOTIFICATION_EMAIL', 'info@readymind.ms'));
            $notificationEmails = array_map('trim', $notificationEmails);

            // Intentar enviar email (pero no fallar si no se puede)
            try {
                Mail::to($notificationEmails)->send(new ContactFormMail($data));

                Log::info('Mensaje de contacto enviado por email', [
                    'contact_message_id' => $contactMessage->id,
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'subject' => $data['subject'],
                    'sent_to' => $notificationEmails
                ]);
            } catch (\Exception $mailException) {
                // Log del error del email pero no fallar el proceso
                Log::warning('Error al enviar email de contacto (mensaje guardado en BD)', [
                    'contact_message_id' => $contactMessage->id,
                    'error' => $mailException->getMessage(),
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'subject' => $data['subject']
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Tu mensaje ha sido enviado exitosamente. Te contactaremos pronto.',
                'data' => [
                    'message_id' => $contactMessage->id
                ]
            ], 200);

        } catch (\Exception $e) {
            Log::error('Error al procesar mensaje de contacto', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Error interno del servidor. Por favor, intenta nuevamente.'
            ], 500);
        }
    }
}
