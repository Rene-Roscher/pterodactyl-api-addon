<?php

use Illuminate\Support\Facades\Route;
use Xepare\PterodactylApiAddon\Http\Controllers\ApiKeyController;
use Xepare\PterodactylApiAddon\Http\Controllers\FileController;
use Xepare\PterodactylApiAddon\Http\Controllers\FreeAllocationController;
use Pterodactyl\Http\Middleware\Api\Client\Server\ResourceBelongsToServer;
use Pterodactyl\Http\Middleware\Api\Client\Server\AuthenticateServerAccess;

Route::prefix('/api/application')->middleware(['api', 'application-api', 'throttle:api.application'])->scopeBindings()->group(function () {
    Route::group(['prefix' => '/users'], function () {
        Route::get('{user:id}/api-keys', [ApiKeyController::class, 'index']);
        Route::post('{user:id}/api-keys', [ApiKeyController::class, 'store']);
        Route::delete('{user:id}/api-keys/{identifier}', [ApiKeyController::class, 'delete']);
    });

    Route::group(['prefix' => '/nodes/{node:id}/allocations'], function () {
        Route::get('/free', FreeAllocationController::class)->name('api.application.allocations.free');
    });
});

Route::middleware(['api', 'client-api', 'throttle:api.client'])->prefix('/api/client')->scopeBindings()->group(function () {
    Route::group(['prefix' => '/servers/{server}', 'middleware' => [AuthenticateServerAccess::class, ResourceBelongsToServer::class]], function () {
        Route::group(['prefix' => '/files'], function () {
            Route::post('/update', FileController::class);
        });
    });
});
