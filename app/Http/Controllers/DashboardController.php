<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        return view('dashboard.index', [
            'user' => $user,
            'active' => $user->latestCompletedOrder(),
            'orders' => $user->orders()->with('package')->take(6)->get(),
            'pendingCount' => $user->orders()->whereIn('status', ['pending', 'processing'])->count(),
        ]);
    }
}
