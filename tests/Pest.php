<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

/**
 * Minimal DataTables query string. Column metadata must be present or the
 * server-side handler cannot resolve searching and ordering.
 *
 * @param  array<int, string>  $columns
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function dtParams(array $columns, array $overrides = []): array
{
    $params = [
        'draw' => 1,
        'start' => 0,
        'length' => 10,
        'search' => ['value' => '', 'regex' => 'false'],
        'columns' => collect($columns)->map(fn (string $name) => [
            'data' => $name,
            'name' => $name,
            'searchable' => 'true',
            'orderable' => 'true',
            'search' => ['value' => '', 'regex' => 'false'],
        ])->all(),
    ];

    return array_replace_recursive($params, $overrides);
}
