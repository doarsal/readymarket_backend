<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "UpdateBillingInformationRequest",
    type: "object",
    title: "Update Billing Information Request",
    description: "Datos para actualizar información de facturación existente",
    properties: [
        new OA\Property(property: "organization", type: "string", maxLength: 255, description: "Nombre de la organización"),
        new OA\Property(property: "rfc", type: "string", maxLength: 15, description: "RFC de la organización"),
        new OA\Property(property: "tax_regime_id", type: "integer", description: "ID del régimen fiscal"),
        new OA\Property(property: "postal_code", type: "string", maxLength: 10, description: "Código postal"),
        new OA\Property(property: "email", type: "string", format: "email", maxLength: 180, description: "Correo electrónico de facturación"),
        new OA\Property(property: "phone", type: "string", maxLength: 15, description: "Teléfono de contacto"),
        new OA\Property(property: "file", type: "string", maxLength: 120, nullable: true, description: "Archivo adjunto"),
        new OA\Property(property: "active", type: "boolean", description: "Estado activo/inactivo"),
        new OA\Property(property: "config_id", type: "integer", description: "ID de configuración"),
        new OA\Property(property: "store_id", type: "integer", description: "ID de la tienda"),
        new OA\Property(property: "account_id", type: "integer", nullable: true, description: "ID de cuenta asociada"),
        new OA\Property(property: "code", type: "string", maxLength: 20, description: "Código único identificador"),
        new OA\Property(property: "is_default", type: "boolean", description: "Marcar como información por defecto")
    ]
)]
class UpdateBillingInformationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $billingId = $this->route('billing_information');

        return [
            'organization' => 'sometimes|string|max:255',
            'rfc' => [
                'sometimes',
                'string',
                'max:15',
                Rule::unique('billing_information', 'rfc')->ignore($billingId)
            ],
            'tax_regime_id' => 'sometimes|integer|min:1',
            'postal_code' => 'sometimes|string|max:10',
            'email' => 'sometimes|email|max:180',
            'phone' => 'sometimes|string|max:15',
            'file' => 'sometimes|nullable|string|max:120',
            'active' => 'sometimes|boolean',
            'config_id' => 'sometimes|integer|min:1',
            'store_id' => 'sometimes|integer|min:1',
            'account_id' => 'sometimes|nullable|integer|min:1',
            'code' => [
                'sometimes',
                'string',
                'max:20',
                Rule::unique('billing_information', 'code')->ignore($billingId)
            ],
            'is_default' => 'sometimes|boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'organization.string' => 'El nombre de la organización debe ser texto.',
            'rfc.unique' => 'Este RFC ya está registrado.',
            'tax_regime_id.integer' => 'El régimen fiscal debe ser un número.',
            'email.email' => 'Debe proporcionar un correo electrónico válido.',
            'code.unique' => 'Este código ya está en uso.'
        ];
    }
}
