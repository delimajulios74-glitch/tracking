<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateManagedUserRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->exists('email')) {
            $email = trim((string) $this->input('email'));
            $this->merge([
                'email' => $email === '' ? null : Str::lower($email),
            ]);
        }
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username')->ignore($this->route('user')),
            ],
            'email' => [
                'sometimes',
                'nullable',
                'string',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'role' => ['sometimes', 'required', Rule::in(config('roles.assignable'))],
            'is_active' => ['sometimes', 'required', 'boolean'],
        ];
    }
}
