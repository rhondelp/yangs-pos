<?php

namespace App\Enums;

/**
 * Table status. People switch only between the manual states; Occupied and Awaiting payment
 * are set by the tab (session) service through TableStatusService with $system = true.
 */
enum TableStatus: string
{
    case Available = 'available';
    case Occupied = 'occupied';
    case AwaitingPayment = 'awaiting_payment';
    case Reserved = 'reserved';
    case Cleaning = 'cleaning';

    public function label(): string
    {
        return match ($this) {
            self::AwaitingPayment => 'Awaiting payment',
            default => ucfirst($this->value),
        };
    }

    public function isManual(): bool
    {
        return in_array($this, [self::Available, self::Reserved, self::Cleaning], true);
    }

    public function canMoveTo(self $to, bool $system): bool
    {
        if ($this->isManual() && $to->isManual()) {
            return $this !== $to;
        }

        if (! $system) {
            return false;
        }

        return match ($this) {
            self::Available, self::Reserved => $to === self::Occupied,
            self::Occupied => in_array($to, [self::AwaitingPayment, self::Available, self::Cleaning], true),
            self::AwaitingPayment => in_array($to, [self::Occupied, self::Available, self::Cleaning], true),
            self::Cleaning => false,
        };
    }

    /** @return list<self> manual statuses a person may pick from here */
    public function manualTargets(): array
    {
        return array_values(array_filter(self::cases(), fn (self $to) => $to->isManual() && $this->canMoveTo($to, system: false)));
    }
}
