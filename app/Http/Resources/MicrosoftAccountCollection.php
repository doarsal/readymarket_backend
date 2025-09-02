<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class MicrosoftAccountCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
            'links' => [
                'self' => $request->url(),
            ],
            'meta' => [
                'count' => $this->collection->count(),
                'total' => $this->resource->total() ?? $this->collection->count(),
                'current_page' => $this->resource->currentPage() ?? 1,
                'last_page' => $this->resource->lastPage() ?? 1,
                'per_page' => $this->resource->perPage() ?? 15,
            ],
        ];
    }
}
