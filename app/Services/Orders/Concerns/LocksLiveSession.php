<?php

namespace App\Services\Orders\Concerns;

use App\Models\TableSession;
use Illuminate\Validation\ValidationException;

/** Row-locks a tab for the rest of the caller's transaction and refuses one that is closed or cancelled. */
trait LocksLiveSession
{
    private function lockLiveSession(int $sessionId): TableSession
    {
        return $this->ensureLive($this->lockSession($sessionId));
    }

    /** Lock only. Idempotent writers check for a replay first: the request they repeat may be the one that closed the tab. */
    private function lockSession(int $sessionId): TableSession
    {
        return TableSession::whereKey($sessionId)->lockForUpdate()->firstOrFail();
    }

    private function ensureLive(TableSession $session): TableSession
    {
        if (! $session->status->isOpen()) {
            throw ValidationException::withMessages(['session' => 'This tab is closed.']);
        }

        return $session;
    }
}
