<?php

namespace App\Http\Controllers;

class ChannelController extends Controller
{
    public function index()
    {
        // The interactive listing is rendered by the Livewire ChannelBrowser
        // component embedded in the view, giving live search & filtering.
        return view('channels.index');
    }
}
