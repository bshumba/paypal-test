<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ApiPaypalController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/paypal/create-order', [ApiPaypalController::class, 'createOrder']);
Route::post('/api/paypal/capture-order', [ApiPaypalController::class, 'captureOrder']);
Route::post('/api/paypal/webhook', [ApiPaypalController::class, 'handleWebhook']);

Route::get('/paypal/success', [ApiPaypalController::class, 'success'])->name('paypal.success');
Route::get('/paypal/cancel', [ApiPaypalController::class, 'cancel'])->name('paypal.cancel');
