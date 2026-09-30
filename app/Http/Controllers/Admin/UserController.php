<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::query()->with('role');
        if ($q = $request->query('q')) {
            $query->where(fn ($w) => $w->where('name', 'like', self::like($q))->orWhere('username', 'like', self::like($q))->orWhere('email', 'like', self::like($q)));
        }
        if ($role = $request->integer('role_id')) {
            $query->where('role_id', $role);
        }
        if (in_array($request->query('status'), ['active', 'inactive'], true)) {
            $query->where('is_active', $request->query('status') === 'active');
        }
        $this->applySort($query, $request, ['name' => 'name', 'username' => 'username', 'last_login' => 'last_login_at', 'created' => 'created_at'], 'name', 'asc');

        return view('admin.users.index', ['users' => $query->paginate($this->perPage($request))->withQueryString(), 'roles' => Role::orderBy('display_name')->get()]);
    }

    public function create(): View
    {
        return view('admin.users.form', $this->formData(new User(['is_active' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request, null);
        $user = DB::transaction(function () use ($data, $request) {
            $user = User::create($data + ['must_change_password' => $request->boolean('must_change_password', true)]);
            $user->directPermissions()->sync($data['permission_ids'] ?? []);

            return $user;
        });
        ActivityLogger::log('user.created', "Created user {$user->username} ({$user->role->display_name})", $user, [],
            ['username' => $user->username, 'role' => $user->role->name, 'extra_permissions' => $user->directPermissions()->pluck('name')->all()], 'admin');

        return redirect()->route('admin.users.index')->with('success', "User {$user->username} created.");
    }

    public function edit(User $user): View
    {
        return view('admin.users.form', $this->formData($user->load('directPermissions')));
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $this->validated($request, $user);
        $this->guardSelf($request, $user, $data);
        $original = $user->getAttributes();
        $oldPerms = $user->directPermissions()->pluck('name')->all();
        unset($data['password']);
        DB::transaction(function () use ($user, $data) {
            $user->fill($data)->save();
            $user->directPermissions()->sync($data['permission_ids'] ?? []);
        });
        $newPerms = $user->directPermissions()->pluck('name')->all();
        ActivityLogger::logChanges('user.updated', "Updated user {$user->username}", $user, $original, 'admin');
        if ($oldPerms != $newPerms) {
            ActivityLogger::log('user.permissions_changed', "Extra permissions changed for {$user->username}", $user, ['permissions' => $oldPerms], ['permissions' => $newPerms], 'admin');
        }

        return redirect()->route('admin.users.index')->with('success', 'User updated.');
    }

    public function resetPassword(Request $request, User $user): RedirectResponse
    {
        $request->validate(['password' => ['nullable', Password::min(8)->letters()->numbers()]]);
        $password = $request->input('password') ?: Str::password(12, symbols: false);
        $user->forceFill(['password' => $password, 'must_change_password' => true, 'remember_token' => Str::random(60)])->save();
        DB::table('sessions')->where('user_id', $user->id)->delete();
        ActivityLogger::log('user.password_reset', "Password reset for {$user->username} by administrator", $user, module: 'admin');

        return back()->with('success', "Password for {$user->username} reset. Temporary password: {$password} — share it securely; the user must change it at next sign-in.");
    }

    public function toggle(Request $request, User $user): RedirectResponse
    {
        abort_if($user->id === $request->user()->id, 422, 'You cannot deactivate your own account.');
        if ($user->is_active && $user->isSuperAdmin() && User::where('is_active', true)->whereHas('role', fn ($q) => $q->where('name', Role::SUPER_ADMIN))->count() <= 1) {
            return back()->withErrors(['user' => 'At least one active Super Admin must remain.']);
        }
        $user->is_active = ! $user->is_active;
        $user->save();
        if (! $user->is_active) {
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        ActivityLogger::log($user->is_active ? 'user.activated' : 'user.deactivated', ($user->is_active ? 'Activated' : 'Deactivated')." user {$user->username}", $user, ['is_active' => ! $user->is_active], ['is_active' => $user->is_active], 'admin');

        return back()->with('success', "User {$user->username} ".($user->is_active ? 'activated.' : 'deactivated.'));
    }

    private function formData(User $user): array
    {
        return [
            'user' => $user,
            'roles' => Role::orderBy('display_name')->get(),
            'permissionGroups' => Permission::orderBy('group')->orderBy('name')->get()->groupBy('group'),
        ];
    }

    private function validated(Request $request, ?User $user): array
    {
        $request->merge(['is_active' => $request->boolean('is_active')]);

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'alpha_dash', 'min:3', 'max:50', Rule::unique('users', 'username')->ignore($user?->id)],
            'email' => ['nullable', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user?->id)],
            'role_id' => ['required', Rule::exists('roles', 'id')],
            'is_active' => ['boolean'],
            'password' => $user ? ['nullable'] : ['required', 'confirmed', Password::min(8)->letters()->numbers()],
            'permission_ids' => ['nullable', 'array'],
            'permission_ids.*' => ['integer', Rule::exists('permissions', 'id')],
        ]);
    }

    private function guardSelf(Request $request, User $user, array $data): void
    {
        if ($user->id !== $request->user()->id) {
            return;
        }
        if (! $data['is_active'] || (int) $data['role_id'] !== (int) $user->role_id) {
            abort(422, 'You cannot deactivate yourself or change your own role.');
        }
    }
}
