<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        $user = Auth::guard('admin')->user();

        if (! $user) {
            return redirect()->route('admin.login');
        }

        if ($permission === 'admin_users.manage' && $user->canManageAdminUsers()) {
            return $next($request);
        }

        if (! $user->hasAdminPermission($permission)) {
            abort(403);
        }

        return $next($request);
    }
}
