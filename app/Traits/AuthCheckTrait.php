<?php

namespace App\Traits;

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
}
