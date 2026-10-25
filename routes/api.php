<?php

use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\AuthOtpController;
use App\Http\Controllers\Api\V1\ChildController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\ProfileController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('ping', fn () => response()->json(['message' => __('api.pong')]))->name('ping');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('otp/request', [AuthOtpController::class, 'requestCode'])->middleware('throttle:otp-request')->name('otp.request');
        Route::post('otp/verify', [AuthOtpController::class, 'verifyCode'])->middleware('throttle:otp-verify')->name('otp.verify');
        Route::post('logout', [AuthOtpController::class, 'logout'])->middleware(['auth:sanctum', 'user'])->name('logout');
    });

    Route::middleware(['auth:sanctum', 'user'])->group(function () {
        Route::get('me', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('me', [ProfileController::class, 'update'])->name('me.update');

        Route::apiResource('children', ChildController::class);

        Route::post('devices', [DeviceController::class, 'store'])->name('devices.store');
        Route::delete('devices', [DeviceController::class, 'destroy'])->name('devices.destroy');
    });

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('auth/login', [AdminAuthController::class, 'login'])->middleware('throttle:admin-login')->name('auth.login');

        Route::middleware(['auth:sanctum', 'staff'])->group(function () {
            Route::post('auth/logout', [AdminAuthController::class, 'logout'])->name('auth.logout');
            Route::get('me', [AdminAuthController::class, 'me'])->name('me');
        });
    });
});
