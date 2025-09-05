<?php

namespace App\Http\Requests;

use App\UseRoleEnum;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6|confirmed',
            'role' => ['required', new Enum(UseRoleEnum::class)],
            'mobile' => 'sometimes|required|string|unique:users,mobile',
        ];
    }
}
