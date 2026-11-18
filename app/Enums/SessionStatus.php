<?php

namespace App\Enums;

enum SessionStatus: string
{
    case Open = 'open';
    case Billing = 'billing';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /** A live tab: the table is in use. */
    public function isOpen(): bool
    {
        return $this === self::Open || $this === self::Billing;
    }
}
