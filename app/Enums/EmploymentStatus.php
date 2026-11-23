<?php

namespace App\Enums;

enum EmploymentStatus: string
{
    case Active = 'active';
    case OnLeave = 'on_leave';
    case Resigned = 'resigned';
    case Terminated = 'terminated';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Active',
            self::OnLeave => 'On leave',
            self::Resigned => 'Resigned',
            self::Terminated => 'Terminated',
        };
    }

    /** Has left: needs a separation date, gets no new shifts. */
    public function isSeparated(): bool
    {
        return $this === self::Resigned || $this === self::Terminated;
    }
}
