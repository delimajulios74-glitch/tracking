<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'q' => ['sometimes', 'nullable', 'string', 'max:255'],
            'category_id' => ['sometimes', 'nullable', 'integer', 'exists:categories,id'],
            'location_id' => ['sometimes', 'nullable', 'integer', 'exists:locations,id'],
            'status' => ['sometimes', 'nullable', 'string', Rule::in(config('assets.statuses'))],
            'condition' => ['sometimes', 'nullable', 'string', Rule::in(config('assets.conditions'))],
            'purchase_date_from' => ['sometimes', 'nullable', 'date'],
            'purchase_date_to' => ['sometimes', 'nullable', 'date', 'after_or_equal:purchase_date_from'],
            'sort_by' => [
                'sometimes',
                'string',
                Rule::in([
                    'asset_tag',
                    'asset_name',
                    'purchase_date',
                    'purchase_cost',
                    'status',
                    'condition',
                    'created_at',
                    'updated_at',
                ]),
            ],
            'sort_direction' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
