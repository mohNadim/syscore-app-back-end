<?php

use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('ping', fn () => response()->json(['message' => __('api.pong')]))->name('ping');

    // public + user routes (middleware auth:sanctum, user) added in later phases
    Route::prefix('admin')->name('admin.')->group(function () {
        // staff routes (middleware auth:sanctum, staff, permission:...) added in later phases
    });
});
