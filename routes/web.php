<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Payment\CinetPayController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Authentication Routes
Route::middleware('guest')->group(function () {
    // Registration Routes
    Route::get('/register', [RegisterController::class, 'showRegistrationForm'])
        ->name('register');
    Route::post('/register', [RegisterController::class, 'register']);
    
    // Login Routes
    Route::get('/login', [LoginController::class, 'showLoginForm'])
        ->name('login');
    Route::post('/login', [LoginController::class, 'login'])
        ->middleware('throttle:5,1');
    
    // Password Reset Routes
    Route::get('/password/reset', [PasswordResetController::class, 'showLinkRequestForm'])
        ->name('password.request');
    Route::post('/password/email', [PasswordResetController::class, 'sendResetLinkEmail'])
        ->name('password.email')
        ->middleware('throttle:5,1');
    Route::get('/password/reset/{token}', [PasswordResetController::class, 'showResetForm'])
        ->name('password.reset');
    Route::post('/password/reset', [PasswordResetController::class, 'reset'])
        ->name('password.update');
});

// Logout Route (requires authentication)
Route::post('/logout', [LoginController::class, 'logout'])
    ->name('logout')
    ->middleware('auth');

// CinetPay Payment Routes
Route::prefix('payment')->name('payment.')->group(function () {
    // Gateway selection page
    Route::get('/select-gateway', [CinetPayController::class, 'showGatewaySelection'])
        ->name('select-gateway')
        ->middleware(['auth', 'throttle:10,1']);
    
    // Payment summary page (before redirecting to gateway)
    Route::get('/summary', [CinetPayController::class, 'showPaymentSummary'])
        ->name('summary')
        ->middleware(['auth', 'throttle:10,1']);
    
    // Initiate payment and redirect to selected gateway
    Route::post('/initiate', [CinetPayController::class, 'initiatePayment'])
        ->name('initiate')
        ->middleware(['auth', 'throttle:10,1']);
    
    // Return URL after payment (user redirected back from gateway)
    Route::get('/return/{transactionId}', [CinetPayController::class, 'handleReturn'])
        ->name('return')
        ->middleware(['throttle:10,1']);
    
    // Cancel payment
    Route::get('/cancel/{transactionId}', [CinetPayController::class, 'cancelPayment'])
        ->name('cancel')
        ->middleware(['auth', 'throttle:10,1']);
});
