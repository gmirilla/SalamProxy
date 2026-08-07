# Elite Broker APIs

Both endpoints require authentication via the `proxy.auth` middleware.  
Include the proxy shared secret in every request header:

```
X-Proxy-Secret: <your-proxy-secret>
```

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

## Typical Usage Flow
Scenario A. Administrator grants broker acess to to system
  1. Call **List Brokers** to get the `broker_id` for the broker you want.

  2. Pass that `broker_id` to **Policies by Broker** to retrieve their portfolio.

  3. Create Broker Account with list of all  policies
