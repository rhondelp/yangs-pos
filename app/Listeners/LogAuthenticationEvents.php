<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\Audit\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

// Web (session) logins. API token logins are logged in Api\V1\AuthController.
class LogAuthenticationEvents
{
    public function __construct(private ActivityLogger $logger) {}

    public function handleLogin(Login $event): void
    {
        $this->logger->log('auth.login', $event->user, actor: $event->user);
    }

    public function handleLogout(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->logger->log('auth.logout', $event->user, actor: $event->user);
        }
    }

    public function handleFailed(Failed $event): void
    {
        $this->logger->log('auth.login_failed', $event->user, new: ['username' => $event->credentials['username'] ?? null]);
    }
}
