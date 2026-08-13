<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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

    /**
     * Log the real exception server-side and return a generic message to the caller.
     */
    private function dbErrorResponse(\Exception $e, string $context): \Illuminate\Http\JsonResponse
    {
        Log::error("Elite DB query failed [{$context}]: " . $e->getMessage());

        return response()->json([
            'status'  => 'error',
            'message' => 'Elite DB query failed.',
            'data'    => [],
        ], 502);
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
                ->post(config('proxy.ecmr.url') . 'api/apiuser/login', [
                    'username' => config('proxy.ecmr.username'),
                    'password' => config('proxy.ecmr.password'),
                ]);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('eCMR login connection failed: ' . $e->getMessage());

            return response()->json(['error' => 'eCMR login connection failed.'], 502);
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
            'regno' => ['required', 'string', 'regex:/^[A-Za-z0-9\- ]+$/'],
        ]);

        $regno = rawurlencode($request->query('regno'));

        try {
            $response = $this->httpClient()
                ->withToken($request->query('token'))
                ->get(config('proxy.ecmr.url') . 'api/insurance/cmrisinfo/v1/license/' . $regno);
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::error('eCMR lookup connection failed: ' . $e->getMessage());

            return response()->json(['error' => 'eCMR lookup connection failed.'], 502);
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
            return $this->dbErrorResponse($e, 'claimCheck');
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
     * GET /api/v1/policy/customer-lookup?policy_no=...&email=...
     * Unauthenticated (rate-limited). Looks up an approved policy by number,
     * then verifies the caller's phone or email matches the insured record in Elite.
     */
    public function policyCustomerLookup(Request $request)
    {
        $request->validate([
            'policy_no' => 'required|string',
            'phone'     => 'required_without:email|string',
            'email'     => 'required_without:phone|email',
        ]);

        $policyNo = $request->query('policy_no');
        $phone    = $request->query('phone');
        $email    = $request->query('email');

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
                    'i.phone',
                    'i.email'
                )
                ->where('p.policy_no', $policyNo)
                ->where('p.state', 'approved')
                ->first();
        } catch (\Exception $e) {
            Log::error('Elite DB query failed [policyCustomerLookup]: ' . $e->getMessage());

            return response()->json(['found' => false], 502);
        }

        $verified = $row && (
            ($phone && $row->phone === $phone) ||
            ($email && $row->email === $email)
        );

        if (!$verified) {
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

    // ── Elite Brokers / Agents ────────────────────────────────────────────────

    /**
     * GET /api/elite/brokers
     * Returns all broker and agent partner records from Elite.
     */
    public function brokerList()
    {
        try {
            $rows = DB::connection('Elite')
                ->table('res_partner')
                ->select(
                    'id as broker_id',
                    'name',
                    'website',
                    'email',
                    'phone', 'cust_type'
                )
                ->whereIn('cust_type', ['broker', 'agent'])
                ->orderBy('name')
                ->get();
        } catch (\Exception $e) {
            return $this->dbErrorResponse($e, 'brokerList');
        }

        return response()->json([
            'status' => 'success',
            'data'   => $rows,
        ]);
    }

    // ── Elite Broker Policies ─────────────────────────────────────────────────

    /**
     * GET /api/elite/broker/policies?broker_id=...
     * Returns all policies underwritten through a specific broker/agent.
     */
    public function brokerPolicies(Request $request)
    {
        $request->validate(['broker_id' => 'required|integer']);

        $brokerId = (int) $request->query('broker_id');

        try {
            $rows = DB::connection('Elite')
                ->table('epgi_policy as p')
                ->join('res_partner as i',        'p.insured_id',       '=', 'i.id')
                ->join('product_product as prd',  'p.product_id',       '=', 'prd.id')
                ->join('product_template as prt', 'prt.id',             '=', 'prd.product_tmpl_id')
                ->select(
                    'p.id as policy_id',
                    'prt.name as product_type',
                    'p.policy_no',
                    'i.name',
                    'p.date_from',
                    'p.date_to',
                    'p.actual_si_lc',
                    'p.actual_gross_premium_lc'
                )
                ->where('p.agency_id', $brokerId)
                ->orderBy('p.date_from', 'desc')
                ->get();
        } catch (\Exception $e) {
            return $this->dbErrorResponse($e, 'brokerPolicies');
        }

        return response()->json([
            'status' => 'success',
            'data'   => $rows,
        ]);
    }

    // ── Elite Policy Risk Update ────────────────────────────────────────────────

    /**
     * POST /api/elite/policy/risk/cert-airworthiness
     * Body: { "policy_no": "...", "cert_airworthiness_no": "..." }
     * Updates epgi_policy_risk.cert_airworthiness_no for the risk row belonging
     * to the given policy. Refuses to write if the policy isn't found, no risk
     * row exists, or more than one risk row matches (ambiguous). Every attempt
     * is written to the eliteupdate log channel for audit purposes.
     */
    public function updatePolicyRiskAirworthiness(Request $request)
    {
        $request->validate([
            'policy_no'             => 'required|string',
            'cert_airworthiness_no' => 'required|string|max:255',
        ]);

        $policyNo = $request->input('policy_no');
        $newValue = $request->input('cert_airworthiness_no');
        $ip       = $request->ip();

        try {
            $result = DB::connection('Elite')->transaction(function () use ($policyNo, $newValue, $ip) {
                $policy = DB::connection('Elite')
                    ->table('epgi_policy')
                    ->select('id')
                    ->where('policy_no', $policyNo)
                    ->first();

                if (!$policy) {
                    Log::channel('eliteupdate')->info('policy_risk_cert_airworthiness_update', [
                        'ip'        => $ip,
                        'policy_no' => $policyNo,
                        'result'    => 'policy_not_found',
                    ]);

                    return ['status' => 'not_found', 'message' => 'No policy found for the provided policy number.'];
                }

                $risks = DB::connection('Elite')
                    ->table('epgi_policy_risk')
                    ->select('id', 'cert_airworthiness_no')
                    ->where('policy_id', $policy->id)
                    ->lockForUpdate()
                    ->get();

                if ($risks->isEmpty()) {
                    Log::channel('eliteupdate')->info('policy_risk_cert_airworthiness_update', [
                        'ip'        => $ip,
                        'policy_no' => $policyNo,
                        'policy_id' => $policy->id,
                        'result'    => 'risk_not_found',
                    ]);

                    return ['status' => 'not_found', 'message' => 'No risk record found for this policy.'];
                }

                if ($risks->count() > 1) {
                    Log::channel('eliteupdate')->info('policy_risk_cert_airworthiness_update', [
                        'ip'        => $ip,
                        'policy_no' => $policyNo,
                        'policy_id' => $policy->id,
                        'risk_ids'  => $risks->pluck('id'),
                        'result'    => 'ambiguous',
                    ]);

                    return ['status' => 'conflict', 'message' => 'Multiple risk records found for this policy; cannot determine which to update.'];
                }

                $risk = $risks->first();

                DB::connection('Elite')
                    ->table('epgi_policy_risk')
                    ->where('id', $risk->id)
                    ->update(['cert_airworthiness_no' => $newValue]);

                Log::channel('eliteupdate')->info('policy_risk_cert_airworthiness_update', [
                    'ip'        => $ip,
                    'policy_no' => $policyNo,
                    'policy_id' => $policy->id,
                    'risk_id'   => $risk->id,
                    'old_value' => $risk->cert_airworthiness_no,
                    'new_value' => $newValue,
                    'result'    => 'success',
                ]);

                return [
                    'status'  => 'success',
                    'message' => 'Airworthiness certificate number updated.',
                    'data'    => ['policy_no' => $policyNo, 'cert_airworthiness_no' => $newValue],
                ];
            });
        } catch (\Exception $e) {
            Log::channel('eliteupdate')->info('policy_risk_cert_airworthiness_update', [
                'ip'        => $ip,
                'policy_no' => $policyNo,
                'result'    => 'db_error',
                'error'     => $e->getMessage(),
            ]);

            return $this->dbErrorResponse($e, 'updatePolicyRiskAirworthiness');
        }

        $httpStatus = match ($result['status']) {
            'conflict' => 409,
            default    => 200,
        };

        return response()->json(array_merge($result, ['data' => $result['data'] ?? []]), $httpStatus);
    }

    // ── Add other blocked API calls below as needed ───────────────────────────
    // Each method follows the same pattern:
    //   1. Call $this->httpClient()->...
    //   2. Catch ConnectionException and return 502
    //   3. Return raw response body + status so the caller gets the original payload
}
