<?php

namespace App\Http\Requests;

use App\Enums\AssetRequestStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAssetRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'asset_id' => ['sometimes', 'required', 'integer', 'exists:assets,id'],
            'location_id' => ['sometimes', 'required', 'integer', 'exists:locations,id'],
            'description' => ['sometimes', 'required', 'string', 'max:5000'],
            'status' => ['sometimes', 'required', 'string', Rule::in(AssetRequestStatus::values())],
            'photo' => ['sometimes', 'nullable', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
            'remove_photo' => ['sometimes', 'boolean'],
        ];
    }
}
