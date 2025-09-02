<?php

namespace App\Jobs;

use App\Models\MicrosoftAccount;
use App\Services\MicrosoftPartnerCenterService;
use App\Services\MicrosoftAccountEmailService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ProcessPendingMicrosoftAccountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private MicrosoftAccount $account;

    public function __construct(MicrosoftAccount $account)
    {
        $this->account = $account;
    }

    public function handle(
        MicrosoftPartnerCenterService $partnerCenterService,
        MicrosoftAccountEmailService $emailService
    ): void
    {
        try {
            // Verificar que la cuenta sigue pendiente
            $this->account->refresh();
            if (!$this->account->is_pending) {
                Log::info('Microsoft Account: Account no longer pending', [
                    'account_id' => $this->account->id
                ]);
                return;
            }

            Log::info('Microsoft Account: Processing pending account', [
                'account_id' => $this->account->id,
                'domain' => $this->account->domain_concatenated
            ]);

            // Preparar datos para Partner Center
            $customerData = [
                'domain_concatenated' => $this->account->domain_concatenated,
                'culture' => $this->account->culture,
                'email' => $this->account->email,
                'language_code' => $this->account->language_code,
                'organization' => $this->account->organization,
                'first_name' => $this->account->first_name,
                'last_name' => $this->account->last_name,
                'address' => $this->account->address,
                'city' => $this->account->city,
                'state_code' => $this->account->state_code,
                'postal_code' => $this->account->postal_code,
                'country_code' => $this->account->country_code,
                'phone' => $this->account->phone,
            ];

            // Intentar crear en Partner Center
            $customerResult = $partnerCenterService->createCustomer($customerData);

            // Actualizar cuenta con Microsoft ID
            $this->account->update([
                'microsoft_id' => $customerResult['microsoft_id'],
                'is_pending' => false,
                'is_active' => true,
            ]);

            // Aceptar acuerdo de Microsoft
            $partnerCenterService->acceptCustomerAgreement(
                $customerResult['microsoft_id'],
                $customerData
            );

            // Enviar credenciales por email si hay contraseña
            if (!empty($customerResult['password'])) {
                $emailService->sendCredentials(
                    $customerData,
                    $customerResult['password']
                );
            }

            // Actualizar progreso del usuario
            DB::table('users')
              ->where('id', $this->account->user_id)
              ->update(['user_progress_accountonmicrosoft' => 1]);

            // Log de actividad
            DB::table('users_logs')->insert([
                'log_idactivity' => 2,
                'log_iduser' => $this->account->user_id,
                'log_idconfig' => $this->account->configuration_id,
                'log_idstore' => $this->account->store_id,
                'log_mod' => 'microsoft_accounts',
                'log_title' => $this->account->organization . ' - ' . $this->account->domain_concatenated,
                'log_date' => now()->format('Y-m-d'),
                'log_time' => now()->format('H:i:s'),
                'log_id' => (string)$this->account->id,
                'created_at' => now(),
            ]);

            Log::info('Microsoft Account: Successfully processed pending account', [
                'account_id' => $this->account->id,
                'microsoft_id' => $customerResult['microsoft_id']
            ]);

        } catch (\Exception $e) {
            Log::error('Microsoft Account: Failed to process pending account', [
                'account_id' => $this->account->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw para que el job falle y se reintente
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Microsoft Account: Job failed permanently', [
            'account_id' => $this->account->id,
            'error' => $exception->getMessage()
        ]);

        // Opcionalmente, marcar la cuenta con un estado de error
        // o enviar notificación a administradores
    }
}
