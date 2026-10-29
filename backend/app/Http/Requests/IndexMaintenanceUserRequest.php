<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMaintenanceUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->role === config('maintenance_tasks.roles.admin');
    }

    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'nullable', 'string', Rule::in(array_values(config('maintenance_tasks.roles')))],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
