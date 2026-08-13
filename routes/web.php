<?php

use App\Http\Controllers\EliteUpdateLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/elite/updates', [EliteUpdateLogController::class, 'index'])->middleware('auditlog.auth');
