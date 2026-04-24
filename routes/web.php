<?php

use App\Http\Controllers\DashboardExportController;
use App\Livewire\Agreements\Show as AgreementShow;
use App\Livewire\Dashboard\Foundation as FoundationDashboard;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', FoundationDashboard::class)->name('dashboard');
    Route::get('dashboard/export', DashboardExportController::class)->name('dashboard.export');
    Route::get('agreements/{agreement}', AgreementShow::class)->name('agreements.show');
});

require __DIR__.'/settings.php';
