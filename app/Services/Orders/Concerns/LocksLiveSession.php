<?php

namespace App\Services\Orders\Concerns;

use App\Models\TableSession;
use Illuminate\Validation\ValidationException;

/** Row-locks a tab for the rest of the caller's transaction and refuses one that is closed or cancelled. */
trait LocksLiveSession
{
    private function lockLiveSession(int $sessionId): TableSession
    {
        $session = TableSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();

        if (! $session->status->isOpen()) {
            throw ValidationException::withMessages(['session' => 'This tab is closed.']);
        }

        return $session;
    }
}
