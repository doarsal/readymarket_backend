<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Servicio para generar URLs de invitación de Microsoft Partner Center
 * para vincular cuentas existentes como CSP (Cloud Solution Provider)
 */
class MicrosoftPartnerInvitationService
{
    private string $partnerId;
    private string $msppId;
    private string $partnerEmail;
    private string $partnerPhone;
    private string $partnerName;

    public function __construct()
    {
        // Configuración del Partner desde variables de entorno
        $this->partnerId = config('services.microsoft.partner_id', 'fa233b05-e848-45c4-957f-d3e11acfc49c');
        $this->msppId = config('services.microsoft.mspp_id', '0');
        $this->partnerEmail = config('services.microsoft.partner_email', 'backofficemex@readymind.ms');
        $this->partnerPhone = config('services.microsoft.partner_phone', '5585261168');
        $this->partnerName = config('services.microsoft.partner_name', 'ReadyMarket of Readymind Mexico SA de CV');
    }

    /**
     * Generar URL de invitación para establecer relación de reseller
     *
     * @return string URL de invitación
     */
    public function generatePartnerInvitationUrl(): string
    {
        return sprintf(
            'https://admin.microsoft.com/Adminportal/Home?invType=ResellerRelationship&partnerId=%s&msppId=%s&DAP=true#/BillingAccounts/partner-invitation',
            $this->partnerId,
            $this->msppId
        );
    }

    /**
     * Generar URL para verificar/completar perfil de facturación
     *
     * @return string URL del perfil de facturación
     */
    public function generateBillingProfileUrl(): string
    {
        return 'https://admin.microsoft.com/Adminportal/Home?#/BillingAccounts/billing-accounts';
    }

    /**
     * Generar URL del portal de administración de Microsoft
     *
     * @return string URL del admin center
     */
    public function generateAdminCenterUrl(): string
    {
        return 'https://admin.microsoft.com/#/homepage';
    }

    /**
     * Obtener información del partner para mostrar al usuario
     *
     * @return array Información del partner
     */
    public function getPartnerInformation(): array
    {
        return [
            'partner_name' => $this->partnerName,
            'partner_email' => $this->partnerEmail,
            'partner_phone' => $this->partnerPhone,
            'partner_id' => $this->partnerId,
        ];
    }

    /**
     * Generar datos completos de invitación con URLs e información
     *
     * @param string $domain Dominio del cliente
     * @param string $globalAdminEmail Email del Global Admin
     * @return array Datos completos de la invitación
     */
    public function generateInvitationData(string $domain, string $globalAdminEmail): array
    {
        return [
            'domain' => $domain,
            'global_admin_email' => $globalAdminEmail,
            'urls' => [
                'billing_profile' => $this->generateBillingProfileUrl(),
                'partner_invitation' => $this->generatePartnerInvitationUrl(),
                'admin_center' => $this->generateAdminCenterUrl(),
            ],
            'partner' => $this->getPartnerInformation(),
            'instructions' => $this->generateInstructionsText(),
        ];
    }

    /**
     * Generar texto de instrucciones en formato plano
     *
     * @return array Array con las instrucciones paso a paso
     */
    private function generateInstructionsText(): array
    {
        return [
            'step_1' => [
                'title' => 'Paso 1: Verificar perfil de facturación',
                'description' => 'Inicia sesión con tu cuenta de Global Admin y asegúrate de que tu perfil de cliente esté completo.',
                'url' => $this->generateBillingProfileUrl(),
                'note' => 'Puede tomar hasta 5 minutos para que se actualice después de realizar cambios.'
            ],
            'step_2' => [
                'title' => 'Paso 2: Aceptar invitación del Partner',
                'description' => 'Después de completar el perfil, haz clic en el siguiente enlace para aceptar la invitación y autorizar a ' . $this->partnerName . ' como tu proveedor de soluciones en la nube de Microsoft.',
                'url' => $this->generatePartnerInvitationUrl(),
                'note' => 'Se requiere usuario con permisos de Global Admin para aceptar la relación.'
            ],
            'requirements' => [
                'Cuenta Microsoft 365 activa',
                'Permisos de Global Administrator',
                'Perfil de facturación completado',
                'Acceso al portal de administración de Microsoft'
            ],
            'support' => [
                'email' => $this->partnerEmail,
                'phone' => $this->partnerPhone,
                'name' => $this->partnerName
            ]
        ];
    }

    /**
     * Log de generación de invitación
     *
     * @param int $accountId ID de la cuenta
     * @param string $domain Dominio
     * @param string $email Email del Global Admin
     */
    public function logInvitationGenerated(int $accountId, string $domain, string $email): void
    {
        Log::info('Microsoft Partner Invitation generated', [
            'account_id' => $accountId,
            'domain' => $domain,
            'global_admin_email' => $email,
            'partner_id' => $this->partnerId,
            'invitation_url' => $this->generatePartnerInvitationUrl()
        ]);
    }
}
