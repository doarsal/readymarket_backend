<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Language extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'locale',
        'flag_icon',
        'is_rtl',
        'is_active',
        'sort_order'
    ];

    protected $casts = [
        'is_rtl' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'store_languages')
                    ->withPivot('is_default', 'is_active', 'sort_order')
                    ->withTimestamps();
    }

    public function translations()
    {
        return $this->hasMany(Translation::class);
    }
}
