<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RejectMaintenanceTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reject', $this->route('maintenanceTask')) ?? false;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->has('reason') && mb_strlen(trim((string) $this->input('reason'))) < 5) {
                $validator->errors()->add('reason', 'The rejection reason must be at least 5 characters.');
            }
        });
    }
}
