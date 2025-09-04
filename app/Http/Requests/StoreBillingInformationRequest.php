<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "StoreBillingInformationRequest",
    type: "object",
    title: "Store Billing Information Request",
    description: "Datos para crear nueva información de facturación",
    required: ["organization", "rfc", "tax_regime_id", "cfdi_usage_id", "postal_code", "email", "phone", "config_id", "store_id", "code"],
    properties: [
        new OA\Property(property: "organization", type: "string", maxLength: 255, description: "Nombre de la organización", example: "Mi Empresa S.A. de C.V."),
        new OA\Property(property: "rfc", type: "string", maxLength: 15, description: "RFC de la organización", example: "ABC123456789"),
        new OA\Property(property: "tax_regime_id", type: "integer", description: "ID del régimen fiscal", example: 1),
        new OA\Property(property: "cfdi_usage_id", type: "integer", description: "ID del uso de CFDI", example: 1),
        new OA\Property(property: "postal_code", type: "string", maxLength: 10, description: "Código postal", example: "01000"),
        new OA\Property(property: "email", type: "string", format: "email", maxLength: 180, description: "Correo electrónico de facturación", example: "facturacion@empresa.com"),
        new OA\Property(property: "phone", type: "string", maxLength: 15, description: "Teléfono de contacto", example: "5555551234"),
        new OA\Property(property: "file", type: "string", maxLength: 120, nullable: true, description: "Archivo adjunto opcional"),
        new OA\Property(property: "active", type: "boolean", description: "Estado activo/inactivo", example: true),
        new OA\Property(property: "config_id", type: "integer", description: "ID de configuración", example: 1),
        new OA\Property(property: "store_id", type: "integer", description: "ID de la tienda", example: 1),
        new OA\Property(property: "account_id", type: "integer", nullable: true, description: "ID de cuenta asociada"),
        new OA\Property(property: "code", type: "string", maxLength: 20, description: "Código único identificador", example: "BILL001"),
        new OA\Property(property: "is_default", type: "boolean", description: "Marcar como información por defecto", example: false)
    ]
)]
class StoreBillingInformationRequest extends FormRequest
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
        return [
            'organization' => 'required|string|max:255',
            'rfc' => 'required|string|max:15|unique:billing_information,rfc,NULL,id,deleted_at,NULL',
            'tax_regime_id' => 'required|integer|min:1',
            'cfdi_usage_id' => 'required|integer|min:1',
            'postal_code' => 'required|string|max:10',
            'email' => 'required|email|max:180',
            'phone' => 'required|string|max:15',
            'file' => 'nullable|string|max:120',
            'active' => 'boolean',
            'config_id' => 'required|integer|min:1',
            'store_id' => 'required|integer|min:1',
            'account_id' => 'nullable|integer|min:1',
            'code' => 'required|string|max:20|unique:billing_information,code,NULL,id,deleted_at,NULL',
            'is_default' => 'boolean'
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'organization.required' => 'El nombre de la organización es obligatorio.',
            'rfc.required' => 'El RFC es obligatorio.',
            'rfc.unique' => 'Este RFC ya está registrado.',
            'tax_regime_id.required' => 'El régimen fiscal es obligatorio.',
            'cfdi_usage_id.required' => 'El uso de CFDI es obligatorio.',
            'postal_code.required' => 'El código postal es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Debe proporcionar un correo electrónico válido.',
            'phone.required' => 'El teléfono es obligatorio.',
            'config_id.required' => 'El ID de configuración es obligatorio.',
            'store_id.required' => 'El ID de la tienda es obligatorio.',
            'code.required' => 'El código es obligatorio.',
            'code.unique' => 'Este código ya está en uso.'
        ];
    }
}
