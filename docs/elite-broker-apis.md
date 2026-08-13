# Elite Broker & Policy APIs

Endpoints **1** and **2** require authentication via the `proxy.auth` middleware.  
Include the proxy shared secret in every request header:

```
X-Proxy-Secret: <your-proxy-secret>
```

Endpoint **3** (Policy Customer Lookup) is **unauthenticated** — no header needed.

---

## 1. List Brokers & Agents

Returns all partner records in Elite whose `cust_type` is `broker` or `agent`.

### Request

```
GET /api/elite/brokers
```

No query parameters.

### Success Response `200`

```json
{
  "status": "success",
  "data": [
    {
      "broker_id": 14,
      "name": "Acme Insurance Brokers Ltd",
      "website": "https://acme.example.com",
      "email": "info@acme.example.com",
      "phone": "08012345678",
      "cust_type": "broker"
    },
    {
      "broker_id": 27,
      "name": "FastCover Agents",
      "website": null,
      "email": "hello@fastcover.example.com",
      "phone": "07099887766",
      "cust_type": "agent"
    }
  ]
}
```

### Error Response `502`

Returned when the Elite DB is unreachable.

```json
{
  "status": "error",
  "message": "Elite DB query failed: <detail>",
  "data": []
}
```

---

## 2. Policies by Broker

Returns all policies placed through a specific broker or agent, identified by their `broker_id` from the list above.

### Request

```
GET /api/elite/broker/policies?broker_id=14
```

| Parameter   | Type    | Required | Description                              |
|-------------|---------|----------|------------------------------------------|
| `broker_id` | integer | Yes      | The `id` of the broker from Elite (`res_partner.id`) |

### Success Response `200`

```json
{
  "status": "success",
  "data": [
    {
      "policy_id": 1042,
      "product_type": "Motor Comprehensive",
      "policy_no": "SLM/MTR/2025/00123",
      "name": "John Doe",
      "date_from": "2025-01-15",
      "date_to": "2026-01-14",
      "actual_si_lc": 3500000.00,
      "actual_gross_premium_lc": 87500.00
    },
    {
      "policy_id": 1087,
      "product_type": "Fire & Special Perils",
      "policy_no": "SLM/FSP/2025/00045",
      "name": "Jane Smith",
      "date_from": "2025-03-01",
      "date_to": "2026-02-28",
      "actual_si_lc": 12000000.00,
      "actual_gross_premium_lc": 120000.00
    }
  ]
}
```

Results are ordered by `date_from` descending (most recent policies first).

| Field                     | Description                              |
|---------------------------|------------------------------------------|
| `policy_id`               | Internal Elite policy ID                 |
| `product_type`            | Product line name (from `product_template`) |
| `policy_no`               | Human-readable policy number             |
| `name`                    | Insured's name                           |
| `date_from`               | Policy start date                        |
| `date_to`                 | Policy end date                          |
| `actual_si_lc`            | Sum insured in local currency            |
| `actual_gross_premium_lc` | Gross premium in local currency          |

### Validation Error `422`

Returned when `broker_id` is missing or not an integer.

```json
{
  "message": "The broker id field is required.",
  "errors": {
    "broker_id": ["The broker id field is required."]
  }
}
```

### Error Response `502`

```json
{
  "status": "error",
  "message": "Elite DB query failed: <detail>",
  "data": []
}
```

---

## 3. Policy Customer Lookup

Lets a customer verify their own policy using their policy number plus either their phone or email. **No authentication header required.**

### Request

```
GET /api/v1/policy/customer-lookup?policy_no=SLM/MTR/2025/00123&phone=08012345678
```

or with email instead:

```
GET /api/v1/policy/customer-lookup?policy_no=SLM/MTR/2025/00123&email=john.doe@example.com
```

| Parameter   | Type   | Required             | Description                              |
|-------------|--------|----------------------|------------------------------------------|
| `policy_no` | string | Yes                  | The policy number to look up             |
| `phone`     | string | Yes (if no `email`)  | Insured's phone number stored in Elite   |
| `email`     | string | Yes (if no `phone`)  | Insured's email address stored in Elite  |

At least one of `phone` or `email` must be supplied. Both may be sent; either matching is sufficient.

### Success Response `200` — Policy found and identity verified

```json
{
  "found": true,
  "data": {
    "policy_no": "SLM/MTR/2025/00123",
    "policy_type": "Motor Comprehensive",
    "start_date": "2025-01-15",
    "end_date": "2026-01-14"
  }
}
```

### Not Found / Mismatch Response `200`

Returned when the policy does not exist, is not in `approved` state, or the phone/email does not match the insured record.

```json
{
  "found": false
}
```

### Validation Error `422`

Returned when `policy_no` is missing or neither `phone` nor `email` is provided.

```json
{
  "message": "The phone field is required when email is not present.",
  "errors": {
    "phone": ["The phone field is required when email is not present."],
    "email": ["The email field is required when phone is not present."]
  }
}
```

### Error Response `502`

Returned when the Elite DB is unreachable.

```json
{
  "found": false
}
```

---

## Typical Usage Flow

### Scenario A — Administrator onboards a broker

1. Call **List Brokers** (`GET /api/elite/brokers`) to retrieve all brokers and their `broker_id`.
2. Pass the `broker_id` to **Policies by Broker** (`GET /api/elite/broker/policies?broker_id=14`) to load their full policy portfolio.
3. Create the broker's system account and associate the returned policies with it.

### Scenario B — Customer self-service policy check

1. Customer enters their policy number and either their phone or email in the portal.
2. Call **Policy Customer Lookup** (`GET /api/v1/policy/customer-lookup?policy_no=...&phone=...`).
3. If `found: true`, display the policy details to the customer.
4. If `found: false`, prompt the customer to check their details or contact support.
