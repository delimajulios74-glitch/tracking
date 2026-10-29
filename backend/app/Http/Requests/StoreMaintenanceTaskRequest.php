<?php

namespace App\Http\Requests;

use App\Models\MaintenanceTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', MaintenanceTask::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'asset_id' => [
                'required',
                'integer',
                Rule::exists('assets', 'id')
                    ->whereNull('deleted_at')
                    ->where(fn ($query) => $query->where('status', '!=', 'Disposed')),
            ],
            'assigned_to' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where(fn ($query) => $query
                    ->where('role', config('maintenance_tasks.roles.maintenance'))
                    ->where('is_active', true)),
            ],
            'title' => ['required', 'string', 'max:255'],
            'instructions' => ['nullable', 'string'],
            'priority' => ['sometimes', 'required', 'string', Rule::in(config('maintenance_tasks.priorities'))],
            'due_date' => ['nullable', 'date'],
        ];
    }
}
