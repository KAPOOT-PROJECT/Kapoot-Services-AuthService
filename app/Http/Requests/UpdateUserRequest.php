<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'sometimes|email|unique:users,email,'.$this->user()->id,
            'current_password' => 'required|string',
            'mobile' => 'sometimes|nullable|string',
            'password' => 'sometimes|nullable|string|min:6',
            'role' => 'sometimes|in:customer,admin,superadmin,service-provider',
            'status' => 'sometimes|in:active,inactive,suspended,pending',
            'metadata' => 'sometimes|nullable|array',
            'two_factor_auth' => 'sometimes|in:disabled,mobile,email',
        ];
    }
}
