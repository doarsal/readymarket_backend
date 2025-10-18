<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @OA\Schema(
 *     schema="AmexNewClientForm",
 *     type="object",
 *     title="Amex New Client Form",
 *     description="Formulario de nuevos clientes para Amex",
 *     required={
 *         "contacto_nombre",
 *         "contacto_apellidos",
 *         "contacto_telefono",
 *         "contacto_email",
 *         "empresa_nombre",
 *         "empresa_rfc",
 *         "empresa_ciudad",
 *         "empresa_estado",
 *         "empresa_codigo_postal",
 *         "empresa_ingresos_anuales",
 *         "fecha_envio"
 *     }
 * )
 *
 * @OA\Property(property="id", type="integer", example=1, description="ID único del registro")
 * @OA\Property(property="contacto_nombre", type="string", example="María", description="Nombre de la persona de contacto")
 * @OA\Property(property="contacto_apellidos", type="string", example="Pérez Gómez", description="Apellidos de la persona de contacto")
 * @OA\Property(property="contacto_telefono", type="string", example="+52 55 1234 5678", description="Teléfono de contacto")
 * @OA\Property(property="contacto_email", type="string", format="email", example="maria.perez@empresa.com", description="Correo electrónico de contacto")
 *
 * @OA\Property(property="empresa_nombre", type="string", example="Comercializadora XYZ SA de CV", description="Nombre de la empresa")
 * @OA\Property(property="empresa_rfc", type="string", example="XYZ123456ABC", description="RFC de la empresa")
 * @OA\Property(property="empresa_ciudad", type="string", example="Ciudad de México", description="Ciudad de la empresa")
 * @OA\Property(property="empresa_estado", type="string", example="CDMX", description="Estado/Provincia de la empresa")
 * @OA\Property(property="empresa_codigo_postal", type="string", example="06000", description="Código postal de la empresa")
 *
 * @OA\Property(property="empresa_ingresos_anuales", type="string", example="1500000", description="Ingresos anuales de la empresa (cadena)")
 * @OA\Property(property="empresa_info_adicional", type="string", nullable=true, example="Tiene operaciones en LATAM", description="Información adicional de la empresa")
 *
 * @OA\Property(property="status_envio", type="boolean", nullable=true, example=false, description="Estado del envío del formulario")
 * @OA\Property(property="fecha_envio", type="string", format="date-time", example="2025-03-01T10:30:00Z", description="Fecha y hora de envío del formulario")
 *
 * @OA\Property(property="created_at", type="string", format="date-time", example="2025-03-01T10:30:00Z")
 * @OA\Property(property="updated_at", type="string", format="date-time", example="2025-03-01T10:35:00Z")
 */
class AmexNewClientForm extends Model
{
    use HasFactory;

    protected $fillable = [
        'contacto_nombre',
        'contacto_apellidos',
        'contacto_telefono',
        'contacto_email',
        'empresa_nombre',
        'empresa_rfc',
        'empresa_ciudad',
        'empresa_estado',
        'empresa_codigo_postal',
        'empresa_ingresos_anuales',
        'empresa_info_adicional',
        'fecha_envio',
        'status_envio',
    ];

    protected function casts(): array
    {
        return [
            'fecha_envio'  => 'datetime',
            'status_envio' => 'boolean',
            'created_at'   => 'datetime',
            'updated_at'   => 'datetime',
        ];
    }
}
