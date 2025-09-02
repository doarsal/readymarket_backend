<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\MicrosoftAccount;

/**
 * @OA\Schema(
 *     schema="UpdateMicrosoftAccountRequest",
 *     type="object",
 *     title="Update Microsoft Account Request",
 *     description="Request body for updating a Microsoft account",
 *     @OA\Property(property="first_name", type="string", maxLength=100, nullable=true, description="First name", example="John"),
 *     @OA\Property(property="last_name", type="string", maxLength=100, nullable=true, description="Last name", example="Doe"),
 *     @OA\Property(property="email", type="string", format="email", maxLength=255, nullable=true, description="Email address", example="john.doe@company.com"),
 *     @OA\Property(property="phone", type="string", maxLength=20, nullable=true, description="Phone number", example="+1234567890"),
 *     @OA\Property(property="organization", type="string", maxLength=255, nullable=true, description="Organization name", example="My Company Inc"),
 *     @OA\Property(property="address", type="string", maxLength=500, nullable=true, description="Address", example="123 Main St"),
 *     @OA\Property(property="city", type="string", maxLength=100, nullable=true, description="City", example="New York"),
 *     @OA\Property(property="state_code", type="string", maxLength=10, nullable=true, description="State code", example="NY"),
 *     @OA\Property(property="state_name", type="string", maxLength=100, nullable=true, description="State name", example="New York"),
 *     @OA\Property(property="postal_code", type="string", maxLength=20, nullable=true, description="Postal code", example="10001"),
 *     @OA\Property(property="country_code", type="string", maxLength=2, nullable=true, description="Country code", example="US"),
 *     @OA\Property(property="country_name", type="string", maxLength=100, nullable=true, description="Country name", example="United States"),
 *     @OA\Property(property="language_code", type="string", maxLength=5, nullable=true, description="Language code", example="en"),
 *     @OA\Property(property="culture", type="string", maxLength=10, nullable=true, description="Culture code", example="en-US"),
 *     @OA\Property(property="is_active", type="boolean", nullable=true, description="Is account active", example=true),
 *     @OA\Property(property="is_default", type="boolean", nullable=true, description="Set as default account", example=false)
 * )
 */
class UpdateMicrosoftAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $accountId = $this->route('id');
        $account = \App\Models\MicrosoftAccount::find($accountId);
        return auth()->check() && $account && $account->user_id === auth()->id();
    }

    public function rules(): array
    {
        $userId = auth()->id();
        $accountId = $this->route('id');

        return [
            // Información básica
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'email' => 'sometimes|required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'organization' => 'sometimes|required|string|max:255',

            // Dominio (solo si se proporciona)
            'domain' => [
                'sometimes',
                'required',
                'string',
                'max:150',
                function ($attribute, $value, $fail) use ($userId, $accountId) {
                    $account = new MicrosoftAccount();
                    $cleanDomain = $account->formatDomain($value);

                    if (!$account->isDomainAvailable($cleanDomain, $userId, $accountId)) {
                        $fail('El dominio ya está registrado para este usuario.');
                    }
                }
            ],

            // Dirección
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'state_code' => 'nullable|string|max:10',
            'state_name' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:20',
            'country_code' => 'nullable|string|size:2',
            'country_name' => 'nullable|string|max:100',

            // Configuración regional
            'language_code' => 'nullable|string|max:10',
            'culture' => 'nullable|string|max:10',

            // Estados
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.required' => 'El nombre es obligatorio.',
            'last_name.required' => 'Los apellidos son obligatorios.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'El correo electrónico debe tener un formato válido.',
            'organization.required' => 'El nombre de la organización es obligatorio.',
            'domain.required' => 'El dominio es obligatorio.',
            'country_code.size' => 'El código de país debe tener exactamente 2 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Limpiar el dominio si se proporciona
        if ($this->has('domain')) {
            $account = new MicrosoftAccount();
            $cleanDomain = $account->formatDomain($this->domain);
            $this->merge(['domain' => $cleanDomain]);
        }

        // Convertir valores boolean
        if ($this->has('is_active')) {
            $this->merge(['is_active' => $this->boolean('is_active')]);
        }
        if ($this->has('is_default')) {
            $this->merge(['is_default' => $this->boolean('is_default')]);
        }
    }
}
