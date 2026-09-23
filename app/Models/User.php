<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $attributes = ['is_active' => true];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function canAccessWorkspace(): bool
    {
        return $this->is_active && $this->role()->exists();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_active && $this->role()->whereHas('permissions',
            fn ($query) => $query->where('slug', $permission))->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'revision' => 'integer', 'security_version' => 'integer', 'must_change_password' => 'boolean',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
