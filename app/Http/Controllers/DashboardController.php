<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $active = $user->activeSubscription();

        return view('dashboard.index', [
            'user' => $user,
            'active' => $active,
            'history' => $user->subscriptions()->with('package')->latest()->get(),
            'channelCount' => Channel::active()->count(),
        ]);
    }
}
