<?php

namespace App\Domains\Identity\Models;

use App\Domains\Customer\Models\CustomerProfile;
use App\Domains\Provider\Models\ProviderProfile;
use App\Domains\Shared\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes, HasUuid;

    protected $table = 'users';

    protected $fillable = [
        'phone_number',
        'email',
        'password_hash',
        'first_name',
        'last_name',
        'user_type',
        'status',
        'phone_verified_at',
        'email_verified_at',
        'failed_login_attempts',
        'locked_until',
    ];

    protected $hidden = [
        'password_hash',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'phone_verified_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'locked_until' => 'datetime',
            'failed_login_attempts' => 'integer',
        ];
    }

    public function getAuthPassword(): string
    {
        return $this->password_hash ?? '';
    }

    /**
     * User Roles
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'user_roles', 'user_id', 'role_id')
            ->withPivot(['assigned_by', 'assigned_at']);
    }

    /**
     * Customer Profile
     */
    public function customerProfile(): HasOne
    {
        return $this->hasOne(CustomerProfile::class, 'user_id', 'id');
    }

    /**
     * Provider Profile
     */
    public function providerProfile(): HasOne
    {
        return $this->hasOne(ProviderProfile::class, 'user_id', 'id');
    }

    /**
     * Check if user has specific role
     */
    public function hasRole(string $roleName): bool
    {
        return $this->roles->contains('name', $roleName);
    }

    /**
     * Check if user has any of the given roles
     */
    public function hasAnyRole(array $roleNames): bool
    {
        return $this->roles->whereIn('name', $roleNames)->isNotEmpty();
    }

    /**
     * Check if user has specific permission through roles
     */
    public function hasPermission(string $permissionName): bool
    {
        foreach ($this->roles as $role) {
            if ($role->permissions->contains('name', $permissionName)) {
                return true;
            }
        }
        return false;
    }
}
