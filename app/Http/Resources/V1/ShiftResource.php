<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** A shift template; end_time at or before start_time means it ends the next day. @mixin \App\Models\Shift */
class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'start_time' => $this->start_time, 'end_time' => $this->end_time, 'is_active' => $this->is_active];
    }
}
