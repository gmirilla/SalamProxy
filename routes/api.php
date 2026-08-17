<?php

use App\Http\Controllers\ProxyController;
use Illuminate\Support\Facades\Route;

// ── Unauthenticated ─────────────────────────────────────
// Tightly throttled: this is the one route the public can hit directly
// against the Elite DB, so it needs its own brute-force guard.
Route::middleware('throttle:10,1')->group(function () {
    Route::get('/v1/policy/customer-lookup', [ProxyController::class, 'policyCustomerLookup']);
});

Route::middleware(['proxy.auth', 'throttle:60,1'])->group(function () {

    // ── eCMR (NPF) ──────────────────────────────────────────
    Route::post('/ecmr/login',  [ProxyController::class, 'ecmrLogin']);
    Route::get('/ecmr/lookup',  [ProxyController::class, 'ecmrLookup']);

    // ── Elite DB ────────────────────────────────────────────
    Route::get('/claim/check',   [ProxyController::class, 'claimCheck']);
    Route::get('/elite/brokers',          [ProxyController::class, 'brokerList']);
    Route::get('/elite/broker/policies',  [ProxyController::class, 'brokerPolicies']);

    // ── Elite Policy Risk Update ─────────────────────────────
    Route::post('/elite/policy/risk/cert-airworthiness', [ProxyController::class, 'updatePolicyRiskAirworthiness']);
    Route::post('/elite/policy/risk/cert-airworthiness/batch', [ProxyController::class, 'updatePolicyRiskAirworthinessBatch']);

    // ── Add other blocked API routes here ───────────────────

});
