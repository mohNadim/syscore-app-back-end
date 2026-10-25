<?php

use App\Enums\PermissionCode;
use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\BookableUnitController;
use App\Http\Controllers\Api\V1\Admin\FacilityController;
use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SpaceController;
use App\Http\Controllers\Api\V1\Admin\SportController;
use App\Http\Controllers\Api\V1\Admin\StaffController;
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

            Route::middleware('permission:'.PermissionCode::RolesManage->value)->group(function () {
                Route::get('permissions', [PermissionController::class, 'index'])->name('permissions.index');
                Route::apiResource('roles', RoleController::class);
            });

            Route::middleware('permission:'.PermissionCode::StaffManage->value)->group(function () {
                Route::apiResource('staff', StaffController::class);
            });

            Route::middleware('permission:'.PermissionCode::FacilitiesManage->value)->group(function () {
                Route::apiResource('facilities', FacilityController::class);
                Route::apiResource('facilities.spaces', SpaceController::class)->shallow();
                Route::apiResource('bookable-units', BookableUnitController::class);
                Route::put('bookable-units/{bookable_unit}/sports', [BookableUnitController::class, 'syncSports'])->name('bookable-units.sports');
            });

            Route::middleware('permission:'.PermissionCode::SportsManage->value)->group(function () {
                Route::apiResource('sports', SportController::class);
            });
        });
    });
});
