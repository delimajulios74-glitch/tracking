<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_tag' => ['required', 'string', 'max:255', 'unique:assets,asset_tag'],
            'asset_name' => ['required', 'string', 'max:255'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'location_id' => ['required', 'integer', 'exists:locations,id'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string'],
            'room_area' => ['nullable', 'string', 'max:255'],
            'purchase_date' => ['required', 'date'],
            'purchase_cost' => ['required', 'numeric', 'min:0'],
            'condition' => ['required', 'string', Rule::in(config('assets.conditions'))],
            'status' => ['required', 'string', Rule::in(config('assets.statuses'))],
            'remarks' => ['nullable', 'string'],
        ];
    }
}
