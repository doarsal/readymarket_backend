<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="UpdateCartItemRequest",
 *     type="object",
 *     title="Update Cart Item Request",
 *     description="Request body for updating a cart item",
 *     @OA\Property(property="quantity", type="integer", minimum=1, maximum=999, description="New quantity", example=3),
 *     @OA\Property(property="metadata", type="object", nullable=true, description="Additional metadata", example={"custom_config": "new_value"})
 * )
 */
class UpdateCartItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => 'nullable|integer|min:1|max:999',
            'metadata' => 'nullable|array',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.min' => 'La cantidad mínima es 1.',
            'quantity.max' => 'La cantidad máxima es 999.',
        ];
    }
}
