<?php

namespace Basics13\Concerns;

use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Wraps soft delete in a transaction on the model's connection.
 *
 * This ensures that any `deleting` hooks registered by integration packages
 * (which acquire row locks in their own inner transactions) keep those locks
 * until the soft delete is actually written. Without this, a child record
 * created between the guard's check and the delete would slip through on any
 * path that does not open a transaction itself, including a plain
 * `$model->delete()` outside a controller.
 *
 * Only applies when the model uses SoftDeletes; otherwise it is a no-op.
 *
 * @phpstan-ignore trait.unused
 */
trait TransactionalDelete
{
    /**
     * Soft deletes inside a transaction on the model's own connection.
     */
    public function delete(): ?bool
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive(static::class), true)) {
            return parent::delete();
        }

        return $this->getConnection()->transaction(fn (): ?bool => parent::delete());
    }
}
