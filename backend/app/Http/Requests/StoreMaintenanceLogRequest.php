<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'maintenance_type' => ['required', 'string', Rule::in(config('assets.maintenance_types'))],
            'date' => ['required', 'date', 'before_or_equal:today'],
            'performed_by' => ['required', 'string', 'max:255'],
            'description_of_work' => ['required', 'string'],
            'parts_replaced' => ['nullable', 'string'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
