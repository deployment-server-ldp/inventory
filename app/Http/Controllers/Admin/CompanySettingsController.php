<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CompanySettingsController extends Controller
{
    public function edit(): View
    {
        return view('admin.company', ['values' => collect(AppSetting::DEFAULTS)->map(fn ($d, $k) => AppSetting::get($k))]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:120'],
            'company_address' => ['nullable', 'string', 'max:255'],
            'default_currency' => ['required', Rule::in(config('spims.currencies'))],
        ]);
        $old = [];
        foreach ($data as $k => $v) {
            $old[$k] = AppSetting::get($k);
            AppSetting::put($k, $v);
        }
        ActivityLogger::log('settings.company', 'Company settings updated', null, $old, $data, 'settings');

        return back()->with('success', 'Settings saved.');
    }
}
