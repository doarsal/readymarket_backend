<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'categories';
    protected $primaryKey = 'id';

    protected $fillable = [
        'store_id',
        'name',
        'image',
        'identifier',
        'is_active',
        'is_deleted',
        'sort_order',
        'columns',
        'description',
        'visits'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_deleted' => 'boolean',
        'sort_order' => 'integer',
        'columns' => 'integer',
        'visits' => 'integer'
    ];

    /**
     * Get the store that owns the category
     */
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    /**
     * Get products for this category
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id', 'id');
    }

    /**
     * Get active products for this category
     */
    public function activeProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'category_id', 'id')
            ->where('is_active', true);
    }

    /**
     * Scope for active categories
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true)
            ->where('is_deleted', false);
    }

    /**
     * Scope for ordering categories
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order');
    }
}
