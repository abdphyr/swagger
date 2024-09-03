<?php

use Abd\Swagger\Http\Controllers\SwaggerController;
use Illuminate\Support\Facades\Route;


Route::get('doc', [SwaggerController::class, 'doc'])->name('doc');