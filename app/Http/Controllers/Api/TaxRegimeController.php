<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTaxRegimeRequest;
use App\Http\Requests\UpdateTaxRegimeRequest;
use App\Models\TaxRegime;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use OpenApi\Attributes as OA;

#[OA\Tag(name: "Tax Regimes", description: "CRUD operations for SAT tax regimes")]
class TaxRegimeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/tax-regimes",
     *     tags={"Tax Regimes"},
     *     summary="List tax regimes (PUBLIC)",
     *     description="Gets the list of tax regimes with optional filters. This endpoint is public, no authentication required.",
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, default=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Records per page",
     *         required=false,
     *         @OA\Schema(type="integer", minimum=1, maximum=100, default=15)
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="sat_code",
     *         in="query",
     *         description="Filter by SAT code",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of tax regimes",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax regimes retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/TaxRegime")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        // Crear cache key único basado en filtros
        $cacheKey = $this->generateTaxRegimesCacheKey($request);

        // Solo cachear si no hay búsqueda específica (búsquedas son dinámicas)
        $shouldCache = !$request->filled('search');
        $cacheTime = 3600; // 1 hora

        if ($shouldCache) {
            $result = Cache::remember($cacheKey, $cacheTime, function () use ($request) {
                return $this->getTaxRegimesData($request);
            });
            return response()->json($result);
        } else {
            // Si hay búsqueda, no usar cache
            $result = $this->getTaxRegimesData($request);
            return response()->json($result);
        }
    }

    /**
     * Generar cache key para tax regimes
     */
    private function generateTaxRegimesCacheKey($request): string
    {
        $filters = [
            'active' => $request->get('active', 'all'),
            'store_id' => $request->get('store_id', 'all'),
            'sat_code' => $request->get('sat_code', 'all'),
            'per_page' => min($request->get('per_page', 15), 100)
        ];

        $filterString = http_build_query($filters);
        return 'tax_regimes_' . md5($filterString);
    }

    /**
     * Obtener datos de tax regimes
     */
    private function getTaxRegimesData($request): array
    {
        $query = TaxRegime::query()->with('store');

        // Filtros
        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        if ($request->filled('store_id')) {
            $query->forStore($request->store_id);
        }

        if ($request->filled('sat_code')) {
            $query->bySatCode($request->sat_code);
        }

        if ($request->filled('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Ordenamiento
        $query->orderBy('sat_code')->orderBy('name');

        // Paginación
        $perPage = min($request->get('per_page', 15), 100);
        $taxRegimes = $query->paginate($perPage);

        return [
            'success' => true,
            'message' => 'Tax regimes retrieved successfully',
            'data' => $taxRegimes
        ];
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tax-regimes",
     *     tags={"Tax Regimes"},
     *     summary="Create tax regime",
     *     description="Creates a new tax regime",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="sat_code", type="integer", description="SAT code", example=601),
     *             @OA\Property(property="name", type="string", maxLength=120, description="Regime name", example="General de Ley Personas Morales"),
     *             @OA\Property(property="relation", type="integer", description="Relation", example=1),
     *             @OA\Property(property="store_id", type="integer", description="Store ID", example=1),
     *             @OA\Property(property="active", type="boolean", description="Active status", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Tax regime created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax regime created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaxRegime")
     *         )
     *     ),
     *     @OA\Response(response=422, description="Validation error"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function store(StoreTaxRegimeRequest $request): JsonResponse
    {
        $taxRegime = TaxRegime::create($request->validated());
        $taxRegime->load('store');

        return response()->json([
            'success' => true,
            'message' => 'Tax regime created successfully',
            'data' => $taxRegime
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tax-regimes/{id}",
     *     tags={"Tax Regimes"},
     *     summary="Get specific tax regime (PUBLIC)",
     *     description="Gets the details of a specific tax regime. This endpoint is public, no authentication required.",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tax regime ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax regime found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax regime found"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaxRegime")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tax regime not found")
     * )
     */
    public function show(TaxRegime $taxRegime): JsonResponse
    {
        $taxRegime->load('store');

        return response()->json([
            'success' => true,
            'message' => 'Tax regime found',
            'data' => $taxRegime
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/tax-regimes/grouped",
     *     tags={"Tax Regimes"},
     *     summary="Get grouped tax regimes (PUBLIC)",
     *     description="Gets tax regimes organized hierarchically, ready to populate a select. Parents (relation=0) contain their corresponding children.",
     *     @OA\Parameter(
     *         name="active_only",
     *         in="query",
     *         description="Only active regimes",
     *         required=false,
     *         @OA\Schema(type="boolean", default=true)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Grouped tax regimes",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Grouped tax regimes retrieved"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="Personas físicas"),
     *                     @OA\Property(property="sat_code", type="integer", nullable=true, example=1),
     *                     @OA\Property(property="relation", type="integer", example=0),
     *                     @OA\Property(
     *                         property="children",
     *                         type="array",
     *                         @OA\Items(ref="#/components/schemas/TaxRegime")
     *                     )
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function getGrouped(Request $request): JsonResponse
    {
        $activeOnly = $request->boolean('active_only', true);

        // Obtener padres (relation = 0)
        $parentsQuery = TaxRegime::where('relation', 0);
        if ($activeOnly) {
            $parentsQuery->where('active', true);
        }
        $parents = $parentsQuery->orderBy('id')->get();

        // Obtener todos los hijos
        $childrenQuery = TaxRegime::where('relation', '>', 0)->with('store');
        if ($activeOnly) {
            $childrenQuery->where('active', true);
        }
        $children = $childrenQuery->orderBy('sat_code')->orderBy('name')->get();

        // Agrupar hijos por relation
        $childrenByRelation = $children->groupBy('relation');

        // Construir estructura jerárquica
        $grouped = $parents->map(function ($parent, $index) use ($childrenByRelation) {
            // Los padres tienen relation 0, pero los hijos están en relation 1 y 2
            // Persona física (id=1) -> relation 1
            // Persona moral (id=2) -> relation 2
            $relationKey = $index + 1; // 1 para el primer padre, 2 para el segundo

            return [
                'id' => $parent->id,
                'name' => $parent->name,
                'sat_code' => $parent->sat_code,
                'relation' => $parent->relation,
                'active' => $parent->active,
                'children' => $childrenByRelation->get($relationKey, collect())->map(function ($child) {
                    return [
                        'id' => $child->id,
                        'name' => $child->name,
                        'sat_code' => $child->sat_code,
                        'relation' => $child->relation,
                        'active' => $child->active,
                        'formatted_name' => $child->sat_code ? "{$child->sat_code} - {$child->name}" : $child->name,
                        'store_id' => $child->store_id,
                    ];
                })->values()
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Grouped tax regimes retrieved successfully',
            'data' => $grouped->values()
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/tax-regimes/{id}",
     *     tags={"Tax Regimes"},
     *     summary="Update tax regime",
     *     description="Updates an existing tax regime",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tax regime ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="sat_code", type="integer", description="SAT code", example=601),
     *             @OA\Property(property="name", type="string", maxLength=120, description="Regime name", example="General de Ley Personas Morales"),
     *             @OA\Property(property="relation", type="integer", description="Relation", example=1),
     *             @OA\Property(property="store_id", type="integer", description="Store ID", example=1),
     *             @OA\Property(property="active", type="boolean", description="Active status", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax regime updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax regime updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaxRegime")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Régimen fiscal no encontrado"),
     *     @OA\Response(response=422, description="Error de validación"),
     *     @OA\Response(response=401, description="No autorizado")
     * )
     */
    public function update(UpdateTaxRegimeRequest $request, TaxRegime $taxRegime): JsonResponse
    {
        $taxRegime->update($request->validated());
        $taxRegime->load('store');

        return response()->json([
            'success' => true,
            'message' => 'Tax regime updated successfully',
            'data' => $taxRegime
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/tax-regimes/{id}",
     *     tags={"Tax Regimes"},
     *     summary="Delete tax regime",
     *     description="Deletes a tax regime (soft delete)",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tax regime ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax regime deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax regime deleted successfully")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tax regime not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function destroy(TaxRegime $taxRegime): JsonResponse
    {
        $taxRegime->delete();

        return response()->json([
            'success' => true,
            'message' => 'Tax regime deleted successfully'
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/tax-regimes/{id}/toggle-status",
     *     tags={"Tax Regimes"},
     *     summary="Toggle active/inactive status",
     *     description="Changes the active/inactive status of a tax regime",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Tax regime ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status changed successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Tax regime status updated"),
     *             @OA\Property(property="data", ref="#/components/schemas/TaxRegime")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Tax regime not found"),
     *     @OA\Response(response=401, description="Unauthorized")
     * )
     */
    public function toggleStatus(TaxRegime $taxRegime): JsonResponse
    {
        $taxRegime->update(['active' => !$taxRegime->active]);
        $taxRegime->load('store');

        return response()->json([
            'success' => true,
            'message' => 'Tax regime status updated',
            'data' => $taxRegime
        ]);
    }
}
