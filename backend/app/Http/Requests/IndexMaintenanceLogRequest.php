<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexMaintenanceLogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['nullable', 'string', 'max:255'],
            'asset_id' => [
                'nullable',
                'integer',
                Rule::exists('assets', 'id')->whereNull('deleted_at'),
            ],
            'maintenance_type' => ['nullable', 'string', Rule::in(config('assets.maintenance_types'))],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'cost_min' => ['nullable', 'numeric', 'min:0'],
            'cost_max' => array_merge(
                ['nullable', 'numeric', 'min:0'],
                $this->filled('cost_min') ? ['gte:cost_min'] : [],
            ),
            'sort_direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
