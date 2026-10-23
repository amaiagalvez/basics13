<?php

namespace Basics13\Concerns;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Database\Eloquent\SoftDeletes;

trait TracksAuditColumns
{
    public static function bootTracksAuditColumns(): void
    {
        static::creating(static function (self $model): void {
            $model->stampCreatedBy();
            $model->stampUpdatedBy();
        });

        static::updating(static function (self $model): void {
            $model->stampUpdatedBy();
        });

        if (in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            static::registerModelEvent('trashed', static function (self $model): void {
                $model->stampDeletedBy();
                $model->stampUpdatedBy();

                $model->writeStampedAuditColumns();
            });

            static::registerModelEvent('restoring', static function (self $model): void {
                $model->setAttribute('deleted_by', null);
            });
        }
    }

    protected function stampCreatedBy(): void
    {
        if (($actor = $this->creatorActorId()) !== null) {
            $this->setAttribute('created_by', $actor);
        }
    }

    protected function stampUpdatedBy(): void
    {
        if (($actor = $this->updaterActorId()) !== null) {
            $this->setAttribute('updated_by', $actor);
        }
    }

    protected function stampDeletedBy(): void
    {
        if (($actor = $this->updaterActorId()) !== null) {
            $this->setAttribute('deleted_by', $actor);
        }
    }

    /**
     * Persist the audit columns stamped on this instance by a soft delete.
     *
     * SoftDeletes::runSoftDelete() updates deleted_at and updated_at and nothing else, so what the
     * `trashed` hook stamped is still unsaved once the row is soft deleted. This writes exactly the
     * stamped columns on that same row, before the caller's delete() returns.
     */
    protected function writeStampedAuditColumns(): void
    {
        $audit = [];

        foreach (['updated_by', 'deleted_by'] as $column) {
            if ($this->isDirty($column)) {
                $audit[$column] = $this->getAttribute($column);
            }
        }

        if ($audit === []) {
            return;
        }

        $this->setKeysForSaveQuery($this->newModelQuery())->update($audit);

        $this->syncOriginalAttributes(array_keys($audit));
    }

    /**
     * Get the actor ID for created_by: during impersonation, this is the impersonated user (Auth::id()).
     */
    protected function creatorActorId(): ?int
    {
        $actor = Auth::id();

        return $actor === null ? null : (int) $actor;
    }

    /**
     * Get the actor ID for updated_by/deleted_by: during impersonation, this is the original superadmin (session('impersonator_id')).
     */
    protected function updaterActorId(): ?int
    {
        $actor = Session::get('impersonator_id') ?? Auth::id();

        return $actor === null ? null : (int) $actor;
    }
}
