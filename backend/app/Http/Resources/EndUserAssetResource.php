<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EndUserAssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'asset_tag' => $this->asset_tag,
            'asset_name' => $this->asset_name,
            'category' => $this->whenLoaded('category', fn (): ?string => $this->category?->name),
            'location' => $this->whenLoaded('location', fn (): ?string => $this->location?->name),
            'room_area' => $this->room_area,
            'brand' => $this->brand,
            'model' => $this->model,
            'serial_number' => $this->serial_number,
            'description' => $this->description,
            'condition' => $this->condition,
            'status' => $this->status,
        ];
    }
}
