<?php

use Illuminate\Support\Facades\Route;
use Xepare\PterodactylApiAddon\Http\Controllers\ApiKeyController;
use Xepare\PterodactylApiAddon\Http\Controllers\FreeAllocationController;

Route::middleware(['api', 'throttle:api.application'])->group(function () {

    Route::group(['prefix' => '/api/application/users'], function () {
        /** Api-Keys */
        Route::get('{user}/api-keys', [ApiKeyController::class, 'index']);
        Route::post('{user}/api-keys', [ApiKeyController::class, 'store']);
        Route::delete('{user}/api-keys/{identifier}', [ApiKeyController::class, 'delete']);
    });

    Route::group(['prefix' => '/{node}/allocations'], function () {
        Route::get('/free', FreeAllocationController::class)->name('api.application.allocations');
    });

});
