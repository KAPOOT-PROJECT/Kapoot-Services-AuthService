<?php

namespace App\Http\Controllers;

use App\Http\Requests\RegisterRequest;
use App\Http\Requests\LoginRequest;
use App\Services\ResponseService;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use App\UseRoleEnum;
use App\LoginHistoryStatusEnum;



class AuthController extends Controller
{
    
    public function updateUser(\App\Http\Requests\UpdateUserRequest $request)
    {
        $user = $request->user();
        $data = $request->validated();
        $updatedUser = $this->authService->updateUser($user, $data);
        return ResponseService::success(new UserResource($updatedUser), 'اطلاعات کاربر با موفقیت بروزرسانی شد');
    }





    
    public function verifyCode(Request $request)
    {
        $email = $request->input('email');
        $code = $request->input('code');
        try {
            $data = $this->authService->verifyCode($email, $code);

        $tokens = $this->authService->generateTokens($data['user']);
        $this->authService->logLoginAttempt($data['user']->id, request()->ip(), request()->userAgent(), LoginHistoryStatusEnum::SUCCESS);

            return ResponseService::success([
                'user' => new UserResource($data['user']),
                'token' => $tokens,
            ], 'ورود با موفقیت انجام شد');
        } catch (\Exception $e) {
            return ResponseService::error('کد وارد شده صحیح نیست یا منقضی شده است', 401, $e->getMessage());
        }
    }

    public function verifyTwoAuthCode(Request $request)
    {
        $email = $request->input('email');
        $code = $request->input('code');
        $type = $request->input('type');
        try {
            $user = \App\Models\User::where('email', $email)->first();
            $data = $this->authService->verifyCode($email, $code , $type);
                    $user->two_factor_auth = $type;
                    $user->save();
        
        if ($type === \App\TwoFactorAuthEnum::EMAIL->value) {
            $user->email_verified_at = now();
        } elseif ($type === \App\TwoFactorAuthEnum::MOBILE->value) {
            $user->mobile_verified_at = now();
        }
        $user->save();

            return ResponseService::success([
                'user' => new UserResource($data['user']),
            ], 'با موفقیت فعال شد ');
        } catch (\Exception $e) {
            return ResponseService::error('کد وارد شده صحیح نیست یا منقضی شده است', 401, $e->getMessage());
        }
    }


    public function setTwoFactor(Request $request)
    {
        $user = $request->user();
        $type = $request->input('type');
        try {
            $result = $this->authService->setTwoFactor($user, $type);
            return ResponseService::success([
                'user' => new UserResource($result['user']),
                'message' => 'کد تایید ارسال شد. لطفا کد را وارد کنید.',
            ], 'کد تایید ارسال شد.');
        } catch (\Exception $e) {
            return ResponseService::error('خطا در تغییر وضعیت احراز هویت دو مرحله‌ای', 422, $e->getMessage());
        }
    }
    public function __construct(private readonly \App\Services\AuthService $authService)
    {
    }

    public function register(RegisterRequest $request)
    {
        try {
            $validated = $request->validated();

            $data = $this->authService->registerUser($validated);
            return ResponseService::success([
                'user' => new UserResource($data['user']),
                'token' => $data['token'],
            ], 'ثبت‌نام با موفقیت انجام شد', 201);
        } catch (\Exception $e) {
            return ResponseService::error('Registration failed', 500, $e->getMessage());
        }
    }

    public function login(LoginRequest $request)
    {
        try {
            $validated = $request->validated();
            $data = $this->authService->login($validated);
            if (isset($data['token'])) {
                return ResponseService::success([
                    'user' => new UserResource($data['user']),
                    'token' => $data['token'],
                ], 'ورود با موفقیت انجام شد');
            } else {
                return ResponseService::success([
                    'user' => new UserResource($data['user']),
                    'message' => $data['پیام'] ?? 'کد تایید ارسال شد. لطفا کد را وارد کنید.',
                ], 'کد تایید ارسال شد.');
            }
        } catch (\Exception $e) {
            return ResponseService::error('Login failed', 401, $e->getMessage());
        }
    }

    public function logout(Request $request)
    {
        try {
            $user = $request->user();
            $this->authService->logout($user);
            return ResponseService::success(null, 'خروج با موفقیت انجام شد');
        } catch (\Exception $e) {
            return ResponseService::error('Logout failed', 500, $e->getMessage());
        }
    }

    public function refresh(Request $request)
    {
        $refreshToken = $request->input('refresh_token');
        $data = $this->authService->refresh($refreshToken);
        if (!$data || !isset($data['user']) || !isset($data['token'])) {
            return ResponseService::error('Refresh token not found or expired', 401);
        }
        return ResponseService::success([
            'user' => new UserResource($data['user']),
            'token' => $data['token'],
        ], 'توکن با موفقیت رفرش شد');
    }

    public function me(Request $request)
    {
        try {
            $user = $request->user();
            return ResponseService::success(new UserResource($user), 'اطلاعات کاربر');
        } catch (\Exception $e) {
            return ResponseService::error('User info failed', 401, $e->getMessage());
        }
    }

    public function validateToken(Request $request)
    {
        $token = $request->bearerToken() ?? $request->input('token');
        $result = $this->authService->validateToken($token);
        if ($result['valid']) {
            return ResponseService::success([
                'user' => new UserResource($result['user']),
                'payload' => $result['payload'],
            ], 'توکن معتبر است');
        } else {
            return ResponseService::error('توکن نامعتبر است', 401, $result['reason'] ?? null);
        }
    }

    public function getUserPermissions(User $user)
    {
        if (!$user) {
            return ResponseService::error('User not found', 404);
        }
        $permissions = $this->authService->getUserPermissions($user);
        return ResponseService::success($permissions, 'Permissions retrieved successfully');
    }
}
