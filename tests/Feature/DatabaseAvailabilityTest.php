<?php

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

it('shows a safe service unavailable response when login session storage cannot connect', function (string $method, bool $json): void {
    config([
        'app.debug' => true,
        'session.driver' => 'database',
        'session.connection' => 'unavailable',
        'database.connections.unavailable' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
    ]);
    $failure = new PDOException('private database host and credentials');
    $failure->errorInfo = ['HY000', 2002, 'private connection details'];
    DB::connection('unavailable')->setPdo(static function () use ($failure): never {
        throw $failure;
    });

    $response = $this->call($method, '/login', [], [], [], $json ? ['HTTP_ACCEPT' => 'application/json'] : []);

    $response->assertServiceUnavailable()
        ->assertHeader('Retry-After', '30')
        ->assertDontSee('private database')
        ->assertDontSee('SQLSTATE')
        ->assertDontSee('select *');
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    if ($json) {
        $response->assertJsonStructure(['message'])->assertJsonMissingPath('exception');
    } else {
        $response->assertSee('Service temporarily unavailable');
    }
})->with([
    'login page' => ['GET', false],
    'login submission' => ['POST', false],
    'JSON login request' => ['POST', true],
]);

it('returns a safe JSON outage response for API requests without an accept header', function (): void {
    $failure = new PDOException('private lost connection');
    $failure->errorInfo = ['HY000', 2013, 'Lost connection'];
    Route::get('/api/database-outage-test', static function () use ($failure): never {
        throw new QueryException('mysql', 'select secret from users', [], $failure);
    });

    $this->get('/api/database-outage-test')
        ->assertServiceUnavailable()
        ->assertHeader('Retry-After', '30')
        ->assertJsonStructure(['message'])
        ->assertDontSee('private lost connection')
        ->assertDontSee('select secret');
});

it('does not misclassify an unrelated SQL error as a connection outage', function (): void {
    config(['app.debug' => false]);
    $failure = new PDOException('Duplicate entry');
    $failure->errorInfo = ['23000', 1062, 'Duplicate entry'];
    Route::get('/database-integrity-error-test', static function () use ($failure): never {
        throw new QueryException('mysql', 'insert into users values (?)', ['private value'], $failure);
    });

    $this->get('/database-integrity-error-test')
        ->assertInternalServerError()
        ->assertDontSee('Service temporarily unavailable')
        ->assertDontSee('private value');
});
