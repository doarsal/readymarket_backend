<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PostalCode;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Postal Codes",
 *     description="API endpoints for postal code management and autocomplete"
 * )
 */
class PostalCodeController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/postal-codes",
     *     summary="Get all postal codes",
     *     description="Retrieve paginated list of postal codes with optional search and filtering",
     *     tags={"Postal Codes"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="page",
     *         in="query",
     *         description="Page number",
     *         required=false,
     *         @OA\Schema(type="integer", example=1)
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Items per page",
     *         required=false,
     *         @OA\Schema(type="integer", example=15)
     *     ),
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search in postal code, city, state, or country",
     *         required=false,
     *         @OA\Schema(type="string", example="44100")
     *     ),
     *     @OA\Parameter(
     *         name="country",
     *         in="query",
     *         description="Filter by country code",
     *         required=false,
     *         @OA\Schema(type="string", example="MX")
     *     ),
     *     @OA\Parameter(
     *         name="state",
     *         in="query",
     *         description="Filter by state",
     *         required=false,
     *         @OA\Schema(type="string", example="JAL")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Postal codes retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Postal codes retrieved successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(property="per_page", type="integer", example=15),
     *                 @OA\Property(property="total", type="integer", example=31514),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(
     *                         @OA\Property(property="idpostalcode", type="integer", example=1),
     *                         @OA\Property(property="pc_postalcode", type="string", example="44100"),
     *                         @OA\Property(property="pc_city", type="string", example="Guadalajara"),
     *                         @OA\Property(property="pc_state", type="string", example="JAL"),
     *                         @OA\Property(property="pc_statelarge", type="string", example="Jalisco"),
     *                         @OA\Property(property="pc_countrycode", type="string", example="MX"),
     *                         @OA\Property(property="pc_countrylarge", type="string", example="México"),
     *                         @OA\Property(property="formatted_address", type="string", example="Guadalajara, Jalisco, México")
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $perPage = $request->get('per_page', 15);
            $search = $request->get('search');
            $country = $request->get('country');
            $state = $request->get('state');

            $query = PostalCode::query();

            if ($search) {
                $query->search($search);
            }

            if ($country) {
                $query->byCountry($country);
            }

            if ($state) {
                $query->byState($state);
            }

            $postalCodes = $query->orderBy('pc_postalcode')->paginate($perPage);

            return response()->json([
                'success' => true,
                'message' => 'Postal codes retrieved successfully',
                'data' => $postalCodes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving postal codes',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/postal-codes",
     *     summary="Create new postal code",
     *     description="Create a new postal code entry",
     *     tags={"Postal Codes"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"pc_postalcode", "pc_city", "pc_state", "pc_countrycode", "pc_countrylarge"},
     *             @OA\Property(property="pc_postalcode", type="string", example="44100"),
     *             @OA\Property(property="pc_city", type="string", example="Guadalajara"),
     *             @OA\Property(property="pc_state", type="string", example="JAL"),
     *             @OA\Property(property="pc_countrycode", type="string", example="MX"),
     *             @OA\Property(property="pc_statelarge", type="string", example="Jalisco"),
     *             @OA\Property(property="pc_countrylarge", type="string", example="México"),
     *             @OA\Property(property="pc_culture", type="string", example="es-MX"),
     *             @OA\Property(property="pc_lang", type="string", example="es")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Postal code created successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'pc_postalcode' => 'required|string|max:10|unique:postalcodes,pc_postalcode',
                'pc_city' => 'required|string|max:255',
                'pc_state' => 'required|string|max:255',
                'pc_countrycode' => 'required|string|max:10',
                'pc_statelarge' => 'nullable|string|max:255',
                'pc_countrylarge' => 'required|string|max:255',
                'pc_culture' => 'nullable|string|max:10',
                'pc_lang' => 'nullable|string|max:10'
            ]);

            $postalCode = PostalCode::create($validated);

            return response()->json([
                'success' => true,
                'message' => 'Postal code created successfully',
                'data' => $postalCode
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error creating postal code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/postal-codes/{id}",
     *     summary="Get postal code by ID",
     *     description="Retrieve a specific postal code by its ID",
     *     tags={"Postal Codes"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Postal code ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Postal code retrieved successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Postal code not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function show(string $id): JsonResponse
    {
        try {
            $postalCode = PostalCode::findOrFail($id);

            return response()->json([
                'success' => true,
                'message' => 'Postal code retrieved successfully',
                'data' => $postalCode
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Postal code not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * @OA\Put(
     *     path="/api/v1/postal-codes/{id}",
     *     summary="Update postal code",
     *     description="Update an existing postal code",
     *     tags={"Postal Codes"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Postal code ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="pc_postalcode", type="string", example="44100"),
     *             @OA\Property(property="pc_city", type="string", example="Guadalajara"),
     *             @OA\Property(property="pc_state", type="string", example="JAL"),
     *             @OA\Property(property="pc_countrycode", type="string", example="MX"),
     *             @OA\Property(property="pc_statelarge", type="string", example="Jalisco"),
     *             @OA\Property(property="pc_countrylarge", type="string", example="México")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Postal code updated successfully"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Postal code not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $postalCode = PostalCode::findOrFail($id);

            $validated = $request->validate([
                'pc_postalcode' => 'sometimes|required|string|max:10|unique:postalcodes,pc_postalcode,' . $id . ',idpostalcode',
                'pc_city' => 'sometimes|required|string|max:255',
                'pc_state' => 'sometimes|required|string|max:255',
                'pc_countrycode' => 'sometimes|required|string|max:10',
                'pc_statelarge' => 'sometimes|nullable|string|max:255',
                'pc_countrylarge' => 'sometimes|required|string|max:255',
                'pc_culture' => 'sometimes|nullable|string|max:10',
                'pc_lang' => 'sometimes|nullable|string|max:10'
            ]);

            $postalCode->update($validated);

            return response()->json([
                'success' => true,
                'message' => 'Postal code updated successfully',
                'data' => $postalCode->fresh()
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error updating postal code',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/postal-codes/{id}",
     *     summary="Delete postal code",
     *     description="Delete a postal code entry",
     *     tags={"Postal Codes"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Postal code ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Postal code deleted successfully"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Postal code not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthorized"
     *     )
     * )
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $postalCode = PostalCode::findOrFail($id);
            $postalCode->delete();

            return response()->json([
                'success' => true,
                'message' => 'Postal code deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error deleting postal code',
                'error' => $e->getMessage()
            ], 404);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/postal-codes/search/{code}",
     *     summary="Search postal code (Public)",
     *     description="Public endpoint to search for postal code information by code",
     *     tags={"Postal Codes"},
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Postal code to search",
     *         required=true,
     *         @OA\Schema(type="string", example="44100")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Postal code found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Postal code found"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="idpostalcode", type="integer", example=1),
     *                     @OA\Property(property="pc_postalcode", type="string", example="44100"),
     *                     @OA\Property(property="pc_city", type="string", example="Guadalajara"),
     *                     @OA\Property(property="pc_state", type="string", example="JAL"),
     *                     @OA\Property(property="pc_statelarge", type="string", example="Jalisco"),
     *                     @OA\Property(property="pc_countrycode", type="string", example="MX"),
     *                     @OA\Property(property="pc_countrylarge", type="string", example="México"),
     *                     @OA\Property(property="formatted_address", type="string", example="Guadalajara, Jalisco, México")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Postal code not found"
     *     )
     * )
     */
    public function searchByCode(string $code): JsonResponse
    {
        try {
            $postalCodes = PostalCode::byCode($code)->get();

            if ($postalCodes->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Postal code not found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Postal code found',
                'data' => $postalCodes
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error searching postal code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/postal-codes/autocomplete",
     *     summary="Autocomplete postal codes (Public)",
     *     description="Public endpoint for postal code autocomplete functionality",
     *     tags={"Postal Codes"},
     *     @OA\Parameter(
     *         name="q",
     *         in="query",
     *         description="Search query (postal code, city, state, or country)",
     *         required=true,
     *         @OA\Schema(type="string", example="44100")
     *     ),
     *     @OA\Parameter(
     *         name="limit",
     *         in="query",
     *         description="Maximum number of results",
     *         required=false,
     *         @OA\Schema(type="integer", example=10)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Autocomplete results",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Autocomplete results"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     @OA\Property(property="idpostalcode", type="integer", example=1),
     *                     @OA\Property(property="pc_postalcode", type="string", example="44100"),
     *                     @OA\Property(property="pc_city", type="string", example="Guadalajara"),
     *                     @OA\Property(property="pc_state", type="string", example="JAL"),
     *                     @OA\Property(property="pc_statelarge", type="string", example="Jalisco"),
     *                     @OA\Property(property="pc_countrycode", type="string", example="MX"),
     *                     @OA\Property(property="pc_countrylarge", type="string", example="México"),
     *                     @OA\Property(property="formatted_address", type="string", example="Guadalajara, Jalisco, México")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid request"
     *     )
     * )
     */
    public function autocomplete(Request $request): JsonResponse
    {
        try {
            $query = $request->get('q');
            $limit = $request->get('limit', 10);

            if (!$query || strlen($query) < 2) {
                return response()->json([
                    'success' => false,
                    'message' => 'Query must be at least 2 characters',
                    'data' => []
                ], 400);
            }

            // Cache por 2 horas - códigos postales no cambian frecuentemente
            $cacheKey = "postal_autocomplete_" . md5($query . "_" . $limit);
            $cacheTime = 7200; // 2 horas

            $results = Cache::remember($cacheKey, $cacheTime, function () use ($query, $limit) {
                return PostalCode::search($query)
                    ->limit($limit)
                    ->get();
            });

            return response()->json([
                'success' => true,
                'message' => 'Autocomplete results',
                'data' => $results
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error in autocomplete search',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Get(
     *     path="/api/v1/postal-codes/address/{code}",
     *     summary="Get formatted address by postal code (Public)",
     *     description="Public endpoint to get formatted address information for autocomplete forms",
     *     tags={"Postal Codes"},
     *     @OA\Parameter(
     *         name="code",
     *         in="path",
     *         description="Postal code",
     *         required=true,
     *         @OA\Schema(type="string", example="44100")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address information found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Address information found"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="postal_code", type="string", example="44100"),
     *                 @OA\Property(property="city", type="string", example="Guadalajara"),
     *                 @OA\Property(property="state", type="string", example="Jalisco"),
     *                 @OA\Property(property="country", type="string", example="México"),
     *                 @OA\Property(property="country_code", type="string", example="MX"),
     *                 @OA\Property(property="formatted_address", type="string", example="Guadalajara, Jalisco, México"),
     *                 @OA\Property(
     *                     property="neighborhoods",
     *                     type="array",
     *                     @OA\Items(type="string", example="Guadalajara")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Address not found"
     *     )
     * )
     */
    public function getAddressByCode(string $code): JsonResponse
    {
        try {
            $address = PostalCode::getAddressInfo($code);

            if (!$address) {
                return response()->json([
                    'success' => false,
                    'message' => 'Address not found for postal code',
                    'data' => null
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Address information found',
                'data' => $address
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving address information',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
