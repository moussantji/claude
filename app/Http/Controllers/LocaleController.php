<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale)
    {
        if (in_array($locale, config('streamwave.locales', ['en']), true)) {
            // Always keep it in the session for guests.
            $request->session()->put('locale', $locale);

            // Persist on the account for logged-in users so it follows them
            // across devices and sessions.
            if (Auth::check()) {
                Auth::user()->update(['locale' => $locale]);
            }
        }

        return redirect()->back();
    }
}
