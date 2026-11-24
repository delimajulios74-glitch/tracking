<?php

namespace App\Http\Requests;

use App\Models\MaintenanceTask;
use Illuminate\Foundation\Http\FormRequest;

class IndexPendingMaintenanceTasksRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewPendingApprovals', MaintenanceTask::class) ?? false;
    }

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }
}
