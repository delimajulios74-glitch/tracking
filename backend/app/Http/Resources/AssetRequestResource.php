<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_no' => $this->reference_no,
            'asset' => $this->whenLoaded('asset', fn (): array => [
                'id' => $this->asset->id,
                'asset_tag' => $this->asset->asset_tag,
                'asset_name' => $this->asset->asset_name,
            ]),
            'location_id' => $this->location_id,
            'reporter' => $this->whenLoaded('reporter', fn (): array => [
                'id' => $this->reporter->id,
                'name' => $this->reporter->name,
            ]),
            'maintenance_task' => $this->when(
                $this->relationLoaded('linkedMaintenanceTask') && $this->linkedMaintenanceTask,
                fn (): array => [
                    'id' => $this->linkedMaintenanceTask->id,
                    'title' => $this->linkedMaintenanceTask->title,
                    'status' => $this->linkedMaintenanceTask->status,
                    'assigned_to' => $this->linkedMaintenanceTask->assignee?->name,
                ],
            ),
            'location' => $this->whenLoaded('location', fn (): ?string => $this->location?->name),
            'type' => $this->type->value,
            'description' => $this->description,
            'photo_url' => $this->photo_path
                ? route(
                    $request->user()?->role === 'admin'
                        ? 'admin.asset-requests.photo'
                        : 'end-user.reports.photo',
                    ['assetRequest' => $this->id],
                )
                : null,
            'status' => $this->status->value,
            'created_at' => $this->created_at,
        ];
    }
}
