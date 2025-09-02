<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Languages",
 *     description="API Endpoints for Languages Management"
 * )
 */
class LanguageController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/languages",
     *     tags={"Languages"},
     *     summary="Get all languages",
     *     description="Returns list of all languages",
     *     @OA\Response(
     *         response=200,
     *         description="Languages retrieved successfully"
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $languages = Language::where('is_active', true)
                            ->orderBy('sort_order')
                            ->orderBy('name')
                            ->get();

        return response()->json([
            'success' => true,
            'data' => $languages
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/languages",
     *     tags={"Languages"},
     *     summary="Create new language",
     *     description="Creates a new language",
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "code", "locale"},
     *             @OA\Property(property="name", type="string", example="Español"),
     *             @OA\Property(property="code", type="string", example="es"),
     *             @OA\Property(property="locale", type="string", example="es_MX")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Language created successfully"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'code' => 'required|string|max:5|unique:languages,code',
            'locale' => 'required|string|max:10',
            'flag_icon' => 'nullable|string|max:255',
            'is_rtl' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer'
        ]);

        $language = Language::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Language created successfully',
            'data' => $language
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/languages/{id}",
     *     tags={"Languages"},
     *     summary="Get language by ID",
     *     description="Returns a single language",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Language ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Language retrieved successfully"
     *     )
     * )
     */
    public function show(Language $language): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $language
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/languages/{id}",
     *     tags={"Languages"},
     *     summary="Update language",
     *     description="Updates an existing language",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Language ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Language updated successfully"
     *     )
     * )
     */
    public function update(Request $request, Language $language): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'string|max:100',
            'code' => 'string|max:5|unique:languages,code,' . $language->id,
            'locale' => 'string|max:10',
            'flag_icon' => 'nullable|string|max:255',
            'is_rtl' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer'
        ]);

        $language->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Language updated successfully',
            'data' => $language
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/languages/{id}",
     *     tags={"Languages"},
     *     summary="Delete language",
     *     description="Deletes a language",
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Language ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Language deleted successfully"
     *     )
     * )
     */
    public function destroy(Language $language): JsonResponse
    {
        $language->delete();

        return response()->json([
            'success' => true,
            'message' => 'Language deleted successfully'
        ]);
    }
}
