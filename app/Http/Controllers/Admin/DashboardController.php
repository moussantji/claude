<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Channel;
use App\Models\Movie;
use App\Models\Order;
use App\Models\Package;
use App\Models\Series;
use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'userCount' => User::count(),
            'movieCount' => Movie::count(),
            'seriesCount' => Series::count(),
            'channelCount' => Channel::count(),
            'packageCount' => Package::count(),
            'pendingOrders' => Order::whereIn('status', ['pending', 'processing'])->count(),
            'revenue' => Order::completed()->sum('amount'),
            'recentOrders' => Order::with(['user'])->latest()->take(8)->get(),
        ]);
    }
}
