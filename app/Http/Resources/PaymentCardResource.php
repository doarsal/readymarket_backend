<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PaymentCardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $isExpired = $this->is_expired;
        $isExpiringSoon = $this->getIsExpiringSoonAttribute();
        $daysUntilExpiry = $this->getDaysUntilExpiryAttribute();

        return [
            'id' => $this->id,
            'masked_card_number' => $this->masked_card_number,
            'last_four_digits' => $this->last_four_digits,
            'brand' => $this->brand,
            'card_type' => $this->card_type,
            'cardholder_name' => $this->cardholder_name,
            'expiry_month' => $this->expiry_month,
            'expiry_year' => $this->expiry_year,
            'expiry_display' => sprintf('%02d/%s', $this->expiry_month, $this->expiry_year),
            'is_default' => $this->is_default,
            'is_active' => $this->is_active,

            // ===== INDICADORES PARA UI =====
            'is_expired' => $isExpired,
            'is_expiring_soon' => $isExpiringSoon,
            'days_until_expiry' => $daysUntilExpiry,

            // ===== ESTADOS DE UI =====
            'ui_status' => $this->getUiStatus($isExpired, $isExpiringSoon, $daysUntilExpiry),
            'ui_color' => $this->getUiColor($isExpired, $isExpiringSoon),
            'ui_actions_allowed' => $this->getUiActionsAllowed($isExpired),
            'ui_warning_message' => $this->getUiWarningMessage($isExpired, $isExpiringSoon, $daysUntilExpiry),

            'last_used_at' => $this->last_used_at?->format('Y-m-d H:i:s'),
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Get UI status for frontend display
     */
    private function getUiStatus($isExpired, $isExpiringSoon, $daysUntilExpiry)
    {
        if ($isExpired) {
            return 'expired';
        } elseif ($isExpiringSoon) {
            return 'expiring_soon';
        } else {
            return 'valid';
        }
    }

    /**
     * Get UI color for frontend styling
     */
    private function getUiColor($isExpired, $isExpiringSoon)
    {
        if ($isExpired) {
            return 'red';        // Rojo para expiradas
        } elseif ($isExpiringSoon) {
            return 'orange';     // Naranja para próximas a vencer
        } else {
            return 'green';      // Verde para válidas
        }
    }

    /**
     * Get allowed UI actions
     */
    private function getUiActionsAllowed($isExpired)
    {
        return [
            'can_use_for_payment' => !$isExpired,
            'can_set_as_default' => !$isExpired,
            'can_edit' => !$isExpired,
            'can_delete' => true,           // Siempre se puede eliminar
            'show_expired_warning' => $isExpired
        ];
    }

    /**
     * Get UI warning message
     */
    private function getUiWarningMessage($isExpired, $isExpiringSoon, $daysUntilExpiry)
    {
        if ($isExpired) {
            $daysExpired = abs($daysUntilExpiry);
            return "Esta tarjeta expiró hace {$daysExpired} días. Solo puedes eliminarla.";
        } elseif ($isExpiringSoon) {
            return "Esta tarjeta vence en {$daysUntilExpiry} días. Considera actualizarla.";
        }

        return null;
    }
}
