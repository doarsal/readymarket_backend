<?php

namespace App\Http\Middleware\RolesAndPermissions;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class HasRolesMiddleware
{
    use Authorized;

    public function handle(Request $request, Closure $next, string|array ...$roles): Response
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user) {
            return $this->unauthorized($request);
        }

        if (!$user->hasRoles($this->makeArray($roles))) {
            return $this->unauthorized($request);
        }

        return $next($request);
    }
}
