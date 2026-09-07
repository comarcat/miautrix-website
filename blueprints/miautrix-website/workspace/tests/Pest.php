<?php

/*
|--------------------------------------------------------------------------
| Pest bootstrap
|--------------------------------------------------------------------------
|
| This is the file Pest actually reads — there is no `pest.config.php`. It binds
| the base TestCase to every test directory and turns on RefreshDatabase for the
| suites that touch PostgreSQL. Environment for tests comes from `phpunit.xml`,
| which points DB_DATABASE at the `miautrix_test` database created in §10 Bootstrap.
|
*/

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/*
|--------------------------------------------------------------------------
| Shared expectations
|--------------------------------------------------------------------------
*/

expect()->extend('toBeSlug', function () {
    expect($this->value)->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');

    return $this;
});

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

function tableExists(string $table): bool
{
    return \Illuminate\Support\Facades\Schema::hasTable($table);
}
