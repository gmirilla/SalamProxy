<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class ProxyController extends Controller
{
    /**
     * Build a pre-configured HTTP client for outbound calls.
     * Running on a Nigerian VPS so no special SSL workarounds needed.
     */
    private function httpClient(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::timeout(30)
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36',
                'Accept'     => 'application/json',
            ]);
    }

    // ── eCMR (NPF) ────────────────────────────────────────────────────────────

    /**
     * POST /api/ecmr/login
     * Authenticates with the NPF eCMR API and returns the token.
     */
    public function ecmrLogin()
    {
        try {
            $response = $this->httpClient()
                ->post(env('eMCR_URL') . 'api/apiuser/login', [
                    'username' => env('eMCR_USERNAME'),
                    'password' => env('eMCR_PASSWORD'),
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['error' => 'eCMR login connection failed: ' . $e->getMessage()], 502);
        }

        return response($response->body(), $response->status())
            ->header('Content-Type', 'application/json');
    }

    /**
     * GET /api/ecmr/lookup?token=...&regno=...
     * Looks up a licence plate on the NPF eCMR API.
     */
    public function ecmrLookup(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'regno' => 'required|string',
        ]);

        try {
            $response = $this->httpClient()
                ->withToken($request->query('token'))
                ->get(env('eMCR_URL') . 'api/insurance/cmrisinfo/v1/license/' . $request->query('regno'));
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            return response()->json(['error' => 'eCMR lookup connection failed: ' . $e->getMessage()], 502);
        }

        return response($response->body(), $response->status())
            ->header('Content-Type', 'application/json');
    }

    // ── Add other blocked API calls below as needed ───────────────────────────
    // Each method follows the same pattern:
    //   1. Call $this->httpClient()->...
    //   2. Catch ConnectionException and return 502
    //   3. Return raw response body + status so the caller gets the original payload
}
