<?php

use App\Http\Controllers\Payment\CinetPayController;
use Illuminate\Support\Facades\Route;

// Gateway-specific callback endpoints
// Each gateway has its own callback endpoint for clear separation
// These endpoints receive POST notifications when payment status changes

// CinetPay callback endpoint
// Validates: Requirements 6.1, 6.2, 6.3, 6.6
Route::post('/cinetpay/callback', [CinetPayController::class, 'handleCinetPayCallback'])
    ->name('payment.callback.cinetpay')
    ->middleware(['throttle:60,1']); // Allow more requests for callbacks (60 per minute)

// Tranzak callback endpoint
// Validates: Requirements 6.1, 6.2, 6.3, 6.6
Route::post('/tranzak/callback', [CinetPayController::class, 'handleTranzakCallback'])
    ->name('payment.callback.tranzak')
    ->middleware(['throttle:60,1']); // Allow more requests for callbacks (60 per minute)

// Legacy CinetPay IPN endpoint (for backward compatibility)
// This endpoint receives POST notifications from CinetPay when payment status changes
Route::post('/cinetpay/ipn', [CinetPayController::class, 'handleIPN'])
    ->name('cinetpay.ipn')
    ->middleware(['throttle:60,1']); // Allow more requests for IPN (60 per minute)
