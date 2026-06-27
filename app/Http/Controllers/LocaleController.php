<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function switch(Request $request, string $locale)
    {
        if (in_array($locale, config('streamwave.locales', ['en']), true)) {
            $request->session()->put('locale', $locale);
        }

        return redirect()->back();
    }
}
