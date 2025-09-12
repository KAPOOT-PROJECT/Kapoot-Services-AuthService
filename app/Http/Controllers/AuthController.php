<?php

namespace App\Http\Controllers;

use App\Enums\LoginHistoryStatusEnum;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\AuthService;
use App\Services\EventPublisher;
use App\Traits\{ResponseTrait, AuthCheckTrait};
use Illuminate\Http\Request;

class AuthController extends Controller
{
    use ResponseTrait, AuthCheckTrait;

    public function __construct(
        private readonly AuthService $authService,
        private readonly EventPublisher $eventPublisher
    ) {
    }

    public function updateUser(\App\Http\Requests\UpdateUserRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        $updatedUser = $this->authService->updateUser($user, $data);

        return self::success(new UserResource($updatedUser), 'اطلاعات کاربر با موفقیت بروزرسانی شد');
    }

    public function verifyCode(Request $request)
    {
        $email = $request->input('email');
        $code = $request->input('code');
        try {
            $data = $this->authService->verifyCode($email, $code);

            $tokens = $this->authService->generateTokens($data['user']);
            $this->authService->logLoginAttempt($data['user']->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::SUCCESS);

            return self::success([
                'user' => new UserResource($data['user']),
                'token' => $tokens,
            ], 'ورود با موفقیت انجام شد');
        } catch (\Exception $e) {
            return self::error($e->getMessage(), 'کد وارد شده صحیح نیست یا منقضی شده است', 401);
        }
    }

    public function verifyTwoAuthCode(Request $request)
    {
        $email = $request->input('email');
        $code = $request->input('code');
        $type = $request->input('type');
        try {
            $user = \App\Models\User::where('email', $email)->first();
            $data = $this->authService->verifyCode($email, $code, $type);
            $user->two_factor_auth = $type;
            $user->save();

            if ($type === \App\Enums\TwoFactorAuthEnum::EMAIL->value) {
                $user->email_verified_at = now();
            } elseif ($type === \App\Enums\TwoFactorAuthEnum::MOBILE->value) {
                $user->mobile_verified_at = now();
            }
            $user->save();

            return self::success([
                'user' => new UserResource($data['user']),
            ], 'با موفقیت فعال شد ');
        } catch (\Exception $e) {
            return self::error($e->getMessage(), 'کد وارد شده صحیح نیست یا منقضی شده است', 401);
        }
    }

    public function setTwoFactor(Request $request)
    {
        $user = $request->user();
        $type = $request->input('type');
        try {
            $result = $this->authService->setTwoFactor($user, $type);

            return self::success([
                'user' => new UserResource($result['user']),
                'message' => 'کد تایید ارسال شد. لطفا کد را وارد کنید.',
            ], 'کد تایید ارسال شد.');
        } catch (\Exception $e) {
            return self::error($e->getMessage(), 'خطا در تغییر وضعیت احراز هویت دو مرحله‌ای', 422);
        }
    }

    public function register(RegisterRequest $request)
    {
        try {
            $validated = $request->validated();

            $data = $this->authService->registerUser($validated);

            $user = $data['user'];
            $this->eventPublisher->publishUserRegistered(
                $user->id,
                $user->email,
                'guest', //TODO add to User Model & Migration
                $user->mobile
            );
            
            return self::success([
                'user' => new UserResource($data['user']),
                'token' => $data['token'],
            ], 'ثبت‌نام با موفقیت انجام شد', 201);
        } catch (\Exception $e) {
            return self::error($e->getMessage(), 'Registration failed', 500);
        }
    }

    public function login(LoginRequest $request)
    {
        try {
            $validated = $request->validated();
            $data = $this->authService->login($validated);
            if (isset($data['token'])) {
                return self::success([
                    'user' => new UserResource($data['user']),
                    'token' => $data['token'],
                ], 'ورود با موفقیت انجام شد');
            }

            return self::success([
                'user' => new UserResource($data['user']),
                'message' => $data['پیام'] ?? 'کد تایید ارسال شد. لطفا کد را وارد کنید.',
            ], 'کد تایید ارسال شد.');
        } catch (\Exception $e) {
            return self::error($e->getMessage(), 'Login failed', 401);
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $this->authService->logout($user);

            return self::success(null, 'خروج با موفقیت انجام شد');
        } catch (\Exception $e) {
            return self::error($e->getMessage(), 'Logout failed', 500);
        }
    }

    public function refresh(Request $request)
    {
        $refreshToken = $request->input('refresh_token');
        $data = $this->authService->refresh($refreshToken);
        if (!$data || !isset($data['user']) || !isset($data['token'])) {
            return self::error(null, 'Refresh token not found or expired', 401);
        }

        return self::success([
            'user' => new UserResource($data['user']),
            'token' => $data['token'],
        ], 'توکن با موفقیت رفرش شد');
    }

    public function me(Request $request)
    {
        try {
            $user = $request->user();

            return self::success(new UserResource($user), 'اطلاعات کاربر');
        } catch (\Exception $e) {
            return self::error($e->getMessage(), 'User info failed', 401);
        }
    }

    public function validateToken(Request $request)
    {
        $token = $request->bearerToken() ?? $request->input('token');
        $result = $this->authService->validateToken($token);
        if ($result['valid']) {
            return self::success([
                'user' => new UserResource($result['user']),
                'payload' => $result['payload'],
            ], 'توکن معتبر است');
        } else {
            return self::error($result['reason'] ?? null, 'توکن نامعتبر است', 401);
        }
    }

    public function getUserPermissions(User $user)
    {
        if (!$user) {
            return self::error(null, 'User not found', 404);
        }
        $permissions = $this->authService->getUserPermissions($user);

        return self::success($permissions, 'Permissions retrieved successfully');
    }

    public function test(Request $request)
    {
        $user = $this->getAuthUser($request);
        return self::success($user);
    }
}
