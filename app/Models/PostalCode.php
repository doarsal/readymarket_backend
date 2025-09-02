<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: "PostalCode",
    type: "object",
    title: "Postal Code",
    description: "Códigos postales con información geográfica asociada",
    properties: [
        new OA\Property(property: "idpostalcode", type: "integer", format: "int64", description: "ID único del código postal"),
        new OA\Property(property: "pc_postalcode", type: "string", maxLength: 8, nullable: true, description: "Código postal"),
        new OA\Property(property: "pc_city", type: "string", maxLength: 180, nullable: true, description: "Ciudad"),
        new OA\Property(property: "pc_state", type: "string", maxLength: 45, nullable: true, description: "Estado (código corto)"),
        new OA\Property(property: "pc_countrycode", type: "string", maxLength: 3, nullable: true, description: "Código del país"),
        new OA\Property(property: "pc_culture", type: "string", maxLength: 10, nullable: true, description: "Cultura/locale"),
        new OA\Property(property: "pc_lang", type: "string", maxLength: 10, nullable: true, description: "Idioma"),
        new OA\Property(property: "pc_statelarge", type: "string", maxLength: 120, nullable: true, description: "Nombre completo del estado"),
        new OA\Property(property: "pc_countrylarge", type: "string", maxLength: 45, nullable: true, description: "Nombre completo del país")
    ]
)]
class PostalCode extends Model
{
    use HasFactory;

    // Configuración de la tabla existente
    protected $table = 'postalcodes';
    protected $primaryKey = 'idpostalcode';
    public $incrementing = true;
    protected $keyType = 'int';
    public $timestamps = false; // La tabla no tiene timestamps

    protected $fillable = [
        'pc_postalcode',
        'pc_city',
        'pc_state',
        'pc_countrycode',
        'pc_culture',
        'pc_lang',
        'pc_statelarge',
        'pc_countrylarge',
    ];

    protected $casts = [
        'idpostalcode' => 'integer',
    ];

    protected $appends = [
        'formatted_address'
    ];

    // Scopes para consultas comunes
    public function scopeByCode($query, $code)
    {
        return $query->where('pc_postalcode', $code);
    }

    public function scopeByCity($query, $city)
    {
        return $query->where('pc_city', 'like', '%' . $city . '%');
    }

    public function scopeByState($query, $state)
    {
        return $query->where('pc_state', $state);
    }

    public function scopeByCountry($query, $countryCode)
    {
        return $query->where('pc_countrycode', $countryCode);
    }

    public function scopeSearch($query, $search)
    {
        return $query->where(function($q) use ($search) {
            $q->where('pc_postalcode', 'like', '%' . $search . '%')
              ->orWhere('pc_city', 'like', '%' . $search . '%')
              ->orWhere('pc_statelarge', 'like', '%' . $search . '%')
              ->orWhere('pc_countrylarge', 'like', '%' . $search . '%');
        });
    }

    // Métodos helper
    public function getFormattedAddressAttribute(): string
    {
        $parts = array_filter([
            $this->pc_city,
            $this->pc_statelarge ?: $this->pc_state,
            $this->pc_countrylarge
        ]);

        return implode(', ', $parts);
    }

    public function getFullLocationAttribute(): string
    {
        return $this->getFormattedAddressAttribute();
    }

    public function getAddressDataAttribute(): array
    {
        return [
            'postal_code' => $this->pc_postalcode,
            'city' => $this->pc_city,
            'state' => $this->pc_state,
            'state_name' => $this->pc_statelarge,
            'country_code' => $this->pc_countrycode,
            'country_name' => $this->pc_countrylarge,
            'culture' => $this->pc_culture,
            'language' => $this->pc_lang,
            'full_location' => $this->getFormattedAddressAttribute()
        ];
    }

    // Método estático para búsqueda rápida por código postal
    public static function findByPostalCode($postalCode)
    {
        return static::byCode($postalCode)->first();
    }

    // Método para autocompletar direcciones
    public static function autocompleteAddress($postalCode)
    {
        $result = static::findByPostalCode($postalCode);

        if (!$result) {
            return null;
        }

        return $result->getAddressDataAttribute();
    }

    // Método estático para obtener información de dirección para autocompletar formularios
    public static function getAddressInfo($postalCode)
    {
        $results = static::byCode($postalCode)->get();

        if ($results->isEmpty()) {
            return null;
        }

        $first = $results->first();

        return [
            'postal_code' => $first->pc_postalcode,
            'city' => $first->pc_city,
            'state' => $first->pc_statelarge ?: $first->pc_state,
            'country' => $first->pc_countrylarge,
            'country_code' => $first->pc_countrycode,
            'formatted_address' => $first->getFullLocationAttribute(),
            'neighborhoods' => $results->pluck('pc_city')->unique()->values()->toArray()
        ];
    }
}
