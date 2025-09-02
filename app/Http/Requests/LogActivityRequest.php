<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogActivityRequest extends FormRequest
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
            'activity_id' => 'required|integer|exists:activities,id',
            'module' => 'nullable|string|max:120',
            'title' => 'nullable|string|max:120',
            'reference_id' => 'nullable|string|max:255',
            'metadata' => 'nullable|array',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'activity_id.required' => 'El ID de la actividad es obligatorio.',
            'activity_id.exists' => 'La actividad especificada no existe.',
            'module.max' => 'El módulo no puede exceder 120 caracteres.',
            'title.max' => 'El título no puede exceder 120 caracteres.',
            'reference_id.max' => 'El ID de referencia no puede exceder 255 caracteres.',
            'metadata.array' => 'Los metadatos deben ser un objeto JSON válido.',
        ];
    }
}
