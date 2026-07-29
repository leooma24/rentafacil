<?php

namespace App\Http\Controllers;

use App\Services\DemoCompanyBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    public function index()
    {
        return view('demo.preparando');
    }

    public function create(Request $request, DemoCompanyBuilder $builder)
    {
        // Quien pide un segundo demo trae la sesión del anterior, cuyo usuario
        // pudo haber sido borrado ya por demo:cleanup. Sin limpiarla, la
        // redirección al panel cae en el middleware de autenticación y revienta.
        Auth::logout();
        $request->session()->invalidate();

        $company = $builder->build();

        // Explícitamente el dueño y no "el primero de la lista": el demo ahora
        // trae también un cobrador, y entrar como él le escondería al visitante
        // los reportes, los precios y el plan, que es justo lo que viene a ver.
        $dueno = $company->members()
            ->whereHas('roles', fn ($query) => $query->where('name', 'propietario'))
            ->first() ?? $company->members()->first();

        Auth::login($dueno);
        $request->session()->regenerate();

        return response()->json([
            'url' => url("/propietario/{$company->id}"),
        ]);
    }
}
