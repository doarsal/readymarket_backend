<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * @OA\Schema(
 *     schema="AddToCartRequest",
 *     type="object",
 *     title="Add to Cart Request",
 *     description="Request body for adding a product to cart",
 *     required={"product_id", "quantity"},
 *     @OA\Property(property="product_id", type="integer", description="Product ID", example=1),
 *     @OA\Property(property="quantity", type="integer", minimum=1, maximum=999, description="Quantity to add", example=2),
 *     @OA\Property(property="metadata", type="object", nullable=true, description="Additional metadata", example={"custom_config": "value"}),
 *     @OA\Property(property="store_id", type="integer", nullable=true, description="Store ID", example=1)
 * )
 */
class AddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Permitir tanto usuarios logueados como no logueados
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|exists:products,idproduct',
            'quantity' => 'required|integer|min:1|max:999',
            'metadata' => 'nullable|array',
            'store_id' => 'nullable|integer|exists:stores,id',
        ];
    }

    public function messages(): array
    {
        return [
            'product_id.required' => 'El ID del producto es requerido.',
            'product_id.exists' => 'El producto especificado no existe.',
            'quantity.required' => 'La cantidad es requerida.',
            'quantity.min' => 'La cantidad mínima es 1.',
            'quantity.max' => 'La cantidad máxima es 999.',
            'store_id.exists' => 'La tienda especificada no existe.',
        ];
    }
}
