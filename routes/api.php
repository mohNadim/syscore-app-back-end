<?php

use App\Enums\PermissionCode;
use App\Http\Controllers\Api\V1\Admin\AcademyController as AdminAcademyController;
use App\Http\Controllers\Api\V1\Admin\AcademyGroupController;
use App\Http\Controllers\Api\V1\Admin\AuthController as AdminAuthController;
use App\Http\Controllers\Api\V1\Admin\BookableUnitController;
use App\Http\Controllers\Api\V1\Admin\BookingController as AdminBookingController;
use App\Http\Controllers\Api\V1\Admin\CalendarController;
use App\Http\Controllers\Api\V1\Admin\ClosureController;
use App\Http\Controllers\Api\V1\Admin\CoachController as AdminCoachController;
use App\Http\Controllers\Api\V1\Admin\DiscountController;
use App\Http\Controllers\Api\V1\Admin\EnrollmentController as AdminEnrollmentController;
use App\Http\Controllers\Api\V1\Admin\FacilityController;
use App\Http\Controllers\Api\V1\Admin\GroupScheduleController;
use App\Http\Controllers\Api\V1\Admin\OperatingHourController;
use App\Http\Controllers\Api\V1\Admin\PermissionController;
use App\Http\Controllers\Api\V1\Admin\PriceRuleController;
use App\Http\Controllers\Api\V1\Admin\RoleController;
use App\Http\Controllers\Api\V1\Admin\SettingController;
use App\Http\Controllers\Api\V1\Admin\SpaceController;
use App\Http\Controllers\Api\V1\Admin\SportController;
use App\Http\Controllers\Api\V1\Admin\StaffController;
use App\Http\Controllers\Api\V1\Admin\SubscriptionController as AdminSubscriptionController;
use App\Http\Controllers\Api\V1\Admin\TrainingSessionController;
use App\Http\Controllers\Api\V1\Admin\UserCategoryController;
use App\Http\Controllers\Api\V1\Admin\WaitlistController as AdminWaitlistController;
use App\Http\Controllers\Api\V1\AuthOtpController;
use App\Http\Controllers\Api\V1\AvailabilityController;
use App\Http\Controllers\Api\V1\BookingController;
use App\Http\Controllers\Api\V1\BookingQuoteController;
use App\Http\Controllers\Api\V1\BookingSeriesController;
use App\Http\Controllers\Api\V1\Catalog\AcademyController;
use App\Http\Controllers\Api\V1\Catalog\AppConfigController;
use App\Http\Controllers\Api\V1\Catalog\CoachController;
use App\Http\Controllers\Api\V1\Catalog\FacilityController as CatalogFacilityController;
use App\Http\Controllers\Api\V1\Catalog\GalleryAlbumController;
use App\Http\Controllers\Api\V1\Catalog\NewsController;
use App\Http\Controllers\Api\V1\Catalog\SportController as CatalogSportController;
use App\Http\Controllers\Api\V1\ChildController;
use App\Http\Controllers\Api\V1\DeviceController;
use App\Http\Controllers\Api\V1\EnrollmentController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ShamCashWebhookController;
use App\Http\Controllers\Api\V1\SubscriptionController;
use App\Http\Controllers\Api\V1\WaitlistController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('ping', fn () => response()->json(['message' => __('api.pong')]))->name('ping');

    Route::get('facilities', [CatalogFacilityController::class, 'index'])->name('facilities.index');
    Route::get('facilities/{facility}', [CatalogFacilityController::class, 'show'])->name('facilities.show');
    Route::get('sports', [CatalogSportController::class, 'index'])->name('sports.index');
    Route::get('coaches', [CoachController::class, 'index'])->name('coaches.index');
    Route::get('coaches/{coach}', [CoachController::class, 'show'])->name('coaches.show');
    Route::get('academies', [AcademyController::class, 'index'])->name('academies.index');
    Route::get('academies/{academy}', [AcademyController::class, 'show'])->name('academies.show');
    Route::get('news', [NewsController::class, 'index'])->name('news.index');
    Route::get('news/{news}', [NewsController::class, 'show'])->whereNumber('news')->name('news.show');
    Route::get('gallery/albums', [GalleryAlbumController::class, 'index'])->name('gallery.albums.index');
    Route::get('gallery/albums/{album}', [GalleryAlbumController::class, 'show'])->name('gallery.albums.show');
    Route::get('bookings/quote', [BookingQuoteController::class, 'show'])->name('bookings.quote');
    Route::get('availability', [AvailabilityController::class, 'show'])->name('availability.show');
    Route::get('app-config', [AppConfigController::class, 'show'])->name('app-config.show');

    Route::post('webhooks/sham-cash', ShamCashWebhookController::class)->name('webhooks.sham-cash');

    Route::prefix('auth')->name('auth.')->group(function () {
        Route::post('otp/request', [AuthOtpController::class, 'requestCode'])->middleware('throttle:otp-request')->name('otp.request');
        Route::post('otp/verify', [AuthOtpController::class, 'verifyCode'])->middleware('throttle:otp-verify')->name('otp.verify');
        Route::post('logout', [AuthOtpController::class, 'logout'])->middleware(['auth:sanctum', 'user'])->name('logout');
    });

    Route::middleware(['auth:sanctum', 'user'])->group(function () {
        Route::get('me', [ProfileController::class, 'show'])->name('me.show');
        Route::patch('me', [ProfileController::class, 'update'])->name('me.update');

        Route::apiResource('children', ChildController::class);
        Route::get('children/{child}/eligible-groups', [EnrollmentController::class, 'eligibleGroups'])->whereNumber('child')->name('children.eligible-groups');

        Route::get('enrollments', [EnrollmentController::class, 'index'])->name('enrollments.index');
        Route::post('enrollments', [EnrollmentController::class, 'store'])->name('enrollments.store');
        Route::get('subscriptions', [SubscriptionController::class, 'index'])->name('subscriptions.index');
        Route::get('subscriptions/{subscription}', [SubscriptionController::class, 'show'])->whereNumber('subscription')->name('subscriptions.show');
        Route::post('subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew'])->whereNumber('subscription')->name('subscriptions.renew');
        Route::post('subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel'])->whereNumber('subscription')->name('subscriptions.cancel');
        Route::get('waitlist', [WaitlistController::class, 'index'])->name('waitlist.index');
        Route::delete('waitlist/{waitlist_entry}', [WaitlistController::class, 'destroy'])->whereNumber('waitlist_entry')->name('waitlist.destroy');

        Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
        Route::post('payments/sham-cash/initiate', [PaymentController::class, 'initiateShamCash'])->name('payments.sham-cash.initiate');
        Route::get('payments/{payment}', [PaymentController::class, 'show'])->whereNumber('payment')->name('payments.show');

        Route::get('bookings', [BookingController::class, 'index'])->name('bookings.index');
        Route::post('bookings', [BookingController::class, 'store'])->name('bookings.store');
        Route::get('bookings/{booking}', [BookingController::class, 'show'])->whereNumber('booking')->name('bookings.show');
        Route::post('bookings/{booking}/cancel', [BookingController::class, 'cancel'])->whereNumber('booking')->name('bookings.cancel');

        Route::get('booking-series', [BookingSeriesController::class, 'index'])->name('booking-series.index');
        Route::post('booking-series/preview', [BookingSeriesController::class, 'preview'])->name('booking-series.preview');
        Route::post('booking-series', [BookingSeriesController::class, 'store'])->name('booking-series.store');
        Route::get('booking-series/{booking_series}', [BookingSeriesController::class, 'show'])->whereNumber('booking_series')->name('booking-series.show');
        Route::post('booking-series/{booking_series}/cancel', [BookingSeriesController::class, 'cancel'])->whereNumber('booking_series')->name('booking-series.cancel');

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

            Route::middleware('permission:'.PermissionCode::CoachesManage->value)->group(function () {
                Route::apiResource('coaches', AdminCoachController::class);
            });

            Route::middleware('permission:'.PermissionCode::AcademiesManage->value)->group(function () {
                Route::apiResource('academies', AdminAcademyController::class);
                Route::apiResource('academies.groups', AcademyGroupController::class)->shallow();
                Route::apiResource('groups.schedules', GroupScheduleController::class)->shallow();
            });

            Route::middleware('permission:'.PermissionCode::SessionsManage->value)->group(function () {
                Route::get('training-sessions', [TrainingSessionController::class, 'index'])->name('training-sessions.index');
                Route::get('training-sessions/{training_session}', [TrainingSessionController::class, 'show'])->name('training-sessions.show');
                Route::patch('training-sessions/{training_session}', [TrainingSessionController::class, 'update'])->name('training-sessions.update');
                Route::post('training-sessions/{training_session}/cancel', [TrainingSessionController::class, 'cancel'])->name('training-sessions.cancel');
            });

            Route::middleware('permission:'.PermissionCode::PricingManage->value)->group(function () {
                Route::apiResource('price-rules', PriceRuleController::class);
            });

            Route::middleware('permission:'.PermissionCode::UserCategoriesManage->value)->group(function () {
                Route::apiResource('user-categories', UserCategoryController::class);
                Route::get('user-categories/{user_category}/members', [UserCategoryController::class, 'members'])->name('user-categories.members.index');
                Route::post('user-categories/{user_category}/members', [UserCategoryController::class, 'addMembers'])->name('user-categories.members.store');
                Route::delete('user-categories/{user_category}/members/{user}', [UserCategoryController::class, 'removeMember'])->name('user-categories.members.destroy');
            });

            Route::middleware('permission:'.PermissionCode::DiscountsManage->value)->group(function () {
                Route::apiResource('discounts', DiscountController::class);
            });

            Route::middleware('permission:'.PermissionCode::BookingsView->value)->group(function () {
                Route::get('bookings', [AdminBookingController::class, 'index'])->name('bookings.index');
                Route::get('bookings/{booking}', [AdminBookingController::class, 'show'])->whereNumber('booking')->name('bookings.show');
                Route::get('calendar', [CalendarController::class, 'show'])->name('calendar.show');
            });

            Route::middleware('permission:'.PermissionCode::BookingsManage->value)->group(function () {
                Route::post('bookings', [AdminBookingController::class, 'store'])->name('bookings.store');
                Route::post('bookings/{booking}/cancel', [AdminBookingController::class, 'cancel'])->whereNumber('booking')->name('bookings.cancel');
                Route::post('bookings/{booking}/no-show', [AdminBookingController::class, 'noShow'])->whereNumber('booking')->name('bookings.no-show');
            });

            Route::middleware('permission:'.PermissionCode::EnrollmentsManage->value)->group(function () {
                Route::get('enrollments', [AdminEnrollmentController::class, 'index'])->name('enrollments.index');
                Route::post('enrollments', [AdminEnrollmentController::class, 'store'])->name('enrollments.store');
                Route::get('enrollments/{enrollment}', [AdminEnrollmentController::class, 'show'])->whereNumber('enrollment')->name('enrollments.show');
                Route::post('enrollments/{enrollment}/end', [AdminEnrollmentController::class, 'end'])->whereNumber('enrollment')->name('enrollments.end');
                Route::get('subscriptions', [AdminSubscriptionController::class, 'index'])->name('subscriptions.index');
                Route::post('subscriptions/{subscription}/cancel', [AdminSubscriptionController::class, 'cancel'])->whereNumber('subscription')->name('subscriptions.cancel');
                Route::get('groups/{group}/waitlist', [AdminWaitlistController::class, 'index'])->whereNumber('group')->name('groups.waitlist.index');
                Route::post('waitlist/{waitlist_entry}/offer', [AdminWaitlistController::class, 'offer'])->whereNumber('waitlist_entry')->name('waitlist.offer');
                Route::post('waitlist/{waitlist_entry}/remove', [AdminWaitlistController::class, 'remove'])->whereNumber('waitlist_entry')->name('waitlist.remove');
            });

            Route::middleware('permission:'.PermissionCode::HoursManage->value)->group(function () {
                Route::get('facilities/{facility}/operating-hours', [OperatingHourController::class, 'index'])->name('facilities.operating-hours.index');
                Route::put('facilities/{facility}/operating-hours', [OperatingHourController::class, 'update'])->name('facilities.operating-hours.update');
                Route::apiResource('closures', ClosureController::class);
            });

            Route::middleware('permission:'.PermissionCode::SettingsManage->value)->group(function () {
                Route::get('settings', [SettingController::class, 'show'])->name('settings.show');
                Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
            });
        });
    });
});
