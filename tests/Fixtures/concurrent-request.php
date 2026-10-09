<?php

use App\Models\User;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(ConsoleKernel::class)->bootstrap();
$database = $argv[1] ?? '';
if (! $app->environment('testing') || $app->configurationIsCached() || ! preg_match('/^autocare_concurrency_test_[a-f0-9]{16}$/', $database)) {
    throw new RuntimeException('Concurrent request worker requires an isolated test database.');
}
config(['database.default' => 'mysql', 'database.connections.mysql.database' => $database, 'database.connections.mysql.url' => null]);
DB::purge('mysql');
Auth::guard('web')->setUser(User::query()->findOrFail((int) $argv[2]));
$payload = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
echo DB::selectOne('SELECT CONNECTION_ID() AS id')->id."\n";
flush();
$request = Request::create($argv[3], 'POST', $payload, server: ['HTTP_ACCEPT' => 'application/json']);
$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request);
echo json_encode(['status' => $response->getStatusCode()], JSON_THROW_ON_ERROR)."\n";
$kernel->terminate($request, $response);
