# Elite Policy Risk Update API

Requires authentication via the `proxy.auth` middleware. Include the proxy API key in every request header:

```
X-Proxy-Secret: <your-api-key>
```

---

## Update Airworthiness Certificate Number

Updates `epgi_policy_risk.cert_airworthiness_no` for the risk row belonging to a policy. The policy is looked up by `policy_no` on `epgi_policy`; the matching row on `epgi_policy_risk` is found via `policy_id`, optionally narrowed down further by `item_id`.

This is a **write** endpoint. It will not create or guess records — if the policy isn't found, no risk row exists, or more than one risk row matches, nothing is written and an error is returned. Every attempt (successful or not) is recorded in `storage/logs/eliteupdate.log`, reviewable at `/elite/updates`.

### Request

```
POST /api/elite/policy/risk/cert-airworthiness
Content-Type: application/json
X-Proxy-Secret: <your-api-key>

{
  "policy_no": "SLM/AVN/2025/00123",
  "cert_airworthiness_no": "TC-ABC-2026-00456"
}
```

| Field                    | Type   | Required | Description                                |
|--------------------------|--------|----------|--------------------------------------------|
| `policy_no`               | string | Yes      | The policy number to look up                |
| `item_id`                 | string | No       | Disambiguates which risk row to update when a policy has more than one (e.g. a Motor policy with several vehicles). Compared against `epgi_policy_risk.item_id` after stripping spaces/special characters and case-insensitively. If omitted, the policy must have exactly one risk row. |
| `cert_airworthiness_no`   | string | Yes      | The new certificate/worthiness number       |

### Success Response `200`

```json
{
  "status": "success",
  "message": "Airworthiness certificate number updated.",
  "data": {
    "policy_no": "SLM/AVN/2025/00123",
    "cert_airworthiness_no": "TC-ABC-2026-00456"
  }
}
```

### Not Found Response `200`

Returned when the policy number doesn't exist, or the policy exists but has no `epgi_policy_risk` row. No write occurs.

```json
{
  "status": "not_found",
  "message": "No policy found for the provided policy number.",
  "data": []
}
```

```json
{
  "status": "not_found",
  "message": "No risk record found for this policy.",
  "data": []
}
```

If `item_id` was supplied but didn't match any risk row on the policy, the message is `"No risk record found for this policy/item combination."` instead.

### Conflict Response `409`

Returned when the policy has more than one `epgi_policy_risk` row, so the target row can't be determined automatically. No write occurs.

```json
{
  "status": "conflict",
  "message": "Multiple risk records found for this policy; cannot determine which to update.",
  "data": []
}
```

### Validation Error `422`

Returned when `policy_no` or `cert_airworthiness_no` is missing.

### Error Response `502`

Returned when the Elite DB is unreachable or the update fails. The real exception is logged server-side; the caller only gets a generic message.

```json
{
  "status": "error",
  "message": "Elite DB query failed.",
  "data": []
}
```

---

## Batch Update

Processes a batch of records in one call — intended for a one-time backfill (e.g. catching up existing Motor policies with a `cert_airworthiness_no` already generated elsewhere), rather than routine per-record use. Same matching/write rules as the single-record endpoint above, applied independently to each record: one bad record doesn't affect the others.

### Request

```
POST /api/elite/policy/risk/cert-airworthiness/batch
Content-Type: application/json
X-Proxy-Secret: <your-api-key>

{
  "records": [
    { "policy_no": "SLM/MTR/2025/00123", "item_id": "AB-123-XY", "cert_airworthiness_no": "RW-2026-00456" },
    { "policy_no": "SLM/MTR/2025/00789", "item_id": "CD 456 ZZ", "cert_airworthiness_no": "RW-2026-00457" }
  ]
}
```

| Field     | Type  | Required | Description                                    |
|-----------|-------|----------|-------------------------------------------------|
| `records` | array | Yes      | 1 to 500 records, each shaped like the single-record request body above (`item_id` optional per record) |

### Response `200`

Always `200` — the call succeeded even if some records failed; check `summary`/`results` for outcomes.

```json
{
  "status": "success",
  "message": "Batch processed.",
  "summary": { "total": 2, "success": 1, "not_found": 1, "conflict": 0, "error": 0 },
  "results": [
    { "policy_no": "SLM/MTR/2025/00123", "item_id": "AB-123-XY", "status": "success", "message": "Airworthiness certificate number updated." },
    { "policy_no": "SLM/MTR/2025/00789", "item_id": "CD 456 ZZ", "status": "not_found", "message": "No risk record found for this policy/item combination." }
  ]
}
```

### Validation Error `422`

Returned when `records` is missing, empty, or has more than 500 entries — split larger backfills into multiple calls.

---

## Audit Log Review

```
GET /elite/updates
```

Protected by HTTP Basic Auth (`AUDIT_LOG_USERNAME` / `AUDIT_LOG_PASSWORD` in `.env`, checked by the `auditlog.auth` middleware — not the `X-Proxy-Secret` header used by the JSON API). Shows the most recent 200 entries from `storage/logs/eliteupdate.log`, newest first, including rejected attempts.
