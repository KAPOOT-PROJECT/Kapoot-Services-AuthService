<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Http\Request;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

trait AuthCheckTrait
{
    protected array $ALL_PERMISSINS = [];
    protected array $ROLE_PERMISSINS = [];

    public function decode(?string $token)
    {
        if (empty($token))
            return null;

        try {
            return JWT::decode(
                $token,
                new Key(
                    env('JWT_SECRET'),
                    env('JWT_ALGO')
                )
            );
        } catch (\Throwable $e) {
            return null;
        }

    }

    public function getAuthUser(Request $request)
    {
        $token = $request->bearerToken();
        $user = $this->decode($token);

        abort_if(!$user, 401, 'Un Auth');

        return $user;
    }

    public function hasPermission(User $user, string $permission): bool
    {
        if (!$user || !isset($user->permissions)) {
            return false;
        }
        if (in_array('*', $user->permissions)) {
            return true;
        }
        if (in_array($permission, $user->permissions)) {
            return true;
        }
        return false;
    }

    public function hasAnyPermission(User $user, array $permissions): bool
    {
        foreach ($permissions as $permission) {
            if ($this->hasPermission($user, $permission)) {
                return true;
                break;
            }
        }
        return false;
    }

    public function hasRole(User $user, string $role): bool
    {
        if (!$user || !isset($user->role)) {
            return false;
        }
        return $user->role === $role;
    }


    public function isAdmin(User $user): bool
    {
        return $this->hasRole($user, 'admin') || $this->hasRole($user, 'super_admin');
    }


    public function requireAuth($request)
    {
        $user = $this->getAuthUser($request);
        if (!$user) {
            abort(401, 'Authentication required');
        }
        return $user;
    }


    public function requirePermission($request, string $permission)
    {
        $user = $this->requireAuth($request);
        if (!$this->hasPermission($user, $permission)) {
            abort(403, "Permission required");
        }
        return $user;
    }

    public function requireRole($request, string $role)
    {
        $user = $this->requireAuth($request);
        if (!$this->hasRole($user, $role)) {
            abort(403, "Role required");
        }
        return $user;
    }

    public function getUserId($request): ?string
    {
        $user = $this->getAuthUser($request);
        if ($user && isset($user->user_id)) {
            return $user->user_id;
        }
        return null;
    }

    public function isOwner($request, $resourceOwnerId): bool
    {
        $userId = $this->getUserId($request);
        return $userId !== null && $userId == $resourceOwnerId;
    }

}
