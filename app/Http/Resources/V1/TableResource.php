<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\DiningTable */
class TableResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'capacity' => $this->capacity,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            // Statuses a person may switch this table to right now.
            'manual_targets' => array_map(fn ($s) => $s->value, $this->is_active ? $this->status->manualTargets() : []),
            'is_active' => $this->is_active,
            'sort_order' => $this->sort_order,
        ];
    }
}
