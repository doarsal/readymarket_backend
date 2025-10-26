<?php

namespace App\Http\Middleware\RolesAndPermissions;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AnyPermissionMiddleware
{
    use Authorized;

    public function handle(Request $request, Closure $next, string|array ...$permissions): Response
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized($request);
        }

        if (!$user->hasAnyPermission($this->makeArray($permissions))) {
            return $this->unauthorized($request);
        }

        return $next($request);
    }
}
