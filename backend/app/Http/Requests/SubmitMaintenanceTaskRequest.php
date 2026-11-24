<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SubmitMaintenanceTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('submit', $this->route('maintenanceTask')) ?? false;
    }

    public function rules(): array
    {
        return [
            'problem' => ['required', 'string'],
            'completion_notes' => ['required', 'string'],
            'maintenance_performed' => ['sometimes', 'nullable', 'string'],
            'parts_replaced' => ['sometimes', 'nullable', 'string'],
            'updated_condition' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(config('assets.conditions')),
            ],
            'updated_asset_status' => [
                'sometimes',
                'nullable',
                'string',
                Rule::in(config('assets.statuses')),
            ],
            'remarks' => ['sometimes', 'nullable', 'string'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            foreach (['problem', 'completion_notes'] as $field) {
                if ($this->has($field) && trim((string) $this->input($field)) === '') {
                    $validator->errors()->add($field, 'This field is required.');
                }
            }
        });
    }
}
