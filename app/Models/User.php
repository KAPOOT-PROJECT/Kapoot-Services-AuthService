<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\TwoFactorAuthEnum;
use App\Enums\UseRoleEnum;
use App\Enums\UserStatusEnum;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;

class User extends Authenticatable implements JWTSubject
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'mobile',
        'email',
        'password',
        'role',
        'status',
        'metadata',
        'two_factor_auth',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_auth' => TwoFactorAuthEnum::class,
        ];
    }

    /**
     * Get the identifier that will be stored in the subject claim of the JWT.
     *
     * @return mixed
     */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /**
     * Return a key value array, containing any custom claims to be added to the JWT.
     *
     * @return array
     */
    public function getJWTCustomClaims()
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'role' => $this->role,
            'status' => $this->status,
        ];
    }

    public function refreshtokens()
    {
        return $this->hasMany(RefreshToken::class);
    }

    public function loginhistories()
    {
        return $this->hasMany(LoginHistory::class);
    }

    public function isActive(): bool
    {
        return $this->status === UserStatusEnum::ACTIVE->value;
    }

    public function isVerified(): bool
    {
        return ! is_null($this->email_verified_at) || ! is_null($this->mobile_verified_at);
    }

    public function hasRole($role): bool
    {
        $roleValue = $role instanceof UseRoleEnum ? $role->value : $role;

        return $this->role === $roleValue;
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === UseRoleEnum::SUPERADMIN->value;
    }

    public function isAdmin(): bool
    {
        return $this->role === UseRoleEnum::ADMIN->value;
    }

    public function isProvider(): bool
    {
        return $this->role === UseRoleEnum::SERVICE_PROVIDER->value;
    }

    public function isCustomer(): bool
    {
        return $this->role === UseRoleEnum::CUSTOMER->value;
    }
}
