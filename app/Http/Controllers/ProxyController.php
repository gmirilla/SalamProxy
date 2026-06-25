<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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

    // ── Elite DB ──────────────────────────────────────────────────────────────

    /**
     * GET /api/claim/check?number=...
     * Queries the Elite PostgreSQL DB for a claim or policy number.
     * Namecheap blocks outbound port 5432 — this runs from the Nigerian VPS instead.
     */
    public function claimCheck(Request $request)
    {
        $request->validate(['number' => 'required|string']);

        $number = $request->query('number');

        try {
            $results = DB::connection('Elite')
                ->table('epgi_claim as e')
                ->join('epgi_policy as p', 'e.policy_id', '=', 'p.id')
                ->select(
                    'p.policy_no',
                    'e.claim_no',
                    'e.description',
                    'e.state',
                    'e.loss_date',
                    'e.notification_date'
                )
                ->where('e.claim_no', $number)
                ->orWhere('p.policy_no', $number)
                ->orderBy('e.loss_date', 'desc')
                ->get();
        } catch (\Exception $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Elite DB query failed: ' . $e->getMessage(),
                'data'    => [],
            ], 502);
        }

        if ($results->isEmpty()) {
            return response()->json([
                'status'  => 'not_found',
                'message' => 'No claim found for the provided number.',
                'data'    => [],
            ]);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Claim retrieved successfully.',
            'data'    => $results->count() === 1 ? $results->first() : $results,
        ]);
    }

    // ── Elite Policy Customer Lookup ──────────────────────────────────────────

    /**
     * GET /api/v1/policy/customer-lookup?policy_no=...&phone=...
     * Unauthenticated. Looks up an approved policy by number, then verifies
     * the caller's phone matches the insured's phone stored in Elite.
     */
    public function policyCustomerLookup(Request $request)
    {
        $request->validate([
            'policy_no' => 'required|string',
            'phone'     => 'required|string',
        ]);

        $policyNo = $request->query('policy_no');
        $phone    = $request->query('phone');

        try {
            $row = DB::connection('Elite')
                ->table('epgi_policy as p')
                ->join('res_partner as i',       'p.insured_id',      '=', 'i.id')
                ->join('product_product as pr',  'p.product_id',      '=', 'pr.id')
                ->join('product_template as pt', 'pr.product_tmpl_id','=', 'pt.id')
                ->select(
                    'p.policy_no',
                    'pt.name as policy_type',
                    'p.date_from',
                    'p.date_to',
                    'i.phone'
                )
                ->where('p.policy_no', $policyNo)
                ->where('p.state', 'approved')
                ->first();
        } catch (\Exception) {
            return response()->json(['found' => false], 502);
        }

        if (!$row || $row->phone !== $phone) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'data'  => [
                'policy_no'   => $row->policy_no,
                'policy_type' => $row->policy_type,
                'start_date'  => $row->date_from,
                'end_date'    => $row->date_to,
            ],
        ]);
    }

    // ── Add other blocked API calls below as needed ───────────────────────────
    // Each method follows the same pattern:
    //   1. Call $this->httpClient()->...
    //   2. Catch ConnectionException and return 502
    //   3. Return raw response body + status so the caller gets the original payload
}
