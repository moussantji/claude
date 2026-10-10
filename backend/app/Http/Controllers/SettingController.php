<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\Lead;
use App\Models\Order;
use App\Models\Post;
use App\Models\Product;
use App\Models\Project;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private function current(): Setting
    {
        return Setting::firstOrCreate(['id' => 1], [
            'company' => 'Manden Baoubab',
            'email' => 'contact@mandenbaoubab.com',
            'phone' => '+223 20 00 00 00',
            'address' => 'ACI 2000, Bamako - Mali',
            'currency' => 'FCFA',
            'slogan' => 'Le numerique qui fait grandir votre entreprise',
        ]);
    }

    public function show(): JsonResponse
    {
        return response()->json($this->current());
    }

    public function update(Request $request): JsonResponse
    {
        $setting = $this->current();
        $setting->update($request->only([
            'company', 'email', 'phone', 'address', 'currency', 'slogan',
        ]));

        return response()->json($setting);
    }

    /**
     * Indicateurs pour le tableau de bord.
     */
    public function stats(): JsonResponse
    {
        return response()->json([
            'revenue' => (int) Order::sum('amount'),
            'leads' => Lead::count(),
            'clients' => Client::count(),
            'subscribers' => (int) Product::sum('subscribers'),
            'active_projects' => Project::where('status', 'En cours')->count(),
            'orders' => Order::count(),
            'published_posts' => Post::where('status', 'Publie')->count(),
        ]);
    }
}
