<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AmexNewClientForm;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Error de validación",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="The given data was invalid."),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function __invoke(Request $request)
    {

        // Validar los datos del formulario
        $validator = Validator::make($request->all(), [
            'contacto_nombre'          => 'required|string|max:50',
            'contacto_apellidos'       => 'required|string|max:50',
            'contacto_telefono'        => 'required|string|min:8|max:15',
            'contacto_email'           => 'required|email|max:50',
            'empresa_nombre'           => 'required|string|max:50',
            'empresa_rfc'              => 'required|string|min:8|max:15',
            'empresa_ciudad'           => 'required|string|max:50',
            'empresa_estado'           => 'required|string|max:50',
            'empresa_codigo_postal'    => 'required|string|max:50|min:5',
            'empresa_ingresos_anuales' => 'required|string|max:50',
            'empresa_info_adicional'   => 'nullable|string|max:255',
        ], [
            'contacto_nombre.required'          => 'El nombre es obligatorio.',
            'contacto_nombre.max'               => 'El nombre no puede exceder 50 caracteres.',
            'contacto_apellidos.required'       => 'Los apellidos son obligatorios.',
            'contacto_apellidos.max'            => 'Los apellidos no pueden exceder 50 caracteres.',
            'contacto_telefono.required'        => 'El teléfono es obligatorio.',
            'contacto_telefono.min'             => 'El teléfono debe tener al menos 8 dígitos.',
            'contacto_telefono.max'             => 'El teléfono no puede exceder 15 dígitos.',
            'contacto_email.required'           => 'El correo electrónico es obligatorio.',
            'contacto_email.email'              => 'El correo electrónico debe tener un formato válido.',
            'contacto_email.max'                => 'El correo electrónico no puede exceder 50 caracteres.',
            'empresa_nombre.required'           => 'El nombre es obligatorio.',
            'empresa_nombre.max'                => 'El nombre no puede exceder 50 caracteres.',
            'empresa_rfc.required'              => 'El RFC es obligatorio.',
            'empresa_rfc.min'                   => 'El RFC debe tener al menos 10 caracteres.',
            'empresa_rfc.max'                   => 'El RFC no puede exceder 15 caracteres.',
            'empresa_ciudad.required'           => 'La ciudad es obligatoria.',
            'empresa_ciudad.max'                => 'La ciudad no puede exceder 50 caracteres.',
            'empresa_estado.required'           => 'El estado es obligatorio.',
            'empresa_estado.max'                => 'El estado no puede exceder 50 caracteres.',
            'empresa_codigo_postal.required'    => 'El código postal es obligatorio.',
            'empresa_codigo_postal.max'         => 'El código postal no puede exceder 50 caracteres.',
            'empresa_codigo_postal.min'         => 'El código postal no puede exceder 5 caracteres.',
            'empresa_ingresos_anuales.required' => 'Los ingresos anuales son obligatorios.',
            'empresa_ingresos_anuales.max'      => 'Los ingresos anuales no pueden exceder 50 caracteres.',
            'empresa_info_adicional.max'        => 'La información adicional no puede exceder 255 caracteres.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error en la validación de datos.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        AmexNewClientForm::create($request->only(self::VALID_FORM_FIELDS));

        return response()->json(['success' => true], HttpResponse::HTTP_CREATED);
    }
}
