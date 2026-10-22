<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_type' => ['sometimes', 'required', 'string', Rule::in(config('assets.maintenance_types'))],
            'date' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'performed_by' => ['sometimes', 'required', 'string', 'max:255'],
            'description_of_work' => ['sometimes', 'required', 'string'],
            'parts_replaced' => ['sometimes', 'nullable', 'string'],
            'cost' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'remarks' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
