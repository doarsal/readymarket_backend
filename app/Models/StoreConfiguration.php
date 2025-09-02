<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="StoreConfiguration",
 *     type="object",
 *     title="Store Configuration",
 *     description="Store configuration model for managing store-specific settings",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="store_id", type="integer", example=1),
 *     @OA\Property(property="category", type="string", example="tax"),
 *     @OA\Property(property="key_name", type="string", example="rate"),
 *     @OA\Property(property="value", type="string", example="0.16"),
 *     @OA\Property(property="type", type="string", enum={"string","text","json","boolean","integer","file","url"}, example="string"),
 *     @OA\Property(property="is_public", type="boolean", example=false),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class StoreConfiguration extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'category',
        'key_name',
        'value',
        'type',
        'is_public'
    ];

    protected $casts = [
        'is_public' => 'boolean',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function getTypedValue()
    {
        switch ($this->type) {
            case 'boolean':
                return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
            case 'integer':
                return (int) $this->value;
            case 'json':
                return json_decode($this->value, true);
            default:
                return $this->value;
        }
    }

    /**
     * Método estático para obtener configuración fácilmente
     */
    public static function get($storeId, $category, $keyName, $default = null)
    {
        $config = self::where('store_id', $storeId)
                     ->where('category', $category)
                     ->where('key_name', $keyName)
                     ->first();

        return $config ? $config->getTypedValue() : $default;
    }
}
