<?php

namespace App\Http\Controllers;

use App\Models\Package;

class PackageController extends Controller
{
    public function index()
    {
        return view('packages.index', [
            'packages' => Package::active()->orderBy('price')->get(),
        ]);
    }
}
