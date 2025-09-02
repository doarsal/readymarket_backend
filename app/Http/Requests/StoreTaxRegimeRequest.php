<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTaxRegimeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Ajustar según el sistema de permisos
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sat_code' => 'nullable|integer|min:1',
            'name' => 'required|string|max:120',
            'relation' => 'nullable|integer',
            'store_id' => 'nullable|exists:stores,id',
            'active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'sat_code.integer' => 'El código del SAT debe ser un número entero.',
            'name.required' => 'El nombre del régimen fiscal es obligatorio.',
            'name.max' => 'El nombre no puede exceder 120 caracteres.',
            'store_id.exists' => 'La tienda seleccionada no existe.',
        ];
    }

    /**
     * Get custom attribute names for validator errors.
     */
    public function attributes(): array
    {
        return [
            'sat_code' => 'código del SAT',
            'name' => 'nombre del régimen',
            'relation' => 'relación',
            'store_id' => 'tienda',
            'active' => 'estado activo',
        ];
    }
}
