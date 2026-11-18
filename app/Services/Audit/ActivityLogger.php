<?php

namespace App\Services\Audit;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    /**
     * Call inside the same DB transaction as the change, so the log commits or rolls back with it.
     * Never pass secrets (passwords, tokens, PINs) in $old or $new.
     */
    public function log(string $action, ?Model $subject = null, array $old = [], array $new = [], ?User $actor = null, ?User $approver = null): ActivityLog
    {
        $actor ??= auth()->user();

        return ActivityLog::create([
            'user_id' => $actor?->id,
            'user_name' => $actor?->name,
            'approved_by' => $approver?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'old_values' => $old ?: null,
            'new_values' => $new ?: null,
            'ip_address' => request()?->ip(),
        ]);
    }
}
