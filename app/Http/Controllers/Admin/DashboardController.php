<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Movie;
use App\Models\Package;
use App\Models\Series;
use App\Models\Subscription;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $activeSubs = Subscription::where('status', 'active')->where('ends_at', '>', now());

        return view('admin.dashboard', [
            'userCount' => User::count(),
            'channelCount' => Channel::count(),
            'movieCount' => Movie::count(),
            'seriesCount' => Series::count(),
            'packageCount' => Package::count(),
            'activeSubCount' => (clone $activeSubs)->count(),
            'revenue' => Subscription::sum('amount_paid'),
            'recentSubs' => Subscription::with(['user', 'package'])->latest()->take(8)->get(),
        ]);
    }
}
