<?php

use App\Http\Controllers\DocumentationController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect('/api/documentation');
});

Route::get('/api/documentation', [DocumentationController::class, 'swagger']);
Route::get('/api/openapi.json', [DocumentationController::class, 'openapi']);
Route::get('/graphql-playground', [DocumentationController::class, 'graphqlPlayground']);
