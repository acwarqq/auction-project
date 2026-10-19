<?php

use App\Http\Controllers\Api\AuctionController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\MeController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);
});

// Lots: public read...
Route::get('/auctions', [AuctionController::class, 'index']);
Route::get('/auctions/{auction}', [AuctionController::class, 'show'])->whereNumber('auction');

// ...everything else requires an authenticated, approved account.
Route::middleware(['auth:sanctum', 'approved'])->group(function () {
    Route::post('/auctions', [AuctionController::class, 'store']);
    Route::put('/auctions/{auction}', [AuctionController::class, 'update'])->whereNumber('auction');
    Route::delete('/auctions/{auction}', [AuctionController::class, 'destroy'])->whereNumber('auction');
    Route::post('/auctions/{auction}/bids', [AuctionController::class, 'placeBid'])->whereNumber('auction');

    Route::get('/me/bids', [MeController::class, 'bids']);
    Route::get('/me/auctions', [MeController::class, 'auctions']);
});
