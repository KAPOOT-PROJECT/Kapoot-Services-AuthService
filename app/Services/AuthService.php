<?php

namespace App\Services;

use Tymon\JWTAuth\Facades\JWTAuth;
use App\UseRoleEnum;
use App\LoginHistoryStatusEnum;


class AuthService
{

    public function updateUser($user, $data)
    {
        // اگر فیلد current_password ارسال شده بود، صحت آن را چک کن
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
    public function verifyCode($email, $code , $type = null)
    {
        $user = \App\Models\User::where('email', $email)->first();
        if (!$user) {
            $this->logLoginAttempt(null, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::FAILED, 'user_not_found');
            throw new \Exception('کاربر یافت نشد');
        }
        $dest = $user->two_factor_auth->value === \App\TwoFactorAuthEnum::EMAIL ? $user->email : $user->mobile;
        if (empty($dest) || $type !== $user->two_factor_auth->value) {
            $dest = $type === \App\TwoFactorAuthEnum::EMAIL->value ? $user->email : $user->mobile;
        }
        $cachedCode = \App\Services\verificationCodeService::get($dest);

        if (!$cachedCode) {
            $this->logLoginAttempt($user->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::FAILED, 'no_2fa_code');
            throw new \Exception('کد وارد شده صحیح نیست یا منقضی شده است');
        }
        if ($cachedCode != $code) {
            throw new \Exception('کد وارد شده صحیح نیست یا منقضی شده است');
        }
        // فقط اگر کد صحیح بود حذف کن
        \App\Services\verificationCodeService::delete($dest);
        return [
            'user' => $user
        ];
    }

    public function setTwoFactor($user, $type)
    {   
        $validTypes = [
            \App\TwoFactorAuthEnum::DISABLED->value,
            \App\TwoFactorAuthEnum::MOBILE->value,
            \App\TwoFactorAuthEnum::EMAIL->value,
        ];
        if (!in_array($type, $validTypes, true)) {
            throw new \Exception('نوع احراز هویت دو مرحله‌ای نامعتبر است.');
        }
        if ($type === \App\TwoFactorAuthEnum::MOBILE->value && empty($user->mobile)) {
            throw new \Exception('برای فعال‌سازی دو مرحله‌ای موبایل، ابتدا باید شماره موبایل ثبت شود.');
        }
        $dest = $type === \App\TwoFactorAuthEnum::EMAIL->value ? $user->email : $user->mobile;
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
     * @param string|null $token
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
            // بررسی انقضای توکن
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


    /**
     * ورود کاربر و تولید توکن
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function login($data)
    {
        $credentials = [
            'email' => $data['email'],
        ];
        $user = \App\Models\User::where('email', $data['email'])->first();
        // چک وجود کاربر و صحت پسورد
        if (!$user || !\Illuminate\Support\Facades\Hash::check($data['password'], $user->password)) {
            if ($user) {
                $this->logLoginAttempt($user->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::FAILED, 'invalid_credentials');
            }
            throw new \Exception('Invalid credentials');
        }
        // چک فعال بودن کاربر
        if (!$user->isActive()) {
            $this->logLoginAttempt($user->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::FAILED, 'inactive');
            throw new \Exception('User is not active');
        }
        // بررسی فعال بودن 2FA
        if ($user->two_factor_auth === \App\TwoFactorAuthEnum::DISABLED) {
            // تولید توکن و رفرش توکن
            $tokens = $this->generateTokens($user, $data['password']);
            $this->logLoginAttempt($user->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::SUCCESS);
            return [
                'user' => $user,
                'token' => $tokens,
            ];
        } else {
            // ارسال کد تایید فقط اگر ایمیل/موبایل وجود دارد
            $dest = $user->two_factor_auth->value === \App\TwoFactorAuthEnum::EMAIL->value ? $user->email : $user->mobile;
            \App\Services\verificationService::sendCode($dest, $user->two_factor_auth->value);
            return [
                'user' => $user,
                'پیام' => 'کد تایید به  ' . $dest . ' ارسال شد.',
            ];
        }
    }

    /**
     * رفرش توکن
     *
     * @param \App\Models\User $user
     * @return array
     */
    public function refresh($refreshTokenStr)
    {
        // پیدا کردن رفرش توکن
        $refreshToken = \App\Models\RefreshToken::where('token', $refreshTokenStr)->first();
        if (!$refreshToken) {
            return null;
        }
        $user = $refreshToken->user;
        if (!$user) {
            return null;
        }
        // حذف رفرش توکن قبلی
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
            // اگر ایمیل تکراری یا خطای دیتابیس یا هر خطای دیگری بود
            $email = $data['email'] ?? null;
            $user = \App\Models\User::where('email', $email)->first();
            $userId = $user ? $user->id : null;
            $this->logLoginAttempt($userId, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::FAILED, $e->getMessage());
            throw $e;
        }
    }

    public function generateTokens($user)
    {
        // claims سفارشی
        $customClaims = [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
        ];
        $token = JWTAuth::customClaims($customClaims)->fromUser($user);

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
