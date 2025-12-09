<?php

use App\Http\Controllers\Api\Admin\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\Auth\RegisterController;
use App\Http\Controllers\Api\Auth\LoginController;
use App\Http\Controllers\Api\Auth\LogoutController;
use App\Http\Controllers\Api\Auth\VerifyEmailController;
use App\Http\Controllers\Api\Auth\PasswordResetController;
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
use App\Http\Controllers\Api\Admin\ReservationController as AdminReservationController;
use App\Http\Controllers\Api\Admin\PaymentController as AdminPaymentController;
use App\Http\Controllers\Api\Admin\LogController as AdminLogController;
use App\Http\Controllers\Api\Admin\BackupController as AdminBackupController;
use App\Http\Controllers\Api\Admin\SettingsController as AdminSettingsController;

/**
 * Public Routes
 */
Route::post('/register', [RegisterController::class, 'register']);
Route::post('/login', [LoginController::class, 'login']);
Route::get('/auth/google/redirect', [LoginController::class, 'redirectToGoogle'])->name('google.redirect');
Route::get('/auth/google/callback', [LoginController::class, 'handleGoogleCallback'])->name('google.callback');

// Email verification route - must be named 'verification.verify' for VerifyEmail notification
Route::get('/email/verify/{id}/{hash}', VerifyEmailController::class)
    ->name('verification.verify');

// Password reset (API-only)
Route::post('/password/forgot', [PasswordResetController::class, 'sendResetLinkEmail']);
Route::post('/password/reset', [PasswordResetController::class, 'reset']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LogoutController::class, 'logout']);

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

    // Reservations (hotel-scoped)
    Route::get('/reservations', [AdminReservationController::class, 'index']);
    Route::post('/reservations', [AdminReservationController::class, 'store']);
    Route::get('/reservations/{id}', [AdminReservationController::class, 'show']);
    Route::put('/reservations/{id}', [AdminReservationController::class, 'update']);
    Route::delete('/reservations/{id}', [AdminReservationController::class, 'destroy']);
    Route::patch('/reservations/{id}/confirm', [AdminReservationController::class, 'confirm']);
    Route::patch('/reservations/{id}/cancel', [AdminReservationController::class, 'cancel']);

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

    // Settings (hotel-scoped)
    Route::get('/settings', [AdminSettingsController::class, 'get']);
    Route::put('/settings', [AdminSettingsController::class, 'update']);
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
    Route::get('/settings/hotel/{hotelId}', [SuperAdminSettingsController::class, 'getHotel']);
    Route::put('/settings/hotel/{hotelId}', [SuperAdminSettingsController::class, 'updateHotel']);

    // Notifications
    Route::get('/notifications', [SuperAdminNotificationController::class, 'index']);
    Route::patch('/notifications/{id}/read', [SuperAdminNotificationController::class, 'markRead']);
});
