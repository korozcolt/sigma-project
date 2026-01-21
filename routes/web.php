<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;
use App\Http\Controllers\PublicVoterRegistrationController;
use App\Http\Controllers\PublicCampaignLogoController;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('media/campaign-logo/{filename}', PublicCampaignLogoController::class)
    ->where('filename', '[A-Za-z0-9._-]+')
    ->name('public.campaign-logo');

// Registro público de votantes mediante enlace
Route::get('registro/{token}', [PublicVoterRegistrationController::class, 'show'])
    ->name('public.voters.register')
    ->middleware(['invitation.required']);

Route::post('registro/{token}', [PublicVoterRegistrationController::class, 'store'])
    ->name('public.voters.register.submit')
    ->middleware(['invitation.required']);

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified', 'redirect.role'])
    ->name('dashboard');

// Survey routes (public)
Volt::route('surveys/{surveyId}/apply', 'surveys.apply-survey')
    ->name('surveys.apply');

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('profile.edit');
    Volt::route('settings/password', 'settings.password')->name('user-password.edit');
    Volt::route('settings/appearance', 'settings.appearance')->name('appearance.edit');

    Volt::route('settings/two-factor', 'settings.two-factor')
        ->middleware(
            when(
                Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                ['password.confirm'],
                [],
            ),
        )
        ->name('two-factor.show');
});

// Campaign Admin routes
Route::middleware(['auth', 'role:admin_campaign'])->prefix('campaign-admin')->name('campaign-admin.')->group(function () {
    Volt::route('dashboard', 'campaign-admin.dashboard')->name('dashboard');

    // Exportes de usuarios
    Route::get('users/export/coordinators', [\App\Http\Controllers\CampaignAdmin\CoordinatorsExportController::class, '__invoke'])->name('users.export.coordinators');
    Route::get('users/export/witnesses', [\App\Http\Controllers\CampaignAdmin\WitnessesExportController::class, '__invoke'])->name('users.export.witnesses');
    Route::get('users/export/annotators', [\App\Http\Controllers\CampaignAdmin\AnnotatorsExportController::class, '__invoke'])->name('users.export.annotators');
});

// Coordinator routes
Route::middleware(['auth', 'role:coordinator,admin_campaign,super_admin'])->prefix('coordinator')->name('coordinator.')->group(function () {
    Route::redirect('/', '/coordinator/dashboard');
    Volt::route('dashboard', 'coordinator.dashboard')->name('dashboard');
    Volt::route('leaders', 'coordinator.leaders')->name('leaders');
    Volt::route('leaders/create', 'coordinator.create-leader')->name('leaders.create');
    Volt::route('leaders/{leader}/edit', 'coordinator.edit-leader')->name('leaders.edit');
    Volt::route('leaders/{leader}/voters', 'coordinator.leader-voters')->name('leaders.voters');

    Route::get('leaders/export', [\App\Http\Controllers\Coordinator\LeadersExportController::class, '__invoke'])->name('leaders.export');
});

// Leader routes
Route::middleware(['auth', 'role:leader'])->prefix('leader')->name('leader.')->group(function () {
    Route::redirect('/', '/leader/dashboard');
    Volt::route('dashboard', 'leader.dashboard')->name('dashboard');
    Volt::route('register-voter', 'leader.register-voter')->name('register-voter');
    Volt::route('my-voters', 'leader.my-voters')->name('my-voters');
});
