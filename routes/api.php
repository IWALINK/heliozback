<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\BankInformationController;
use App\Http\Controllers\ReferralController;

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);


Route::post('/logout', [AuthController::class, 'logout']);
Route::post('/verify-otp', [AuthController::class, 'verify_otp']);

Route::get('/user', function (Request $request) {
    return AuthController::get_user($request);
});

Route::post('/logout', [AuthController::class, 'logout']);

Route::get('/cart', [CartController::class, 'index']);
Route::post('/cart/store', [CartController::class, 'store']);
Route::post('/cart/clear', [CartController::class, 'clear']);
Route::put('/cart/{productId}', [CartController::class, 'update']);
Route::delete('/cart/{productId}', [CartController::class, 'destroy']);

Route::post('/checkout', [CheckoutController::class, 'checkout']);
Route::get('/orders', [CheckoutController::class, 'getUserOrders']);

Route::get('/env-test', function () {
    dd([
        'APP_URL' => env('APP_URL'),
        'STRIPE_KEY' => env('MAIL_FROM_ADDRESS'),
        'STRIPE_WEBHOOK_SECRET' => env('STRIPE_WEBHOOK_SECRET')
    ]);
});

Route::get('/user/profile', [AuthController::class, 'getProfile']);

Route::get('/bank-information-index', [BankInformationController::class, 'index']);
Route::post('/bank-information', [BankInformationController::class, 'store']);

Route::get('/referral-code', [ReferralController::class, 'getReferralCode']);
Route::get('/referral/referrals', [ReferralController::class, 'showReferrals']);
Route::get('/referral/earnings', [ReferralController::class, 'getReferralEarnings']);

Route::put('/user/profile', [AuthController::class, 'updateProfile']);

Route::post('/reset-password', [AuthController::class, 'send_reset_password_code']);
Route::post('/reset-password/update', [AuthController::class, 'update_password_from_email_code']);
