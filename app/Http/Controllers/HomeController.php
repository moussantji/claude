<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Package;

class HomeController extends Controller
{
    public function index()
    {
        return view('home', [
            'packages' => Package::active()->orderBy('price')->get(),
            'featuredChannels' => Channel::active()->with('category')->latest()->take(12)->get(),
            'categoryCount' => Category::count(),
            'channelCount' => Channel::active()->count(),
        ]);
    }
}
