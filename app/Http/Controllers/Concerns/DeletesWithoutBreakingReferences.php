<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

trait DeletesWithoutBreakingReferences
{
    /**
     * Delete a model inside a transaction and turn a foreign key violation into a 409 response.
     *
     * The optional callback runs first (for example to detach pivot rows). When the delete is
     * blocked by a referencing row, the transaction rolls back so anything the callback changed
     * is restored.
     *
     * @param  Model  $model  the model to delete
     * @param  string  $conflictMessage  the translated message returned when other records still reference the model
     * @param  (callable(): mixed)|null  $beforeDelete  work to run inside the transaction before the delete
     *
     * @throws ConflictHttpException when MySQL rejects the delete because of a foreign key constraint (error 1451)
     * @throws QueryException for any other database failure
     */
    protected function deleteOrConflict(Model $model, string $conflictMessage, ?callable $beforeDelete = null): void
    {
        try {
            DB::transaction(function () use ($model, $beforeDelete): void {
                if ($beforeDelete !== null) {
                    $beforeDelete();
                }

                $model->delete();
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[1] ?? null) === 1451) {
                throw new ConflictHttpException($conflictMessage);
            }

            throw $exception;
        }
    }
}
