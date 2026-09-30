<?php

declare(strict_types=1);

use Aenzenith\BirFatura\Http\Controllers\IntegrationController;
use Illuminate\Support\Facades\Route;

// Paths are fixed by BirFatura: it appends them to the site address set in its panel.
Route::post('api/orderStatus', [IntegrationController::class, 'orderStatus'])->name('order-status');
Route::post('api/paymentMethods', [IntegrationController::class, 'paymentMethods'])->name('payment-methods');
Route::post('api/orders', [IntegrationController::class, 'orders'])->name('orders');
Route::post('api/orderCargoUpdate', [IntegrationController::class, 'orderCargoUpdate'])->name('cargo-update');
Route::post('api/invoiceLinkUpdate', [IntegrationController::class, 'invoiceLinkUpdate'])->name('invoice-link');
