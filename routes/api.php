<?php

use App\Http\Controllers\ProxyController;
use Illuminate\Support\Facades\Route;

Route::middleware('proxy.auth')->group(function () {

    // ── eCMR (NPF) ──────────────────────────────────────────
    Route::post('/ecmr/login',  [ProxyController::class, 'ecmrLogin']);
    Route::get('/ecmr/lookup',  [ProxyController::class, 'ecmrLookup']);

    // ── Add other blocked API routes here ───────────────────

});
