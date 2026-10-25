<?php

namespace App\Services\Academies;

use App\Models\TrainingSession;

readonly class GenerationResult
{
    /**
     * @param  list<TrainingSession>  $created
     * @param  list<string>  $skippedClosed  local dates (Y-m-d) skipped because of a closure
     */
    public function __construct(
        public array $created = [],
        public array $skippedClosed = [],
    ) {}
}
