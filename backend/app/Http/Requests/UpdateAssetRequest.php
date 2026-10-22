<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $asset = $this->route('asset');
        $assetId = is_numeric($asset) ? $asset : $asset?->getKey();

        return [
            'asset_tag' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('assets', 'asset_tag')->ignore($assetId)],
            'asset_name' => ['sometimes', 'required', 'string', 'max:255'],
            'category_id' => ['sometimes', 'required', 'integer', 'exists:categories,id'],
            'location_id' => ['sometimes', 'required', 'integer', 'exists:locations,id'],
            'brand' => ['sometimes', 'nullable', 'string', 'max:255'],
            'model' => ['sometimes', 'nullable', 'string', 'max:255'],
            'serial_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'required', 'string'],
            'room_area' => ['sometimes', 'nullable', 'string', 'max:255'],
            'purchase_date' => ['sometimes', 'required', 'date'],
            'purchase_cost' => ['sometimes', 'required', 'numeric', 'min:0'],
            'condition' => ['sometimes', 'required', 'string', Rule::in(config('assets.conditions'))],
            'status' => ['sometimes', 'required', 'string', Rule::in(config('assets.statuses'))],
            'remarks' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
