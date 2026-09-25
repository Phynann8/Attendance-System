<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\SchoolSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SchoolSettingController extends Controller
{
    public function index(): View
    {
        $settings = SchoolSetting::all()->groupBy('group');

        return view('super_admin.settings.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'settings' => ['required', 'array'],
            'settings.*' => ['nullable', 'string', 'max:500'],
        ]);

        foreach ($validated['settings'] as $key => $value) {
            SchoolSetting::set($key, $value);
        }

        return back()->with('success', 'School settings and branding updated successfully. Cached in Redis.');
    }
}
