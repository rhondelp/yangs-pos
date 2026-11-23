<?php

namespace App\Rules;

use App\Services\Staff\ScheduleService;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/** A real ISO week such as "2026-W47" (2025 has no W53). */
class IsoWeek implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-W\d{2}$/', $value) || ScheduleService::weekOf(ScheduleService::monday($value)->toDateString()) !== $value) {
            $fail('Choose a week like 2026-W47.');
        }
    }
}
