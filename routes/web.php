<?php

use Abdphyr\Swagger\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;


if (app()->environment() != 'production') {
    Route::get('swagger-ui', [SwaggerController::class, 'swaggerUI'])->name('swagger-ui');
    Route::get('swagger-document', [SwaggerController::class, 'swaggerDocument'])->name('swagger-document');
}