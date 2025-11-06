<?php

namespace App\Http\Requests\Cart\CheckOutItem;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="UpdateCartCheckOutItemRequest",
 *     type="object",
 *     title="Update Check Out Item",
 *     description="Request body for update a check out item cart",
 *     required={"cart_check_out_item_id", "status"},
 *     @OA\Property(property="cart_check_out_item_id", type="integer", description="Cart Check Out Item ID", example=1),
 *     @OA\Property(property="status", type="boolean", description="Status of item", example=true),
 *     @OA\Property(property="metadata", type="object", nullable=true, description="Additional metadata", example={"custom_config": "value"}),
 *     @OA\Property(property="store_id", type="integer", nullable=true, description="Store ID", example=1)
 * )
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permitir tanto usuarios logueados como no logueados
    }

    public function rules(): array
    {
        return [
            'status' => 'required|boolean',
            'metadata' => 'nullable|array',
            'store_id' => 'nullable|integer|exists:stores,id',
        ];
    }

    public function messages(): array
    {
        return [
            'quantity.required' => 'El status es requerida.',
            'quantity.boolean' => 'El status debe ser verdadero o falso.',
            'store_id.exists' => 'La tienda especificada no existe.',
        ];
    }
}
