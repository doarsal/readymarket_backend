<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Translation;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Translations",
 *     description="API Endpoints for Translations Management"
 * )
 */
class TranslationController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v1/translations",
     *     tags={"Translations"},
     *     summary="Get all translations",
     *     description="Returns list of translations with filters",
     *     @OA\Parameter(
     *         name="language_id",
     *         in="query",
     *         description="Filter by language ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="key",
     *         in="query",
     *         description="Filter by translation key",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Translations retrieved successfully"
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Translation::query();

            if ($request->has('language_id')) {
                $query->where('language_id', $request->language_id);
            }

            if ($request->has('key')) {
                $query->where('field', 'like', '%' . $request->key . '%');
            }

            $translations = $query->paginate(50);

            return response()->json([
                'success' => true,
                'data' => $translations->items(),
                'pagination' => [
                    'current_page' => $translations->currentPage(),
                    'last_page' => $translations->lastPage(),
                    'per_page' => $translations->perPage(),
                    'total' => $translations->total()
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading translations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * @OA\Post(
     *     path="/api/v1/translations",
     *     tags={"Translations"},
     *     summary="Create new translation",
     *     description="Creates a new translation",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"language_id", "key", "value"},
     *             @OA\Property(property="language_id", type="integer", example=1),
     *             @OA\Property(property="key", type="string", example="welcome_message"),
     *             @OA\Property(property="value", type="string", example="Bienvenido")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Translation created successfully"
     *     )
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language_id' => 'required|exists:languages,id',
            'key' => 'required|string|max:255',
            'value' => 'required|string'
        ]);

        // Check if translation already exists
        $existing = Translation::where('language_id', $validated['language_id'])
                              ->where('key', $validated['key'])
                              ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'Translation already exists for this language and key'
            ], 409);
        }

        $translation = Translation::create($validated);
        $translation->load('language');

        return response()->json([
            'success' => true,
            'message' => 'Translation created successfully',
            'data' => $translation
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/translations/{id}",
     *     tags={"Translations"},
     *     summary="Get translation by ID",
     *     description="Returns a single translation",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Translation ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Translation retrieved successfully"
     *     )
     * )
     */
    public function show(Translation $translation): JsonResponse
    {
        $translation->load('language');

        return response()->json([
            'success' => true,
            'data' => $translation
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/v1/translations/{id}",
     *     tags={"Translations"},
     *     summary="Update translation",
     *     description="Updates an existing translation",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Translation ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Translation updated successfully"
     *     )
     * )
     */
    public function update(Request $request, Translation $translation): JsonResponse
    {
        $validated = $request->validate([
            'language_id' => 'exists:languages,id',
            'key' => 'string|max:255',
            'value' => 'string'
        ]);

        // Check for duplicate if key or language changes
        if (isset($validated['language_id']) || isset($validated['key'])) {
            $languageId = $validated['language_id'] ?? $translation->language_id;
            $key = $validated['key'] ?? $translation->key;

            $existing = Translation::where('language_id', $languageId)
                                  ->where('key', $key)
                                  ->where('id', '!=', $translation->id)
                                  ->first();

            if ($existing) {
                return response()->json([
                    'success' => false,
                    'message' => 'Translation already exists for this language and key'
                ], 409);
            }
        }

        $translation->update($validated);
        $translation->load('language');

        return response()->json([
            'success' => true,
            'message' => 'Translation updated successfully',
            'data' => $translation
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/v1/translations/{id}",
     *     tags={"Translations"},
     *     summary="Delete translation",
     *     description="Deletes a translation",
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Translation ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Translation deleted successfully"
     *     )
     * )
     */
    public function destroy(Translation $translation): JsonResponse
    {
        $translation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Translation deleted successfully'
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/v1/translations/by-language/{languageCode}",
     *     tags={"Translations"},
     *     summary="Get translations by language code",
     *     description="Returns all translations for a specific language",
     *     @OA\Parameter(
     *         name="languageCode",
     *         in="path",
     *         description="Language code (e.g., es, en, pt)",
     *         required=true,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Translations retrieved successfully"
     *     )
     * )
     */
    public function getByLanguage(string $languageCode): JsonResponse
    {
        $language = Language::where('code', $languageCode)->first();

        if (!$language) {
            return response()->json([
                'success' => false,
                'message' => 'Language not found'
            ], 404);
        }

        $translations = Translation::where('language_id', $language->id)
                                  ->orderBy('field')
                                  ->get()
                                  ->keyBy('field');

        return response()->json([
            'success' => true,
            'data' => [
                'language' => $language,
                'translations' => $translations
            ]
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/v1/translations/bulk",
     *     tags={"Translations"},
     *     summary="Bulk create or update translations",
     *     description="Creates or updates multiple translations at once",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"language_id", "translations"},
     *             @OA\Property(property="language_id", type="integer", example=1),
     *             @OA\Property(property="translations", type="object", example={"welcome": "Bienvenido", "goodbye": "Adiós"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Translations processed successfully"
     *     )
     * )
     */
    public function bulkStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language_id' => 'required|exists:languages,id',
            'translations' => 'required|array',
            'translations.*' => 'required|string'
        ]);

        $created = 0;
        $updated = 0;
        $errors = [];

        foreach ($validated['translations'] as $key => $value) {
            try {
                $translation = Translation::updateOrCreate(
                    [
                        'language_id' => $validated['language_id'],
                        'key' => $key
                    ],
                    ['value' => $value]
                );

                if ($translation->wasRecentlyCreated) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (\Exception $e) {
                $errors[] = "Failed to process key '{$key}': " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Bulk operation completed',
            'data' => [
                'created' => $created,
                'updated' => $updated,
                'errors' => $errors
            ]
        ]);
    }
}
