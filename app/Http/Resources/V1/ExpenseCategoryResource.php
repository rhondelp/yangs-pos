<?php

namespace App\Http\Resources\V1;

use App\Models\ExpenseCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ExpenseCategory */
class ExpenseCategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'is_active' => $this->is_active];
    }
}
