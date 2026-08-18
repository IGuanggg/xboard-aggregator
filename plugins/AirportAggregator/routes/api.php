<?php

use Illuminate\Support\Facades\Route;
use Plugin\AirportAggregator\Controllers\AirportController;

Route::prefix('/api/v2/' . admin_setting(
    'secure_path',
    admin_setting('frontend_admin_path', hash('crc32b', config('app.key')))
))
    ->middleware(['admin', 'log'])
    ->group(function (): void {
        Route::prefix('plugin/airport-aggregator/airports')->group(function (): void {
            Route::get('/fetch', [AirportController::class, 'fetch']);
            Route::post('/save', [AirportController::class, 'save']);
            Route::post('/delete', [AirportController::class, 'delete']);
            Route::post('/pull', [AirportController::class, 'pull']);
            Route::post('/pull-all', [AirportController::class, 'pullAll']);
            Route::post('/refresh-usage', [AirportController::class, 'refreshUsage']);
        });
        Route::prefix('plugin/airport-aggregator/nodes')->group(function (): void {
            Route::get('/fetch', [AirportController::class, 'nodes']);
            Route::post('/update', [AirportController::class, 'updateNode']);
            Route::post('/delete', [AirportController::class, 'deleteNode']);
        });
    });
