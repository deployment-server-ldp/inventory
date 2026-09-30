<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'email', 'password', 'role_id', 'is_active', 'must_change_password',
    ];

    protected $hidden = ['password', 'remember_token'];

    /** @var array<string,bool>|null */
    protected ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function directPermissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'user_permissions');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role?->name === Role::SUPER_ADMIN;
    }

    /** All effective permission names (role + direct grants). */
    public function permissionNames(): array
    {
        if ($this->permissionCache === null) {
            $names = $this->role ? $this->role->permissions()->pluck('name')->all() : [];
            $names = array_merge($names, $this->directPermissions()->pluck('name')->all());
            $this->permissionCache = array_fill_keys($names, true);
        }

        return array_keys($this->permissionCache);
    }

    public function hasPermission(string $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }
        if ($this->isSuperAdmin()) {
            return true;
        }
        $this->permissionNames();

        return isset($this->permissionCache[$permission]);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if ($this->hasPermission($p)) {
                return true;
            }
        }

        return false;
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
    }
}
