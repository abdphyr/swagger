<?php

use Abdphyr\Swagger\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;


if (app()->environment() != 'production') {
    $config = config('swagger', []);
    foreach ($config as $page => $properties) {
        Route::get(config("swagger.$page.ui_endpoint", "/swagger/$page-ui"), [SwaggerController::class, "$page-ui"])->name("$page-ui");
        Route::get(config("swagger.$page.data_endpoint", "/swagger/$page-data"), [SwaggerController::class, "$page-data"])->name("$page-data");
    }
}