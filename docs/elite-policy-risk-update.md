# Elite Policy Risk Update API

Requires authentication via the `proxy.auth` middleware. Include the proxy API key in every request header:

```
X-Proxy-Secret: <your-api-key>
```

---

## Update Airworthiness Certificate Number

Updates `epgi_policy_risk.cert_airworthiness_no` for the risk row belonging to a policy. The policy is looked up by `policy_no` on `epgi_policy`; the matching row on `epgi_policy_risk` is found via `policy_id`.

This is a **write** endpoint. It will not create or guess records — if the policy isn't found, no risk row exists, or more than one risk row matches the policy, nothing is written and an error is returned. Every attempt (successful or not) is recorded in `storage/logs/eliteupdate.log`, reviewable at `/elite/updates`.

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
| `cert_airworthiness_no`   | string | Yes      | The new airworthiness certificate number    |

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

Returned when the Elite DB is unreachable or the update fails.

```json
{
  "status": "error",
  "message": "Elite DB update failed: <detail>",
  "data": []
}
```

---

## Audit Log Review

```
GET /elite/updates
```

Protected by HTTP Basic Auth (`AUDIT_LOG_USERNAME` / `AUDIT_LOG_PASSWORD` in `.env`, checked by the `auditlog.auth` middleware — not the `X-Proxy-Secret` header used by the JSON API). Shows the most recent 200 entries from `storage/logs/eliteupdate.log`, newest first, including rejected attempts.
