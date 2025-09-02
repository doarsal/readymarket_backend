<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreActivityRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Adjust based on your authorization logic
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:180|unique:activities,name',
            'description' => 'nullable|string|max:1000',
            'icon' => 'nullable|string|max:45',
            'active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la actividad es obligatorio.',
            'name.unique' => 'Ya existe una actividad con este nombre.',
            'name.max' => 'El nombre no puede exceder 180 caracteres.',
            'description.max' => 'La descripción no puede exceder 1000 caracteres.',
            'icon.max' => 'El icono no puede exceder 45 caracteres.',
            'active.boolean' => 'El estado activo debe ser verdadero o falso.',
        ];
    }
}
