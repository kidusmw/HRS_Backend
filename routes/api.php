<?php

use App\Http\Controllers\Api\Admin\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
use App\Http\Controllers\Api\Shared\ProfileController;
use App\Http\Controllers\Api\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\Api\SuperAdmin\UserController as SuperAdminUserController;
use App\Http\Controllers\Api\SuperAdmin\HotelController as SuperAdminHotelController;
use App\Http\Controllers\Api\SuperAdmin\LogController as SuperAdminLogController;
use App\Http\Controllers\Api\SuperAdmin\BackupController as SuperAdminBackupController;
use App\Http\Controllers\Api\SuperAdmin\SettingsController as SuperAdminSettingsController;
use App\Http\Controllers\Api\SuperAdmin\NotificationController as SuperAdminNotificationController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserManagementController;
use App\Http\Controllers\Api\Admin\RoomController as AdminRoomController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\LogController as AdminLogController;
use App\Http\Controllers\Api\Admin\BackupController as AdminBackupController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Api\Admin\HotelLogoController;
use App\Http\Controllers\Api\Admin\HotelImageController as AdminHotelImageController;
use App\Http\Controllers\Api\Admin\RoomImageController as AdminRoomImageController;
use App\Http\Controllers\Api\Admin\NotificationController as AdminNotificationController;
use App\Http\Controllers\Api\Manager\EmployeeController as ManagerEmployeeController;
use App\Http\Controllers\Api\Manager\AttendanceController as ManagerAttendanceController;
use App\Http\Controllers\Api\Manager\OverrideController as ManagerOverrideController;
use App\Http\Controllers\Api\Manager\AlertController as ManagerAlertController;
use App\Http\Controllers\Api\Manager\BookingController as ManagerBookingController;
use App\Http\Controllers\Api\Manager\ReportController as ManagerReportController;
use App\Http\Controllers\Api\Manager\DashboardController as ManagerDashboardController;
use App\Http\Controllers\Api\Manager\OccupancyController as ManagerOccupancyController;
use App\Http\Controllers\Api\Manager\NotificationController as ManagerNotificationController;
use App\Http\Controllers\Api\Manager\ActivityController as ManagerActivityController;
use App\Http\Controllers\Api\Receptionist\DashboardController as ReceptionistDashboardController;
use App\Http\Controllers\Api\Receptionist\RoomController as ReceptionistRoomController;
use App\Http\Controllers\Api\Receptionist\ReservationController as ReceptionistReservationController;
use App\Http\Controllers\Api\Receptionist\ReportController as ReceptionistReportController;
use App\Http\Controllers\Api\Receptionist\AvailabilityController as ReceptionistAvailabilityController;
use App\Http\Controllers\Api\Customer\HotelController as CustomerHotelController;
use App\Http\Controllers\Api\Customer\ReviewController as CustomerReviewController;
use App\Http\Controllers\Api\Customer\AvailabilityController as CustomerAvailabilityController;
use App\Http\Controllers\Api\Customer\AvailabilityCalendarController as CustomerAvailabilityCalendarController;
use App\Http\Controllers\Api\Customer\Payments\ChapaPaymentController as CustomerChapaPaymentController;
use App\Http\Controllers\Api\ChapaWebhookController;
use App\Http\Controllers\Api\Customer\ReservationIntentController;
use App\Http\Controllers\Api\Customer\CustomerReservationController;  
use App\Http\Controllers\Api\SystemSettingsController;

/**
 * Public Routes
 */
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::get('/auth/google/redirect', [LoginController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('/auth/google/callback', [LoginController::class, 'handleGoogleCallback'])->name('google.callback');

// Public system settings (for favicon/title, etc.)
Route::get('/system/settings', SystemSettingsController::class);

// Email verification route - must be named 'verification.verify' for VerifyEmail notification
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->name('verification.verify');

// Password reset (API-only)
Route::post('/password/forgot', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/password/reset', [PasswordResetController::class, 'reset']);

/**
 * Customer API (Public - no auth required)
 * Base: /api/customer/*
 */
Route::prefix('customer')->group(function () {
    // Hotels
    Route::get('/hotels', [CustomerHotelController::class, 'index']);
    Route::get('/hotels/{id}', [CustomerHotelController::class, 'show']);
    
    // Reviews
    Route::get('/hotels/{hotelId}/reviews', [CustomerReviewController::class, 'index']);
    
    // Availability
    Route::get('/hotels/{hotelId}/availability', [CustomerAvailabilityController::class, 'show']);
    Route::get('/hotels/{hotelId}/availability/checkin-dates', [CustomerAvailabilityCalendarController::class, 'checkInDates']);
    Route::get('/hotels/{hotelId}/availability/checkout-dates', [CustomerAvailabilityCalendarController::class, 'checkOutDates']);
});

/**
 * Chapa Webhook (Public - no auth, but should verify signature)
 */
Route::post('/webhooks/chapa', [ChapaWebhookController::class, 'handle'])->name('api.webhooks.chapa');
Route::get('/payments/chapa/verify', [CustomerChapaPaymentController::class, 'verify'])->name('api.payments.chapa.callback');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LogoutController::class, 'logout']);

    // Authenticated user profile
    Route::get('/profile', [ProfileController::class, 'show']);
    Route::put('/profile', [ProfileController::class, 'update']);
    Route::put('/profile/password', [ProfileController::class, 'updatePassword']);

    // Customer Payments (authenticated)
    Route::prefix('customer')->group(function () {
        Route::post('/reservation-intents', [ReservationIntentController::class, 'store']);
        Route::get('/payments/status', [CustomerChapaPaymentController::class, 'status']);
        Route::post('/payments/{paymentId}/refund', [CustomerChapaPaymentController::class, 'refund']);
        Route::get('/reservations', [CustomerReservationController::class, 'index']);
        // Customer reviews (authenticated)
        Route::get('/reviews/mine', [CustomerReviewController::class, 'mine']);
        Route::post('/reviews', [CustomerReviewController::class, 'store']);
    });

    // Protected routes for all authenticated users can be added here
});

// Legacy/admin-scoped routes (kept for compatibility)
Route::middleware(['auth:sanctum', 'role:admin,superadmin'])->group(function () {
    Route::post('/staff', [UserController::class, 'store']);
});

/**
 * Admin API
 * Base: /api/admin/*
 * All routes are hotel-scoped (automatically filtered by authenticated user's hotel_id)
 */
Route::prefix('admin')->middleware(['auth:sanctum', 'role:admin'])->group(function () {
    // Dashboard
    Route::get('/dashboard/metrics', [AdminDashboardController::class, 'metrics']);

    // Users (hotel-scoped)
    Route::get('/users', [AdminUserManagementController::class, 'index']);
    Route::post('/users', [AdminUserManagementController::class, 'store']);
    Route::get('/users/{id}', [AdminUserManagementController::class, 'show']);
    Route::put('/users/{id}', [AdminUserManagementController::class, 'update']);
    Route::delete('/users/{id}', [AdminUserManagementController::class, 'destroy']);

    // Rooms (hotel-scoped)
    Route::get('/rooms', [AdminRoomController::class, 'index']);
    Route::post('/rooms', [AdminRoomController::class, 'store']);
    Route::get('/rooms/{id}', [AdminRoomController::class, 'show']);
    Route::put('/rooms/{id}', [AdminRoomController::class, 'update']);
    Route::delete('/rooms/{id}', [AdminRoomController::class, 'destroy']);

    // Payments (hotel-scoped)
    Route::get('/payments', [AdminPaymentController::class, 'index']);
    Route::get('/payments/{id}', [AdminPaymentController::class, 'show']);

    // Logs (hotel-scoped)
    Route::get('/logs', [AdminLogController::class, 'index']);
    Route::get('/logs/{id}', [AdminLogController::class, 'show']);

    // Backups (hotel-scoped)
    Route::get('/backups', [AdminBackupController::class, 'index']);
    Route::post('/backups', [AdminBackupController::class, 'store']);
    Route::get('/backups/{id}/download', [AdminBackupController::class, 'download']);

    // Hotel images (hotel-scoped)
    Route::get('/hotel-images', [AdminHotelImageController::class, 'index']);
    Route::post('/hotel-images', [AdminHotelImageController::class, 'store']);
    Route::put('/hotel-images/{id}', [AdminHotelImageController::class, 'update']);
    Route::delete('/hotel-images/{id}', [AdminHotelImageController::class, 'destroy']);

    // Room images (hotel-scoped via room ownership)
    Route::get('/room-images', [AdminRoomImageController::class, 'index']);
    Route::post('/room-images', [AdminRoomImageController::class, 'store']);
    Route::put('/room-images/{id}', [AdminRoomImageController::class, 'update']);
    Route::delete('/room-images/{id}', [AdminRoomImageController::class, 'destroy']);

    // Settings (hotel-scoped)
    Route::get('/settings', [AdminSettingsController::class, 'get']);
    Route::put('/settings', [AdminSettingsController::class, 'update']);

    // Hotel Logo (hotel-scoped, file-only)
    Route::get('/hotel-logo', [HotelLogoController::class, 'show']);
    Route::post('/hotel-logo', [HotelLogoController::class, 'store']);

    // Notifications (hotel-scoped via audit logs)
    Route::get('/notifications', [AdminNotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [AdminNotificationController::class, 'markRead']);
});

/**
 * Manager API
 * Base: /api/manager/*
 * All routes are hotel-scoped to the authenticated manager's hotel_id
 */
Route::prefix('manager')->middleware(['auth:sanctum', 'role:manager'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [ManagerDashboardController::class, 'show']);

    // Employees (hotel-scoped, role=receptionist)
    Route::get('/employees', [ManagerEmployeeController::class, 'index']);

    // Attendance
    Route::get('/attendance', [ManagerAttendanceController::class, 'index']);
    Route::post('/attendance', [ManagerAttendanceController::class, 'store']);

    // Overrides
    Route::get('/overrides', [ManagerOverrideController::class, 'index']);
    Route::post('/overrides', [ManagerOverrideController::class, 'store']);

    // Alerts
    Route::get('/alerts', [ManagerAlertController::class, 'index']);

    // Bookings
    Route::get('/bookings', [ManagerBookingController::class, 'index']);

    // Reports
    Route::get('/reports', [ManagerReportController::class, 'index']);

    // Occupancy
    Route::get('/occupancy', [ManagerOccupancyController::class, 'show']);

    // Notifications (hotel-scoped via audit logs)
    Route::get('/notifications', [ManagerNotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [ManagerNotificationController::class, 'markRead']);

    // Activities (receptionist activities)
    Route::get('/activities', [ManagerActivityController::class, 'index']);
});

/**
 * Receptionist API
 * Base: /api/receptionist/*
 * All routes are hotel-scoped to the authenticated receptionist's hotel_id
 */
Route::prefix('receptionist')->middleware(['auth:sanctum', 'role:receptionist'])->group(function () {
    // Dashboard
    Route::get('/dashboard', [ReceptionistDashboardController::class, 'show']);

    // Rooms (hotel-scoped, view-only with status update)
    Route::get('/rooms', [ReceptionistRoomController::class, 'index']);
    Route::get('/rooms/available', [ReceptionistRoomController::class, 'available']);
    Route::patch('/rooms/{id}/status', [ReceptionistRoomController::class, 'updateStatus']);

    // Availability calendar (disable unavailable dates in walk-in flow)
    Route::get('/availability/checkin-dates', [ReceptionistAvailabilityController::class, 'checkInDates']);
    Route::get('/availability/checkout-dates', [ReceptionistAvailabilityController::class, 'checkOutDates']);

    // Reservations (hotel-scoped)
    Route::get('/reservations', [ReceptionistReservationController::class, 'index']);
    Route::post('/reservations', [ReceptionistReservationController::class, 'store']); // Walk-in booking
    Route::patch('/reservations/{id}/confirm', [ReceptionistReservationController::class, 'confirm']);
    Route::patch('/reservations/{id}/cancel', [ReceptionistReservationController::class, 'cancel']);
    Route::patch('/reservations/{id}/check-in', [ReceptionistReservationController::class, 'checkIn']);
    Route::patch('/reservations/{id}/check-out', [ReceptionistReservationController::class, 'checkOut']);

    // Reports (operational level)
    Route::get('/reports', [ReceptionistReportController::class, 'index']);
});

/**
 * Super Admin API
 * Base: /api/super_admin/*
 */

Route::prefix('super_admin')->middleware(['auth:sanctum', 'role:superadmin'])->group(function () {
    // Dashboard
    Route::get('/dashboard/metrics', [SuperAdminDashboardController::class, 'metrics']);

    // Users
    Route::get('/users', [SuperAdminUserController::class, 'index']);
    Route::post('/users', [SuperAdminUserController::class, 'store']);
    Route::get('/users/{id}', [SuperAdminUserController::class, 'show']);
    Route::put('/users/{id}', [SuperAdminUserController::class, 'update']);
    Route::patch('/users/{id}/activate', [SuperAdminUserController::class, 'activate']);
    Route::patch('/users/{id}/deactivate', [SuperAdminUserController::class, 'deactivate']);
    Route::post('/users/{id}/reset-password', [SuperAdminUserController::class, 'resetPassword']);
    Route::delete('/users/{id}', [SuperAdminUserController::class, 'destroy']);

    // Hotels
    Route::get('/hotels', [SuperAdminHotelController::class, 'index']);
    Route::post('/hotels', [SuperAdminHotelController::class, 'store']);
    Route::get('/hotels/{id}', [SuperAdminHotelController::class, 'show']);
    Route::put('/hotels/{id}', [SuperAdminHotelController::class, 'update']);
    Route::delete('/hotels/{id}', [SuperAdminHotelController::class, 'destroy']);

    // Logs
    Route::get('/logs', [SuperAdminLogController::class, 'index']);
    Route::get('/logs/{id}', [SuperAdminLogController::class, 'show']);

    // Backups
    Route::post('/backups/full', [SuperAdminBackupController::class, 'runFull']);
    Route::post('/backups/hotel/{hotelId}', [SuperAdminBackupController::class, 'runHotel']);
    Route::get('/backups', [SuperAdminBackupController::class, 'index']);
    Route::get('/backups/{id}/download', [SuperAdminBackupController::class, 'download']);

    // Settings
    Route::get('/settings/system', [SuperAdminSettingsController::class, 'getSystem']);
    Route::put('/settings/system', [SuperAdminSettingsController::class, 'updateSystem']);
    Route::post('/settings/system', [SuperAdminSettingsController::class, 'updateSystem']);
    Route::get('/settings/hotel/{hotelId}', [SuperAdminSettingsController::class, 'getHotel']);
    Route::put('/settings/hotel/{hotelId}', [SuperAdminSettingsController::class, 'updateHotel']);

    // Notifications
    Route::get('/notifications', [SuperAdminNotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [SuperAdminNotificationController::class, 'markRead']);
});
