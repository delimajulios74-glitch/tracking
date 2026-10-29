<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMaintenanceTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', \App\Models\MaintenanceTask::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(config('maintenance_tasks.statuses'))],
            'priority' => ['sometimes', 'nullable', 'string', Rule::in(config('maintenance_tasks.priorities'))],
            'assigned_to' => ['sometimes', 'nullable', 'integer', 'exists:users,id'],
            'asset_id' => ['sometimes', 'nullable', 'integer', 'exists:assets,id'],
            'due_date_from' => ['sometimes', 'nullable', 'date'],
            'due_date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:due_date_from'],
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
