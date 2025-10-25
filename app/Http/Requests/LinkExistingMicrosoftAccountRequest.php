<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\MicrosoftAccount;

/**
 * @OA\Schema(
 *     schema="LinkExistingMicrosoftAccountRequest",
 *     type="object",
 *     title="Link Existing Microsoft Account Request",
 *     description="Request payload for linking an existing Microsoft account with Global Admin",
 *     required={"domain", "global_admin_email"},
 *     @OA\Property(property="domain", type="string", maxLength=150, description="Domain name (without .onmicrosoft.com)", example="mycompany.com"),
 *     @OA\Property(property="global_admin_email", type="string", format="email", maxLength=255, description="Email of the Global Admin user", example="admin@mycompany.onmicrosoft.com")
 * )
 */
class LinkExistingMicrosoftAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $userId = auth()->id();

        return [
            // Dominio (será validado y limpiado)
            'domain' => [
                'required',
                'string',
                'max:150',
                function ($attribute, $value, $fail) use ($userId) {
                    $account = new MicrosoftAccount();
                    $cleanDomain = $account->formatDomain($value);

                    if (!$account->isDomainAvailable($cleanDomain, $userId)) {
                        $fail('El dominio ya está registrado para este usuario.');
                    }
                }
            ],

            // Email del Global Admin
            'global_admin_email' => [
                'required',
                'email',
                'max:255'
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'domain.required' => 'El dominio es requerido.',
            'domain.max' => 'El dominio no debe exceder 150 caracteres.',
            'global_admin_email.required' => 'El correo del Global Admin es requerido.',
            'global_admin_email.email' => 'El correo del Global Admin debe ser válido.',
            'global_admin_email.max' => 'El correo del Global Admin no debe exceder 255 caracteres.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Limpiar y formatear el dominio antes de la validación
        if ($this->has('domain')) {
            $account = new MicrosoftAccount();
            $cleanDomain = $account->formatDomain($this->domain);
            $this->merge(['domain' => $cleanDomain]);
        }
    }
}
