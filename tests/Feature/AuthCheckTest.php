<?php

namespace Tests\Feature;

use App\Enums\UseRoleEnum;
use App\Enums\UserStatusEnum;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'email_verified_at' => now(),
            'mobile_verified_at' => now(),
            'status' => UserStatusEnum::ACTIVE,
            'role' => UseRoleEnum::CUSTOMER,
        ]);
    }

    public function test_success_register_user()
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => "user@example.com",
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => UseRoleEnum::CUSTOMER,
            'mobile' => '09151002030',
        ], [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);

        $response->assertStatus(201)->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'user',
                'token'
            ]
        ]);

        $this->assertDatabaseHas('users', [
            'email' => 'user@example.com',
            'role' => UseRoleEnum::CUSTOMER
        ]);
    }

    public function test_failed_register_user()
    {
        $response = $this->postJson('/api/auth/register', [
            'email' => "user@example.com",
            'password' => 'password123',
            'password_confirmation' => 'password1234',
            'role' => UseRoleEnum::CUSTOMER,
            'mobile' => '09151002030',
        ], [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);

        $response->assertStatus(422);
    }

    public function test_success_login_user()
    {
        $response = $this->postJson('api/auth/login', [
            'email' => $this->user->email,
            'password' => 'password123'
        ], [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json'
        ]);

        $response->assertOk();
    }

    public function test_success_logout()
    {
        $token = JWTAuth::customClaims($this->user->getJWTCustomClaims())->fromUser($this->user);
        $response = $this->postJson('api/auth/logout', [], [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => "Bearer {$token}"
        ]);
        $response->assertOk();
    }
}
