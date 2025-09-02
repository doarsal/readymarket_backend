<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->idproduct,
            'product_id' => $this->ProductId,
            'sku_id' => $this->SkuId,
            'title' => $this->ProductTitle,
            'sku_title' => $this->SkuTitle,
            'publisher' => $this->Publisher,
            'description' => $this->SkuDescription,
            'price' => [
                'unit_price' => $this->UnitPrice,
                'currency' => $this->Currency,
                'erp_price' => $this->ERPPrice,
            ],
            'media' => [
                'icon' => $this->prod_icon,
                'slide_image' => $this->prod_slideimage,
                'screenshots' => array_filter([
                    $this->prod_screenshot1,
                    $this->prod_screenshot2,
                    $this->prod_screenshot3,
                    $this->prod_screenshot4,
                ]),
            ],
            'market' => $this->Market,
            'segment' => $this->Segment,
            'is_active' => (bool) $this->is_active,
            'is_top' => (bool) $this->top,
            'category' => $this->whenLoaded('category'),
            'tags' => $this->Tags ? explode(',', $this->Tags) : [],
            'effective_dates' => [
                'start' => $this->EffectiveStartDate,
                'end' => $this->EffectiveEndDate,
            ],
        ];
    }
}
