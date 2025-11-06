<?php

namespace App\Http\Middleware\RolesAndPermissions;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

trait Authorized
{
    public function makeArray(array|string $data): array
    {
        return is_array($data) ? $data : explode(',', $data);
    }

    public function unAuthorized(Request $request): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado.',
            ], 401);
        }

        return redirect()->route('login')->with('error', 'Acceso restringido');
    }
}
