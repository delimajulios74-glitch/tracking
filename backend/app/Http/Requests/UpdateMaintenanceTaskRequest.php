<?php

namespace App\Http\Requests;

use App\Models\MaintenanceTask;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateMaintenanceTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('maintenanceTask')) ?? false;
    }

    public function rules(): array
    {
        $isAdmin = $this->user()?->role === config('maintenance_tasks.roles.admin');

        return [
            'asset_id' => $isAdmin
                ? [
                    'sometimes',
                    'required',
                    'integer',
                    Rule::exists('assets', 'id')
                        ->whereNull('deleted_at')
                        ->where(fn ($query) => $query->where('status', '!=', 'Disposed')),
                ]
                : ['prohibited'],
            'assigned_to' => $isAdmin
                ? [
                    'sometimes',
                    'required',
                    'integer',
                    Rule::exists('users', 'id')->where(fn ($query) => $query
                        ->where('role', config('maintenance_tasks.roles.maintenance'))
                        ->where('is_active', true)),
                ]
                : ['prohibited'],
            'title' => $isAdmin ? ['sometimes', 'required', 'string', 'max:255'] : ['prohibited'],
            'instructions' => $isAdmin ? ['sometimes', 'nullable', 'string'] : ['prohibited'],
            'priority' => $isAdmin
                ? ['sometimes', 'required', 'string', Rule::in(config('maintenance_tasks.priorities'))]
                : ['prohibited'],
            'due_date' => $isAdmin ? ['sometimes', 'nullable', 'date'] : ['prohibited'],
            'status' => [
                'sometimes',
                'required',
                'string',
                Rule::in($isAdmin
                    ? config('maintenance_tasks.admin_update_statuses')
                    : config('maintenance_tasks.maintenance_user_statuses')),
            ],
            'problem' => ['sometimes', $isAdmin ? 'nullable' : 'required', 'string'],
            'remarks' => ['sometimes', 'nullable', 'string'],
            'completion_notes' => ['sometimes', 'nullable', 'string'],
            'maintenance_performed' => ['sometimes', 'nullable', 'string'],
            'parts_replaced' => ['sometimes', 'nullable', 'string'],
            'updated_condition' => ['sometimes', 'nullable', 'string'],
            'updated_asset_status' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->has('status')) {
                return;
            }

            $task = $this->route('maintenanceTask');
            $status = $this->input('status');

            if (
                $status !== null
                && $status !== $task->status
                && (
                    ! $task->canTransitionTo($status)
                    || $task->status === MaintenanceTask::STATUS_PENDING_APPROVAL
                )
            ) {
                $validator->errors()->add('status', 'This task cannot transition to the selected status through this endpoint.');
            }
        });
    }
}
