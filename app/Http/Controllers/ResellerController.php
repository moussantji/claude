<?php

namespace App\Http\Controllers;

class ResellerController extends Controller
{
    public function index()
    {
        // Reseller credit tiers (1 credit = 1 month / 1 line).
        $tiers = [
            ['credits' => 10, 'price' => 25000, 'unit' => 2500],
            ['credits' => 25, 'price' => 55000, 'unit' => 2200, 'popular' => true],
            ['credits' => 50, 'price' => 100000, 'unit' => 2000],
            ['credits' => 100, 'price' => 180000, 'unit' => 1800],
        ];

        return view('resellers.index', ['tiers' => $tiers]);
    }
}
