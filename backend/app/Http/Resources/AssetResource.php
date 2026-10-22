<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_tag' => $this->asset_tag,
            'asset_name' => $this->asset_name,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn (): array => [
                'id' => $this->category->id,
                'name' => $this->category->name,
            ]),
            'location_id' => $this->location_id,
            'location' => $this->whenLoaded('location', fn (): array => [
                'id' => $this->location->id,
                'name' => $this->location->name,
            ]),
            'brand' => $this->brand,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'description' => $this->description,
            'room_area' => $this->room_area,
            'purchase_date' => $this->purchase_date->toDateString(),
            'purchase_cost' => $this->purchase_cost,
            'condition' => $this->condition,
            'status' => $this->status,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'activity_logs' => $this->whenLoaded('activityLogs', fn (): array => $this->activityLogs
                ->map(fn ($activityLog): array => [
                    'id' => $activityLog->id,
                    'action' => $activityLog->action,
                    'description' => $activityLog->description,
                    'created_at' => $activityLog->created_at,
                    'user' => [
                        'id' => $activityLog->user->id,
                        'name' => $activityLog->user->name,
                    ],
                ])
                ->all()),
        ];
    }
}
