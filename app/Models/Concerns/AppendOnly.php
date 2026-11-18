<?php

namespace App\Models\Concerns;

use LogicException;

/**
 * History records that are written once and never changed (CLAUDE.md "Corrections are new records").
 * The table also needs the reject_append_only_change() trigger, which stops raw SQL too.
 */
trait AppendOnly
{
    public static function bootAppendOnly(): void
    {
        $refuse = fn () => throw new LogicException((new static)->getTable().' is append-only; record a new entry instead.');
        static::updating($refuse);
        static::deleting($refuse);
    }
}
