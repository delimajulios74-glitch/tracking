<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_id' => $this->asset_id,
            'asset' => $this->whenLoaded('asset', fn (): array => [
                'id' => $this->asset->id,
                'asset_tag' => $this->asset->asset_tag,
                'asset_name' => $this->asset->asset_name,
                'deleted_at' => $this->asset->deleted_at,
            ]),
            'maintenance_type' => $this->maintenance_type,
            'date' => $this->date->toDateString(),
            'performed_by' => $this->performed_by,
            'description_of_work' => $this->description_of_work,
            'parts_replaced' => $this->parts_replaced,
            'cost' => $this->cost,
            'remarks' => $this->remarks,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
