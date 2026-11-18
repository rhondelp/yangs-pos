<?php

namespace App\Models\Concerns;

use App\Services\Audit\ActivityLogger;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use LogicException;

/**
 * Soft deletes for business records (CLAUDE.md "No hard deletes"):
 * delete() archives and logs "<model>.archived", restore() logs "<model>.restored",
 * forceDelete() is refused. The table needs $table->softDeletesTz().
 *
 * Call delete()/restore() inside the service's DB transaction so the log commits with the change.
 * Query-builder deletes (Model::where(...)->forceDelete()) bypass model events; never use them on these models.
 */
trait HasAuditedSoftDeletes
{
    use SoftDeletes;

    public static function bootHasAuditedSoftDeletes(): void
    {
        static::deleting(function (self $model) {
            if ($model->isForceDeleting()) {
                $model->forceDeleting = false;

                throw new LogicException('Hard deletes are not allowed on '.static::class.'; archive it with delete() instead.');
            }
        });

        static::softDeleted(fn (self $model) => app(ActivityLogger::class)->log($model->auditName().'.archived', $model));
        static::restored(fn (self $model) => app(ActivityLogger::class)->log($model->auditName().'.restored', $model));
    }

    /** Action prefix, e.g. "product" for App\Models\Product. */
    public function auditName(): string
    {
        return Str::snake(class_basename($this));
    }
}
