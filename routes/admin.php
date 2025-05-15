<?php

use App\Http\Controllers\Admin\settings\PasswordController;
use App\Http\Controllers\Admin\settings\ProfileController;
use App\Http\Controllers\Admin\settings\SessionController;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\PlanController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'admin'])->group(function () {
    // Admin Dashboard
    Route::get('admin/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');

    // Customer Management
    Route::prefix('admin/customers')->name('admin.customers.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\CustomersController::class, 'index'])->name('index');
        Route::get('/{id}', [App\Http\Controllers\Admin\CustomersController::class, 'show'])->name('show');
        Route::put('/{id}/status', [App\Http\Controllers\Admin\CustomersController::class, 'updateStatus'])->name('update-status');
    });

    // Subscription Management
    Route::prefix('admin/subscriptions')->name('admin.subscriptions.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\SubscriptionsController::class, 'index'])->name('index');
        Route::get('/{id}', [App\Http\Controllers\Admin\SubscriptionsController::class, 'show'])->name('show');
        Route::post('/{id}/cancel', [App\Http\Controllers\Admin\SubscriptionsController::class, 'cancel'])->name('cancel');
        Route::post('/{id}/resume', [App\Http\Controllers\Admin\SubscriptionsController::class, 'resume'])->name('resume');
        Route::post('/{id}/sync', [App\Http\Controllers\Admin\SubscriptionsController::class, 'sync'])->name('sync');
    });

    // Plans Management
    Route::prefix('admin/plans')->name('admin.plans.')->group(function () {
        Route::get('/', [PlanController::class, 'index'])->name('index');
        Route::post('/', [PlanController::class, 'store'])->name('store');
        Route::put('/{id}', [PlanController::class, 'update'])->name('update');
        Route::delete('/{id}', [PlanController::class, 'destroy'])->name('destroy');
    });

    // Roles & Permissions Management
    Route::prefix('admin/roles')->name('admin.roles.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\RolesController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Admin\RolesController::class, 'store'])->name('store');
        Route::get('/{id}', [App\Http\Controllers\Admin\RolesController::class, 'show'])->name('show');
        Route::put('/{id}', [App\Http\Controllers\Admin\RolesController::class, 'update'])->name('update');
        Route::delete('/{id}', [App\Http\Controllers\Admin\RolesController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('admin/permissions')->name('admin.permissions.')->group(function () {
        Route::get('/', [App\Http\Controllers\Admin\PermissionsController::class, 'index'])->name('index');
        Route::post('/', [App\Http\Controllers\Admin\PermissionsController::class, 'store'])->name('store');
        Route::get('/{id}', [App\Http\Controllers\Admin\PermissionsController::class, 'show'])->name('show');
        Route::put('/{id}', [App\Http\Controllers\Admin\PermissionsController::class, 'update'])->name('update');
        Route::delete('/{id}', [App\Http\Controllers\Admin\PermissionsController::class, 'destroy'])->name('destroy');
    });

    // Admin Settings
    Route::redirect('admin/settings', '/admin/settings/profile');

    Route::get('admin/settings/profile', [ProfileController::class, 'edit'])->name('admin.profile.edit');
    Route::patch('admin/settings/profile', [ProfileController::class, 'update'])->name('admin.aprofile.update');
    Route::delete('admin/settings/profile', [ProfileController::class, 'destroy'])->name('aadmin.profile.destroy');

    Route::get('admin/settings/password', [PasswordController::class, 'edit'])->name('admin.password.edit');
    Route::put('admin/settings/password', [PasswordController::class, 'update'])->name('admin.password.update');

    Route::get('admin/settings/appearance', function () {
        return Inertia::render('admin/settings/Appearance');
    })->name('admin.appearance');

    Route::get('admin/settings/session',[SessionController::class, 'index'])->name('admin.session.index');
    Route::delete('admin/settings/session/{id}', [SessionController::class, 'destroy'])->name('admin.session.destroy');
    Route::post('admin/settings/session/logout-others', [SessionController::class, 'logoutOthers'])->name('admin.session.logout.others');
});
