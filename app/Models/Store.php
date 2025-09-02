<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'domain',
        'subdomain',
        'default_language',
        'default_currency',
        'timezone',
        'is_active',
        'is_maintenance'
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_maintenance' => 'boolean',
    ];

    // Relaciones
    public function configurations()
    {
        return $this->hasMany(StoreConfiguration::class);
    }

    public function languages()
    {
        return $this->belongsToMany(Language::class, 'store_languages')
                    ->withPivot('is_default', 'is_active', 'sort_order')
                    ->withTimestamps();
    }

    public function currencies()
    {
        return $this->belongsToMany(Currency::class, 'store_currencies')
                    ->withPivot('is_default', 'is_active', 'sort_order', 'auto_update_rate')
                    ->withTimestamps();
    }

    public function categories()
    {
        return $this->hasMany(Category::class);
    }

    public function products()
    {
        return $this->hasMany(Product::class);
    }

    public function translations()
    {
        return $this->hasMany(Translation::class);
    }

    // Métodos auxiliares
    public function getConfiguration($category, $key, $default = null)
    {
        $config = $this->configurations()
                      ->where('category', $category)
                      ->where('key_name', $key)
                      ->first();

        return $config ? $config->value : $default;
    }

    public function setConfiguration($category, $key, $value, $type = 'string', $isPublic = false)
    {
        return $this->configurations()->updateOrCreate(
            ['category' => $category, 'key_name' => $key],
            ['value' => $value, 'type' => $type, 'is_public' => $isPublic]
        );
    }

    public function getDefaultLanguage()
    {
        return $this->languages()->wherePivot('is_default', true)->first();
    }

    public function getDefaultCurrency()
    {
        return $this->currencies()->wherePivot('is_default', true)->first();
    }
}
