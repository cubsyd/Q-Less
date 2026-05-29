<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

use App\Models\User;

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\FavoriteProductController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\OrderController;

Route::get('/test', function () {
    return response()->json([
        'ok' => true
    ]);
});

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

Route::get('/email/verify/{id}/{hash}', function (
    Request $request,
    $id,
    $hash
) {

    $user = User::find($id);

    if (!$user) {
        abort(404);
    }

    if (!hash_equals(
        sha1($user->getEmailForVerification()),
        $hash
    )) {
        abort(403);
    }

    if (!$user->hasVerifiedEmail()) {
        $user->markEmailAsVerified();
    }

    return redirect('http://localhost:4200/email-verificado');

})->name('verification.verify');

Route::post('/email/verification-notification', function (
    Request $request
) {

    $request->user()->sendEmailVerificationNotification();

    return response()->json([
        'status' => true,
        'message' => 'Correo de verificacion reenviado.'
    ]);

})->middleware('auth:sanctum');

Route::post('/payments/webhook', [PaymentController::class, 'webhook']);

Route::middleware('auth:sanctum')->group(function () {

    Route::post('/logout', [AuthController::class, 'logout']);

    Route::get('/categorias', [CategoriaController::class, 'index']);

    Route::post('/chatbot/recomendar', [ChatbotController::class, 'recommend']);

    Route::get('/carrito/{userId}', [CartController::class, 'index']);
    Route::post('/carrito/reservar', [CartController::class, 'reserve']);
    Route::patch('/carrito/{userId}/{productId}/decrease', [CartController::class, 'decrease']);
    Route::delete('/carrito/{userId}/{productId}', [CartController::class, 'remove']);
    Route::delete('/carrito/{userId}', [CartController::class, 'clear']);
    Route::post('/carrito/checkout', [CartController::class, 'checkout']);

    Route::get('/favoritos/{userId}', [FavoriteProductController::class, 'index']);
    Route::post('/favoritos/toggle', [FavoriteProductController::class, 'toggle']);

    Route::post('/payments/preference', [PaymentController::class, 'createPreference']);
    Route::post('/payments/create-order', [PaymentController::class, 'createOrder']);

    Route::apiResource('productos', ProductoController::class);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::get('/orders/user/{userId}', [OrderController::class, 'userOrders']);
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus']);
    Route::delete('/orders/{id}', [OrderController::class, 'destroy']);
});