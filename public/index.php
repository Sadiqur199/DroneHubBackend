<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Check If The Application Is Under Maintenance
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register The Auto Loader
if (file_exists(__DIR__.'/../vendor/autoload.php')) {
    require __DIR__.'/../vendor/autoload.php';
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
    $response = $kernel->handle(
        $request = Request::capture()
    )->send();
    $kernel->terminate($request, $response);
} else {
    // Graceful response when running before composer install in Docker
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'status' => 'initialized',
        'application' => 'DroneHub Australia Backend Service',
        'company' => 'DroneHub Australia',
        'phone' => '1300 544 968',
        'email' => 'enquiries@dronehub.com.au',
        'base' => 'Mackay, Queensland',
        'note' => 'Backend is ready. Run "docker compose up -d" to start PHP 8.3 FPM, MySQL 8, Redis, and workers.',
    ], JSON_PRETTY_PRINT);
}
