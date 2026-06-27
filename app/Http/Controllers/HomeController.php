<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Channel;
use App\Models\Movie;
use App\Models\Package;
use App\Models\Series;

class HomeController extends Controller
{
    public function index()
    {
        return view('home', [
            'packages' => Package::active()->orderBy('price')->get(),
            'featuredChannels' => Channel::active()->with('category')->latest()->take(12)->get(),
            'movies' => Movie::active()->latest()->take(6)->get(),
            'series' => Series::active()->latest()->take(6)->get(),
            'categoryCount' => Category::count(),
            'channelCount' => Channel::active()->count(),
            'movieCount' => Movie::active()->count(),
        ]);
    }
}
