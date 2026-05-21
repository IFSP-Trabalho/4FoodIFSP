<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string $role): Response
    {
        $user = Auth::user();

        $allowed = explode('|', $role);

        if (! $user || ! in_array($user->role, $allowed, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Acesso negado para este perfil.');
        }

        return $next($request);
    }
}
