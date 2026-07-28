<?php

namespace App\Http\Controllers;

use App\Services\DemoCompanyBuilder;
use Illuminate\Support\Facades\Auth;

class DemoController extends Controller
{
    public function index()
    {
        return view('demo.preparando');
    }

    public function create(DemoCompanyBuilder $builder)
    {
        $company = $builder->build();

        Auth::login($company->members()->first());

        return response()->json([
            'url' => url("/propietario/{$company->id}"),
        ]);
    }
}
