<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/**
 * Secure first-time administrator setup (for hosts without SSH).
 * Works ONLY while the users table is empty AND a SPIMS_SETUP_TOKEN is configured in .env,
 * and the submitted token must match it. After the first admin exists the page returns 404.
 */
class SetupController extends Controller
{
    public function show(): View
    {
        $this->guard();

        return view('auth.setup', ['tokenConfigured' => filled(config('spims.setup_token'))]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->guard();
        $data = $request->validate([
            'setup_token' => ['required', 'string'],
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->mixedCase()->numbers()],
        ]);

        $expected = (string) config('spims.setup_token');
        if ($expected === '' || ! hash_equals($expected, $data['setup_token'])) {
            return back()->withInput($request->except('password', 'password_confirmation', 'setup_token'))
                ->withErrors(['setup_token' => 'Invalid setup token.']);
        }

        $user = DB::transaction(function () use ($data) {
            if (User::query()->lockForUpdate()->exists()) {
                abort(404);
            }
            if (! Role::where('name', Role::SUPER_ADMIN)->exists()) {
                (new DatabaseSeeder)->run();
            }

            return User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'] ?? null,
                'password' => $data['password'],
                'role_id' => Role::where('name', Role::SUPER_ADMIN)->value('id'),
                'is_active' => true,
            ]);
        });

        ActivityLogger::log('setup.admin_created', "First Super Admin '{$user->username}' created via web setup", $user, module: 'admin', user: $user);
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', 'Administrator account created. Remove SPIMS_SETUP_TOKEN from your .env file now.');
    }

    private function guard(): void
    {
        abort_if(User::query()->exists(), 404);
    }
}
