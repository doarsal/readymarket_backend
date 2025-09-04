<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\CfdiUsage;
use App\Models\TaxRegime;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;

class CfdiUsageController extends Controller
{
    /**
     * Display a listing of all CFDI Usages.
     *
     * @OA\Get(
     *     path="/api/cfdi-usages",
     *     summary="Get all CFDI usages",
     *     tags={"CFDI Usages"},
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Filter by active status (1/0)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="physical",
     *         in="query",
     *         description="Filter by applies to physical person (1/0)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="moral",
     *         in="query",
     *         description="Filter by applies to moral person (1/0)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="tax_regime_code",
     *         in="query",
     *         description="Filter by compatible tax regime code",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="store_id",
     *         in="query",
     *         description="Filter by store ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of CFDI usages",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CfdiUsage")),
     *             @OA\Property(property="message", type="string", example="CFDI usages retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        try {
            $query = CfdiUsage::query();

            // Apply filters
            if ($request->has('active')) {
                $active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
                $query->where('active', $active);
            }

            if ($request->has('physical')) {
                $physical = filter_var($request->physical, FILTER_VALIDATE_BOOLEAN);
                $query->where('applies_to_physical', $physical);
            }

            if ($request->has('moral')) {
                $moral = filter_var($request->moral, FILTER_VALIDATE_BOOLEAN);
                $query->where('applies_to_moral', $moral);
            }

            if ($request->has('tax_regime_code')) {
                $query->compatibleWithTaxRegime($request->tax_regime_code);
            }

            if ($request->has('store_id')) {
                $query->where('store_id', $request->store_id);
            }

            $cfdiUsages = $query->get();

            return response()->json([
                'success' => true,
                'data' => $cfdiUsages,
                'message' => 'CFDI usages retrieved successfully'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CFDI usages',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Store a newly created CFDI Usage.
     *
     * @OA\Post(
     *     path="/api/cfdi-usages",
     *     summary="Create a new CFDI usage",
     *     tags={"CFDI Usages"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"code", "description", "applies_to_physical", "applies_to_moral"},
     *             @OA\Property(property="code", type="string", example="G03"),
     *             @OA\Property(property="description", type="string", example="Gastos en general"),
     *             @OA\Property(property="applies_to_physical", type="boolean", example=true),
     *             @OA\Property(property="applies_to_moral", type="boolean", example=true),
     *             @OA\Property(property="applicable_tax_regimes", type="array", @OA\Items(type="string"), example={"601", "603", "606"}),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="store_id", type="integer", example=1),
     *             @OA\Property(property="tax_regime_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="CFDI usage created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/CfdiUsage"),
     *             @OA\Property(property="message", type="string", example="CFDI usage created successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'code' => 'required|string|max:5|unique:cfdi_usages,code',
                'description' => 'required|string|max:255',
                'applies_to_physical' => 'required|boolean',
                'applies_to_moral' => 'required|boolean',
                'applicable_tax_regimes' => 'nullable|array',
                'applicable_tax_regimes.*' => 'string',
                'active' => 'boolean',
                'store_id' => 'nullable|exists:stores,id',
                'tax_regime_ids' => 'nullable|array',
                'tax_regime_ids.*' => 'exists:tax_regimes,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Create CFDI Usage
            $cfdiUsage = CfdiUsage::create([
                'code' => $request->code,
                'description' => $request->description,
                'applies_to_physical' => $request->applies_to_physical,
                'applies_to_moral' => $request->applies_to_moral,
                'applicable_tax_regimes' => $request->applicable_tax_regimes,
                'active' => $request->has('active') ? $request->active : true,
                'store_id' => $request->store_id ?? 1
            ]);

            // Attach tax regimes if provided
            if ($request->has('tax_regime_ids') && is_array($request->tax_regime_ids)) {
                foreach ($request->tax_regime_ids as $taxRegimeId) {
                    $cfdiUsage->taxRegimes()->attach($taxRegimeId, ['active' => true]);
                }
            }

            return response()->json([
                'success' => true,
                'data' => $cfdiUsage->load('taxRegimes'),
                'message' => 'CFDI usage created successfully'
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create CFDI usage',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified CFDI usage.
     *
     * @OA\Get(
     *     path="/api/cfdi-usages/{id}",
     *     summary="Get CFDI usage details",
     *     tags={"CFDI Usages"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="CFDI usage ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CFDI usage details",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/CfdiUsage"),
     *             @OA\Property(property="message", type="string", example="CFDI usage retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="CFDI usage not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $cfdiUsage = CfdiUsage::with('taxRegimes')->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $cfdiUsage,
                'message' => 'CFDI usage retrieved successfully'
            ], Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'CFDI usage not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CFDI usage',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update the specified CFDI usage.
     *
     * @OA\Put(
     *     path="/api/cfdi-usages/{id}",
     *     summary="Update CFDI usage",
     *     tags={"CFDI Usages"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="CFDI usage ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string", example="G03"),
     *             @OA\Property(property="description", type="string", example="Gastos en general"),
     *             @OA\Property(property="applies_to_physical", type="boolean", example=true),
     *             @OA\Property(property="applies_to_moral", type="boolean", example=true),
     *             @OA\Property(property="applicable_tax_regimes", type="array", @OA\Items(type="string"), example={"601", "603", "606"}),
     *             @OA\Property(property="active", type="boolean", example=true),
     *             @OA\Property(property="store_id", type="integer", example=1),
     *             @OA\Property(property="tax_regime_ids", type="array", @OA\Items(type="integer"), example={1, 2, 3})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CFDI usage updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/CfdiUsage"),
     *             @OA\Property(property="message", type="string", example="CFDI usage updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="CFDI usage not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $cfdiUsage = CfdiUsage::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'code' => 'string|max:5|unique:cfdi_usages,code,' . $id,
                'description' => 'string|max:255',
                'applies_to_physical' => 'boolean',
                'applies_to_moral' => 'boolean',
                'applicable_tax_regimes' => 'nullable|array',
                'applicable_tax_regimes.*' => 'string',
                'active' => 'boolean',
                'store_id' => 'nullable|exists:stores,id',
                'tax_regime_ids' => 'nullable|array',
                'tax_regime_ids.*' => 'exists:tax_regimes,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_BAD_REQUEST);
            }

            // Update CFDI Usage
            $cfdiUsage->update($request->only([
                'code',
                'description',
                'applies_to_physical',
                'applies_to_moral',
                'applicable_tax_regimes',
                'active',
                'store_id'
            ]));

            // Update tax regimes if provided
            if ($request->has('tax_regime_ids')) {
                $cfdiUsage->taxRegimes()->sync($request->tax_regime_ids);
            }

            return response()->json([
                'success' => true,
                'data' => $cfdiUsage->fresh()->load('taxRegimes'),
                'message' => 'CFDI usage updated successfully'
            ], Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'CFDI usage not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update CFDI usage',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified CFDI usage.
     *
     * @OA\Delete(
     *     path="/api/cfdi-usages/{id}",
     *     summary="Delete CFDI usage",
     *     tags={"CFDI Usages"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="CFDI usage ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="CFDI usage deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="CFDI usage deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="CFDI usage not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        try {
            $cfdiUsage = CfdiUsage::findOrFail($id);

            // Check if CFDI usage is being used
            if ($cfdiUsage->billingInformation()->count() > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete CFDI usage as it is being used by billing information'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Delete the tax regime relationships
            $cfdiUsage->taxRegimes()->detach();

            // Delete the CFDI usage
            $cfdiUsage->delete();

            return response()->json([
                'success' => true,
                'message' => 'CFDI usage deleted successfully'
            ], Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'CFDI usage not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete CFDI usage',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get CFDI usages by tax regime ID or code.
     *
     * @OA\Get(
     *     path="/api/tax-regimes/{id_or_code}/cfdi-usages",
     *     summary="Get CFDI usages by tax regime ID or code",
     *     tags={"CFDI Usages", "Tax Regimes"},
     *     @OA\Parameter(
     *         name="id_or_code",
     *         in="path",
     *         description="Tax regime ID or SAT code",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Filter by active status (1/0)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of CFDI usages for the specified tax regime",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CfdiUsage")),
     *             @OA\Property(property="message", type="string", example="CFDI usages retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax regime not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * @param  string  $idOrCode
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getByTaxRegime($idOrCode, Request $request)
    {
        try {
            // Check if the parameter is a numeric ID or a string code
            if (is_numeric($idOrCode)) {
                $taxRegime = TaxRegime::findOrFail($idOrCode);
            } else {
                $taxRegime = TaxRegime::where('sat_code', $idOrCode)->firstOrFail();
            }

            // Get CFDI usages for this tax regime
            $query = $taxRegime->cfdiUsages();

            // Apply filters
            if ($request->has('active')) {
                $active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
                $query->wherePivot('active', $active);
            }

            $cfdiUsages = $query->get();

            return response()->json([
                'success' => true,
                'data' => $cfdiUsages,
                'message' => 'CFDI usages retrieved successfully'
            ], Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Tax regime not found'
            ], Response::HTTP_NOT_FOUND);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CFDI usages',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get CFDI usages by person type (physical or moral).
     *
     * @OA\Get(
     *     path="/api/cfdi-usages/by-person-type/{type}",
     *     summary="Get CFDI usages by person type",
     *     tags={"CFDI Usages"},
     *     @OA\Parameter(
     *         name="type",
     *         in="path",
     *         description="Person type (physical or moral)",
     *         required=true,
     *         @OA\Schema(type="string", enum={"physical", "moral"})
     *     ),
     *     @OA\Parameter(
     *         name="active",
     *         in="query",
     *         description="Filter by active status (1/0)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="List of CFDI usages for the specified person type",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="array", @OA\Items(ref="#/components/schemas/CfdiUsage")),
     *             @OA\Property(property="message", type="string", example="CFDI usages retrieved successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Bad request - invalid person type"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * @param  string  $type
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getByPersonType($type, Request $request)
    {
        try {
            if (!in_array($type, ['physical', 'moral'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid person type. Must be "physical" or "moral".'
                ], Response::HTTP_BAD_REQUEST);
            }

            $query = CfdiUsage::query();

            // Apply person type filter
            if ($type === 'physical') {
                $query->where('applies_to_physical', true);
            } else { // moral
                $query->where('applies_to_moral', true);
            }

            // Apply active filter if provided
            if ($request->has('active')) {
                $active = filter_var($request->active, FILTER_VALIDATE_BOOLEAN);
                $query->where('active', $active);
            }

            $cfdiUsages = $query->get();

            return response()->json([
                'success' => true,
                'data' => $cfdiUsages,
                'message' => 'CFDI usages retrieved successfully'
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve CFDI usages',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Toggle active status of a CFDI Usage.
     *
     * @OA\Post(
     *     path="/api/cfdi-usages/{id}/toggle-status",
     *     summary="Toggle active status of CFDI usage",
     *     tags={"CFDI Usages"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="CFDI Usage ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Status toggled successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/CfdiUsage"),
     *             @OA\Property(property="message", type="string", example="CFDI usage status updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="CFDI usage not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function toggleStatus($id)
    {
        try {
            $cfdiUsage = CfdiUsage::findOrFail($id);

            $cfdiUsage->active = !$cfdiUsage->active;
            $cfdiUsage->save();

            return response()->json([
                'success' => true,
                'data' => $cfdiUsage,
                'message' => 'CFDI usage status updated successfully'
            ], Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'CFDI usage not found'
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update CFDI usage status',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Synchronize tax regimes for a CFDI Usage.
     *
     * @OA\Post(
     *     path="/api/cfdi-usages/{id}/sync-tax-regimes",
     *     summary="Sync tax regimes with CFDI usage",
     *     tags={"CFDI Usages"},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="CFDI Usage ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(
     *                 property="tax_regime_ids",
     *                 type="array",
     *                 @OA\Items(type="integer"),
     *                 description="Array of tax regime IDs to sync",
     *                 example={1, 2, 3}
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax regimes synced successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", ref="#/components/schemas/CfdiUsage"),
     *             @OA\Property(property="message", type="string", example="Tax regimes synced successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="CFDI usage not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function syncTaxRegimes(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'tax_regime_ids' => 'required|array',
                'tax_regime_ids.*' => 'exists:tax_regimes,id'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], Response::HTTP_BAD_REQUEST);
            }

            $cfdiUsage = CfdiUsage::findOrFail($id);

            // Sync tax regimes with pivot data
            $syncData = [];
            foreach ($request->tax_regime_ids as $taxRegimeId) {
                $syncData[$taxRegimeId] = [
                    'active' => true,
                    'created_at' => now(),
                    'updated_at' => now()
                ];
            }

            $cfdiUsage->taxRegimes()->sync($syncData);

            // Load the updated relationships
            $cfdiUsage->load('taxRegimes');

            return response()->json([
                'success' => true,
                'data' => $cfdiUsage,
                'message' => 'Tax regimes synced successfully'
            ], Response::HTTP_OK);

        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'CFDI usage not found'
            ], Response::HTTP_NOT_FOUND);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to sync tax regimes',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
