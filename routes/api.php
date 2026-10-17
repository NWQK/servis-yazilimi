<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\{AuthController,BusinessController};
use App\Http\Middleware\MobileSession;
Route::prefix('v1')->name('mobile.')->group(function(){
    Route::post('auth/login',[AuthController::class,'login'])->middleware('throttle:10,1');
    Route::middleware('auth:sanctum')->group(function(){
        Route::post('auth/logout',[AuthController::class,'logout'])->name('logout');
        Route::post('auth/2fa',[AuthController::class,'verify'])->middleware([MobileSession::class.':pending','throttle:10,1']);
        Route::middleware(MobileSession::class)->group(function(){
            Route::get('session',[AuthController::class,'session']);
            Route::get('devices',[AuthController::class,'devices']);
            Route::delete('devices/{id}',[AuthController::class,'revoke'])->whereNumber('id');
            Route::get('dashboard',[BusinessController::class,'dashboard']);
            Route::get('notifications',[\App\Http\Controllers\Api\V1\NotificationController::class,'index']);
            Route::put('push-device',[\App\Http\Controllers\Api\V1\PushDeviceController::class,'store']);
            Route::delete('push-device',[\App\Http\Controllers\Api\V1\PushDeviceController::class,'destroy']);
            Route::post('notifications/{id}/read',[\App\Http\Controllers\Api\V1\NotificationController::class,'read'])->whereNumber('id');
            Route::get('appointments/{id}',[\App\Http\Controllers\Api\V1\NotificationController::class,'appointment'])->whereNumber('id');
            Route::post('customers',[BusinessController::class,'storeCustomer']);
            Route::get('{kind}',[BusinessController::class,'index'])->whereIn('kind',['customers','vehicles','services']);
            Route::get('{kind}/{id}',[BusinessController::class,'show'])->whereIn('kind',['customers','vehicles','services'])->whereNumber('id');
        });
    });
});
