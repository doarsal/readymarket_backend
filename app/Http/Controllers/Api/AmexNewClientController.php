<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AmexNewClientForm;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * @OA\Tag(
 *     name="Amex",
 *     description="Endpoints para formulario de nuevos clientes Amex"
 * )
 */
class AmexNewClientController extends Controller
{
    const VALID_FORM_FIELDS = [
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
    ];

    /**
     * @OA\Post(
     *     path="/api/v1/promotions/amex-new-client",
     *     tags={"Amex"},
     *     summary="Crear registro de nuevo cliente Amex",
     *     description="Crea un nuevo registro con los datos del formulario de Amex",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={
     *                 "contacto_nombre",
     *                 "contacto_apellidos",
     *                 "contacto_telefono",
     *                 "contacto_email",
     *                 "empresa_nombre",
     *                 "empresa_rfc",
     *                 "empresa_ciudad",
     *                 "empresa_estado",
     *                 "empresa_codigo_postal",
     *                 "empresa_ingresos_anuales"
     *             },
     *             @OA\Property(property="contacto_nombre", type="string", example="María"),
     *             @OA\Property(property="contacto_apellidos", type="string", example="Pérez Gómez"),
     *             @OA\Property(property="contacto_telefono", type="string", example="+52 55 1234 5678"),
     *             @OA\Property(property="contacto_email", type="string", format="email", example="maria.perez@empresa.com"),
     *             @OA\Property(property="empresa_nombre", type="string", example="Comercializadora XYZ SA de CV"),
     *             @OA\Property(property="empresa_rfc", type="string", example="XYZ123456ABC"),
     *             @OA\Property(property="empresa_ciudad", type="string", example="Ciudad de México"),
     *             @OA\Property(property="empresa_estado", type="string", example="CDMX"),
     *             @OA\Property(property="empresa_codigo_postal", type="string", example="06000"),
     *             @OA\Property(property="empresa_ingresos_anuales", type="string", example="1500000"),
     *             @OA\Property(property="empresa_info_adicional", type="string", nullable=true, example="Opera en LATAM")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Creado",
     *         @OA\JsonContent(type="object", example={success: true})
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(
     *                 property="errors",
     *                 type="object",
     *                 additionalProperties=@OA\Schema(type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function __invoke(Request $request)
    {
        AmexNewClientForm::create($request->only(self::VALID_FORM_FIELDS));

        return response()->json(['success' => true], HttpResponse::HTTP_CREATED);
    }
}
