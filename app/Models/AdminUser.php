<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class AdminUser extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'permissions',
        'is_active',
        'training_mode_enabled',
        'training_mode_active',
        'last_login_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'training_mode_enabled' => 'boolean',
            'training_mode_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'permissions' => 'array',
        ];
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function canManageAdminUsers(): bool
    {
        return $this->hasAdminPermission('admin_users.manage')
            || ! self::query()->where('role', 'owner')->exists();
    }

    public function hasAdminPermission(string $permission): bool
    {
        if ($this->isOwner()) {
            return true;
        }

        $permissions = $this->effectivePermissions();

        return in_array('*', $permissions, true)
            || in_array($permission, $permissions, true);
    }

    public function effectivePermissions(): array
    {
        $rolePermissions = config("admin_permissions.role_permissions.{$this->role}", []);
        $userPermissions = $this->permissions ?? [];

        return array_values(array_unique([...$rolePermissions, ...$userPermissions]));
    }

    public function emailAccounts(): HasMany
    {
        return $this->hasMany(EmailAccount::class);
    }

    public function emailAccount(): HasOne
    {
        return $this->hasOne(EmailAccount::class);
    }

    public function assignedEmailConversations(): HasMany
    {
        return $this->hasMany(EmailConversation::class, 'assigned_user_id');
    }
}
