<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MicrosoftAccountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'microsoft_id' => $this->microsoft_id,
            'domain' => $this->domain,
            'domain_concatenated' => $this->domain_concatenated,
            'admin_email' => $this->admin_email,

            // Información personal
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => $this->full_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'organization' => $this->organization,

            // Dirección
            'address' => $this->address,
            'city' => $this->city,
            'state_code' => $this->state_code,
            'state_name' => $this->state_name,
            'postal_code' => $this->postal_code,
            'country_code' => $this->country_code,
            'country_name' => $this->country_name,

            // Configuración regional
            'language_code' => $this->language_code,
            'culture' => $this->culture,

            // Estados
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            'is_current' => $this->is_current,
            'is_pending' => $this->is_pending,
            'status_text' => $this->status_text,

            // Metadatos
            'configuration_id' => $this->configuration_id,
            'store_id' => $this->store_id,
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),

            // URLs útiles
            'microsoft_admin_url' => 'https://admin.microsoft.com/#/homepage',

            // Información adicional condicionalmente
            'user' => $this->whenLoaded('user', function () {
                return [
                    'id' => $this->user->id,
                    'name' => $this->user->full_name ?? 'N/A',
                    'email' => $this->user->email ?? 'N/A',
                ];
            }),
        ];
    }
}
