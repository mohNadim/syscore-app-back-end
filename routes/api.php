<?php

use App\Http\Controllers\Api\V1\AuthOtpController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('ping', fn () => response()->json(['message' => __('api.pong')]))->name('ping');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('otp/request', [AuthOtpController::class, 'requestCode'])->middleware('throttle:otp-request')->name('otp.request');
        Route::post('otp/verify', [AuthOtpController::class, 'verifyCode'])->middleware('throttle:otp-verify')->name('otp.verify');
        Route::post('logout', [AuthOtpController::class, 'logout'])->middleware(['auth:sanctum', 'user'])->name('logout');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        // staff routes (middleware auth:sanctum, staff, permission:...) added in later phases
    });
});
