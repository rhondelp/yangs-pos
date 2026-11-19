<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Wraps ReceiptService::data() plus `reprint`; money values are 2-decimal strings. */
class ReceiptResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
