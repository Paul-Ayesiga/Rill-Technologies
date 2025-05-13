<?php

use App\Http\Controllers\Admin\settings\PasswordController;
use App\Http\Controllers\Admin\settings\ProfileController;
use App\Http\Controllers\Admin\settings\SessionController;

use App\Http\Controllers\Admin\AdminController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('auth')->group(function () {
    Route::get('admin/dashboard', [AdminController::class, 'index'])->name('admin.dashboard');




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
