<?php

namespace App\Services;

use App\Enums\LoginHistoryStatusEnum;
use App\Enums\UseRoleEnum;
use App\Models\User;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function updateUser($user, $data)
    {
        if (isset($data['current_password'])) {
            if (!\Illuminate\Support\Facades\Hash::check($data['current_password'], $user->password)) {
                throw new \Exception('رمز عبور فعلی اشتباه است');
            }
            unset($data['current_password']);
        }
        if (isset($data['password'])) {
            $data['password'] = bcrypt($data['password']);
        }
        $user->fill($data);
        $user->save();

        return $user;
    }

    public function verifyCode($email, $code, $type = null)
    {
        $user = \App\Models\User::where('email', $email)->first();
        if (!$user) {
            $this->logLoginAttempt(null, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::FAILED, 'user_not_found');
            throw new \Exception('کاربر یافت نشد');
        }
        $dest = $user->two_factor_auth->value === \App\Enums\TwoFactorAuthEnum::EMAIL ? $user->email : $user->mobile;
        if (empty($dest) || $type !== $user->two_factor_auth->value) {
            $dest = $type === \App\Enums\TwoFactorAuthEnum::EMAIL->value ? $user->email : $user->mobile;
        }
        $cachedCode = \App\Services\verificationCodeService::get($dest);

        if (!$cachedCode) {
            $this->logLoginAttempt($user->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::FAILED, 'no_2fa_code');
            throw new \Exception('کد وارد شده صحیح نیست یا منقضی شده است');
        }
        if ($cachedCode != $code) {
            throw new \Exception('کد وارد شده صحیح نیست یا منقضی شده است');
        }
        \App\Services\verificationCodeService::delete($dest);

        return [
            'user' => $user,
        ];
    }

    public function setTwoFactor($user, $type)
    {
        $validTypes = [
            \App\Enums\TwoFactorAuthEnum::DISABLED->value,
            \App\Enums\TwoFactorAuthEnum::MOBILE->value,
            \App\Enums\TwoFactorAuthEnum::EMAIL->value,
        ];
        if (!in_array($type, $validTypes, true)) {
            throw new \Exception('نوع احراز هویت دو مرحله‌ای نامعتبر است.');
        }
        if ($type === \App\Enums\TwoFactorAuthEnum::MOBILE->value && empty($user->mobile)) {
            throw new \Exception('برای فعال‌سازی دو مرحله‌ای موبایل، ابتدا باید شماره موبایل ثبت شود.');
        }
        $dest = $type === \App\Enums\TwoFactorAuthEnum::EMAIL->value ? $user->email : $user->mobile;
        if (empty($dest)) {
            throw new \Exception('برای فعال‌سازی این روش، ایمیل یا موبایل کاربر باید ثبت شده باشد.');
        }
        \App\Services\verificationService::sendCode($dest, $type);

        return [
            'user' => $user,
            'type' => $type,
            'پیام' => 'کد تایید به  ' . $dest . ' ارسال شد.',
        ];

    }

    /**
     * اعتبارسنجی توکن JWT و بررسی وجود کاربر و عدم انقضا
     *
     * @param  string|null  $token
     * @return array|null
     */
    public function validateToken($token = null)
    {
        try {
            $token = $token ?: (request()->bearerToken() ?? null);
            if (!$token) {
                return [
                    'valid' => false,
                    'reason' => 'No token provided',
                ];
            }
            $payload = \Tymon\JWTAuth\Facades\JWTAuth::setToken($token)->getPayload();
            $userId = $payload['id'] ?? null;
            if (!$userId) {
                return [
                    'valid' => false,
                    'reason' => 'User id not found in token',
                ];
            }
            $user = \App\Models\User::find($userId);
            if (!$user) {
                return [
                    'valid' => false,
                    'reason' => 'User not found',
                ];
            }
            if ($payload->get('exp') < time()) {
                return [
                    'valid' => false,
                    'reason' => 'Token expired',
                ];
            }

            return [
                'valid' => true,
                'user' => $user,
                'payload' => $payload->toArray(),
            ];
        } catch (\Tymon\JWTAuth\Exceptions\TokenExpiredException $e) {
            return [
                'valid' => false,
                'reason' => 'Token expired',
            ];
        } catch (\Tymon\JWTAuth\Exceptions\TokenInvalidException $e) {
            return [
                'valid' => false,
                'reason' => 'Token invalid',
            ];
        } catch (\Exception $e) {
            return [
                'valid' => false,
                'reason' => $e->getMessage(),
            ];
        }
    }

    public function login($data)
    {
        $credentials = [
            'email' => $data['email'],
        ];
        $user = \App\Models\User::where('email', $data['email'])->first();
        if (!$user || !\Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
            if ($user) {
                $this->logLoginAttempt($user->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::FAILED, 'invalid_credentials');
            }
            throw new \Exception('Invalid credentials');
        }
        if (!$user->isActive()) {
            $this->logLoginAttempt($user->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::FAILED, 'inactive');
            throw new \Exception('User is not active');
        }
        if ($user->two_factor_auth === \App\Enums\TwoFactorAuthEnum::DISABLED) {
            $tokens = $this->generateTokens($user, $data['password']);
            $this->logLoginAttempt($user->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::SUCCESS);

            return [
                'user' => $user,
                'token' => $tokens,
            ];
        } else {
            $dest = $user->two_factor_auth->value === \App\Enums\TwoFactorAuthEnum::EMAIL->value ? $user->email : $user->mobile;
            \App\Services\verificationService::sendCode($dest, $user->two_factor_auth->value);

            return [
                'user' => $user,
                'پیام' => 'کد تایید به  ' . $dest . ' ارسال شد.',
            ];
        }
    }

    public function refresh($refreshTokenStr)
    {
        $refreshToken = \App\Models\RefreshToken::where('token', $refreshTokenStr)->first();
        if (!$refreshToken) {
            return null;
        }
        $user = $refreshToken->user;
        if (!$user) {
            return null;
        }
        $refreshToken->delete();

        return [
            'user' => $user,
            'token' => $this->generateTokens($user),
        ];
    }

    public function registerUser($data)
    {
        try {
            $user = \App\Models\User::create([
                'email' => $data['email'],
                'password' => bcrypt($data['password']),
                'role' => $data['role'],
                'mobile' => $data['mobile'] ?? null,
            ]);
            $token = $this->generateTokens($user);
            $this->logLoginAttempt($user->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::SUCCESS);

            return ['user' => $user, 'token' => $token];
        } catch (\Exception $e) {

            $email = $data['email'] ?? null;
            $user = \App\Models\User::where('email', $email)->first();
            $userId = $user ? $user->id : null;
            $this->logLoginAttempt($userId, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::FAILED, $e->getMessage());
            throw $e;
        }
    }

    public function generateTokens(User $user)
    {
        $token = JWTAuth::customClaims($user->getJWTCustomClaims())->fromUser($user);

        $refreshToken = \App\Models\RefreshToken::create([
            'user_id' => $user->id,
            'token' => bin2hex(random_bytes(64)),
            'expires_at' => now()->addDays(30),
            'user_agent' => request()->userAgent(),
            'ip_address' => request()->ip(),
        ]);

        return [
            'access_token' => $token,
            'token_type' => 'bearer',
            'expires_in' => intval(config('jwt.ttl') * 60),
            'refresh_token' => $refreshToken->token,
            'permissions' => $this->getUserPermissions($user),
        ];
    }

    public function logout($user)
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
            $this->logLoginAttempt($user->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::SUCCESS, 'logged_out');
        } catch (\Exception $e) {
            $this->logLoginAttempt($user->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::FAILED, 'logout_failed: ' . $e->getMessage());
            throw $e;
        }
    }

    public function logLoginAttempt($userId, $ipAddress, $userAgent, $status, $reason = null)
    {
        \App\Models\LoginHistory::create([
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'status' => $status,
            'logged_in_at' => now(),
            'failure_reason' => $reason ?? '',
        ]);
    }

    public function getUserPermissions($user)
    {
        $rolePermissions = [
            UseRoleEnum::SUPERADMIN->value => ['*'],
            UseRoleEnum::ADMIN->value => ['manage_users', 'view_reports'],
            UseRoleEnum::SERVICE_PROVIDER->value => ['view_jobs', 'update_status'],
            UseRoleEnum::CUSTOMER->value => ['create_request', 'view_own_requests'],
        ];

        return $rolePermissions[$user->role] ?? [];
    }
}
