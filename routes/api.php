<?php

use App\Http\Controllers\ProxyController;
use Illuminate\Support\Facades\Route;

// ── Unauthenticated ─────────────────────────────────────
Route::get('/v1/policy/customer-lookup', [ProxyController::class, 'policyCustomerLookup']);

Route::middleware('proxy.auth')->group(function () {

    // ── eCMR (NPF) ──────────────────────────────────────────
    Route::post('/ecmr/login',  [ProxyController::class, 'ecmrLogin']);
    Route::get('/ecmr/lookup',  [ProxyController::class, 'ecmrLookup']);

    // ── Elite DB ────────────────────────────────────────────
    Route::get('/claim/check', [ProxyController::class, 'claimCheck']);

    // ── Add other blocked API routes here ───────────────────

});
