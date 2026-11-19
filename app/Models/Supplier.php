<?php

namespace App\Models;

use App\Models\Concerns\HasAuditedSoftDeletes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'contact_person', 'phone', 'email', 'address', 'notes'])]
class Supplier extends Model
{
    use HasAuditedSoftDeletes;

    public function receipts(): HasMany
    {
        return $this->hasMany(StockReceipt::class);
    }
}
