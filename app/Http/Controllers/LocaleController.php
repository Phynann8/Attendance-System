<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    /**
     * Switch application locale (en | km).
     */
    public function switchLocale(Request $request, string $locale): RedirectResponse
    {
        if (in_array($locale, ['en', 'km'], true)) {
            session(['locale' => $locale]);
            app()->setLocale($locale);
        }

        return back();
    }
}
