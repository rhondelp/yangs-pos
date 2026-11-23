<?php

namespace App\Http\Resources\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** One expense. The receipt is fetched from GET expenses/{id}/attachment; its storage path is never exposed. @mixin \App\Models\Expense */
class ExpenseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'category_id' => $this->expense_category_id,
            'category' => $this->category?->name,
            'description' => $this->description,
            'amount' => $this->amount,
            'expense_date' => $this->expense_date->toDateString(),
            'has_attachment' => $this->attachment_path !== null,
            'attachment_name' => $this->attachment_name,
            'recorded_by' => $this->recordedBy?->name,
            'recorded_at' => $this->created_at->toIso8601String(),
            'voided' => $this->isVoided(),
            'voided_at' => $this->voided_at?->toIso8601String(),
            'voided_by' => $this->voidedBy?->name,
            'void_reason' => $this->void_reason,
        ];
    }
}
