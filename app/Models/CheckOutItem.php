<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CheckOutItem extends Model
{
    protected $fillable = [
        'item',
        'description',
        'min_cart_amount',
        'max_cart_amount',
        'percentage_of_amount',
        'help_cta',
        'help_text',
        'price',
        'currency_id',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    //Relationships

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    //Scopes

    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
