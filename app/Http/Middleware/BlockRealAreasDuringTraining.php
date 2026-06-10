<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockRealAreasDuringTraining
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('admin')?->training_mode_active) {
            return redirect()
                ->route('admin.training.index')
                ->with('error', 'Questa sezione contiene dati reali ed è disabilitata durante la formazione.');
        }

        return $next($request);
    }
}
