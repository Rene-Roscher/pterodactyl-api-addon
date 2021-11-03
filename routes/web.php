<?php

use Illuminate\Support\Facades\Route;
use Xepare\PterodactylApiAddon\Http\Controllers\ApiKeyController;

Route::group(['prefix' => '/api/application/users', 'middleware' => ['api', 'throttle:api.application']], function () {
    /** Api-Keys */
    Route::get('{user}/api-keys', [ApiKeyController::class, 'index']);
    Route::post('{user}/api-keys', [ApiKeyController::class, 'store']);
    Route::delete('{user}/api-keys/{identifier}', [ApiKeyController::class, 'delete']);
});
