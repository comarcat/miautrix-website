<?php

namespace App\Contracts;

use App\Models\Import;

/**
 * Seam for the deferred GitHub content import (repo -> Project, Non-Goal #2). Distinct from
 * GitHub-as-dev-platform (remote, Actions, branch protection), which IS in v1 from step 1 —
 * this interface exists so the seam is explicit and the two are never conflated. Unimplemented
 * in v1: the `imports`/`import_records` tables exist, nothing binds this interface yet.
 */
interface PortfolioIntegration
{
    /**
     * Fetch and persist ImportRecord rows for the given Import. Left unimplemented in v1.
     */
    public function sync(Import $import): void;
}
