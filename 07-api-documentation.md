# API Documentation
## Nondon — Multi-Branch Women's Dress Retail & Wholesale ERP

**Project:** Nondon
**Version:** 1.0
**Date:** 24 November 2026
**Status:** Design document — pending approval

---

## 1. API Overview

### 1.1 Architecture

The primary user interface is Livewire + Blade (server-rendered). The REST API exists for:
- Offline POS sync (`/api/sync/*`).
- Future mobile app or third-party integration.
- Courier/payment gateway webhooks (future).

### 1.2 Base URL

```
https://{domain}/api/v1
```

### 1.3 Authentication

- **Sanctum token authentication** for API clients.
- Token passed in `Authorization: Bearer {token}` header.
- Tokens are scoped to a user and their permissions.
- All API endpoints enforce the same authorization policies as the Livewire interface.

### 1.4 Response format

All responses are JSON with consistent structure:

**Success:**
```json
{
    "success": true,
    "data": { ... },
    "meta": {
        "pagination": { "page": 1, "per_page": 25, "total": 100 }
    }
}
```

**Error:**
```json
{
    "success": false,
    "error": {
        "code": "garment_not_available",
        "message": "The garment is no longer available for sale.",
        "details": { "garment_id": 123, "current_status": "sold" }
    }
}
```

### 1.5 HTTP status codes

| Code | Meaning |
|---|---|
| 200 | Success |
| 201 | Created |
| 400 | Bad request (validation error) |
| 401 | Unauthorized (not authenticated) |
| 403 | Forbidden (not authorized) |
| 404 | Not found |
| 409 | Conflict (e.g., garment already sold) |
| 422 | Unprocessable entity (business rule violation) |
| 500 | Internal server error |

### 1.6 Rate limiting

- 60 requests per minute per token (default Laravel throttle).
- Offline sync endpoints: 600 requests per minute (burst during sync).

---

## 2. Authentication Endpoints

### POST /api/v1/auth/login
Authenticate user and receive token.

**Request:**
```json
{
    "username": "string",
    "password": "string"
}
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "token": "string",
        "user": {
            "id": 1,
            "username": "string",
            "full_name": "string",
            "assigned_branch_id": 1,
            "roles": ["salesperson", "branch_supervisor"]
        }
    }
}
```

**Response (422):**
```json
{
    "success": false,
    "error": {
        "code": "invalid_credentials",
        "message": "Invalid username or password."
    }
}
```

**Response (423):**
```json
{
    "success": false,
    "error": {
        "code": "account_locked",
        "message": "Account is locked due to too many failed attempts. Contact administrator."
    }
}
```

### POST /api/v1/auth/logout
Revoke current token.

### POST /api/v1/auth/role-switch
Switch active role (for multi-role users).

**Request:**
```json
{
    "role_id": 2
}
```

### GET /api/v1/auth/me
Get current user profile, active role, and branch.

---

## 3. Offline Sync Endpoints

### POST /api/v1/sync/push
Push offline sales to the server.

**Request:**
```json
{
    "sales": [
        {
            "offline_uuid": "uuid-string",
            "branch_id": 1,
            "salesperson_id": 5,
            "customer_phone": "01712345678",
            "customer_name": "Optional Name",
            "sale_type": "retail",
            "items": [
                {
                    "garment_id": 123,
                    "standard_price": 1500.00,
                    "applied_discount": 100.00,
                    "final_price": 1400.00
                }
            ],
            "payments": [
                {
                    "method": "cash",
                    "amount": 1400.00,
                    "reference": null
                }
            ],
            "completed_at_local": "2026-11-24T14:30:00+06:00"
        }
    ]
}
```

**Response (200):**
```json
{
    "success": true,
    "data": {
        "results": [
            {
                "offline_uuid": "uuid-string",
                "status": "committed",
                "invoice_id": 456,
                "invoice_number": "INV-2026-000456"
            },
            {
                "offline_uuid": "uuid-string-2",
                "status": "conflict",
                "conflicts": [
                    {
                        "garment_id": 124,
                        "reason": "already_sold",
                        "conflicting_invoice_id": 455
                    }
                ]
            },
            {
                "offline_uuid": "uuid-string-3",
                "status": "already_synced"
            }
        ]
    }
}
```

### GET /api/v1/sync/pull
Pull updated data for the offline cache.

**Query parameters:**
- `branch_id` (required) — the device's branch.
- `last_sync_at` (optional) — ISO timestamp of last successful sync (for delta sync).

**Response (200):**
```json
{
    "success": true,
    "data": {
        "products": [...],
        "prices": [...],
        "stock_snapshot": [
            {
                "garment_id": 123,
                "barcode": "NND-abc-25-00001",
                "product_id": 1,
                "product_size_id": 2,
                "current_status": "available"
            }
        ],
        "customers": [
            {
                "id": 1,
                "phone": "01712345678",
                "name": "Customer Name"
            }
        ],
        "sync_timestamp": "2026-11-24T15:00:00+06:00"
    }
}
```

### GET /api/v1/sync/status
Check sync status and pending conflicts.

---

## 4. Catalog Endpoints

### GET /api/v1/products
List products (paginated).

**Query parameters:**
- `page`, `per_page`
- `search` — search by name or code
- `active_only` — boolean

### GET /api/v1/products/{id}
Get product detail with sizes and current prices.

### POST /api/v1/products
Create product (admin only).

### PUT /api/v1/products/{id}
Update product (admin only).

### GET /api/v1/products/{id}/stock
Get stock availability for a product across all branches.

**Response:**
```json
{
    "success": true,
    "data": {
        "product_id": 1,
        "branches": [
            {
                "branch_id": 1,
                "branch_name": "Main Branch",
                "available": [
                    { "size": "32", "count": 5 },
                    { "size": "34", "count": 3 }
                ]
            }
        ]
    }
}
```

---

## 5. Inventory Endpoints

### GET /api/v1/garments/lookup
Lookup garment by barcode, serial, or ID.

**Query parameters:**
- `barcode` — barcode string
- `serial` — fiscal-year serial (e.g., `25-00001`)
- `garment_id` — numeric ID

**Response:**
```json
{
    "success": true,
    "data": {
        "id": 123,
        "barcode": "NND-abc-25-00001",
        "fiscal_year_serial": "25-00001",
        "product": { "id": 1, "name": "Summer Dress" },
        "size": { "id": 2, "label": "34" },
        "current_status": "available",
        "current_branch": { "id": 1, "name": "Main Branch" }
    }
}
```

### GET /api/v1/garments/{id}/history
Get garment movement history (full lifecycle).

**Response:**
```json
{
    "success": true,
    "data": {
        "garment_id": 123,
        "movements": [
            {
                "id": 1,
                "type": "goods_receipt",
                "from_branch": null,
                "to_branch": "Main Branch",
                "performed_by": "Admin User",
                "performed_at": "2026-07-15T10:00:00+06:00",
                "reference": "Purchase PO-001"
            },
            {
                "id": 2,
                "type": "transfer_dispatch",
                "from_branch": "Main Branch",
                "to_branch": "B1",
                "performed_at": "2026-08-01T14:00:00+06:00"
            }
        ]
    }
}
```

### GET /api/v1/inventory/stock
Get stock availability across branches.

**Query parameters:**
- `branch_id` — filter to specific branch
- `product_id` — filter to specific product
- `status` — filter by status (default: available)

---

## 6. POS Sales Endpoints

### POST /api/v1/pos/sales
Create and complete a POS sale (atomic).

**Request:**
```json
{
    "customer_phone": "01712345678",
    "customer_name": "Optional",
    "sale_type": "retail",
    "items": [
        {
            "garment_id": 123,
            "discount": 100.00
        }
    ],
    "payments": [
        { "method": "cash", "amount": 1400.00 },
        { "method": "bkash", "amount": 200.00, "reference": "TXN123" }
    ]
}
```

**Response (201):**
```json
{
    "success": true,
    "data": {
        "invoice_id": 456,
        "invoice_number": "INV-2026-000456",
        "total_amount": 1600.00,
        "total_paid": 1600.00,
        "completed_at": "2026-11-24T15:30:00+06:00"
    }
}
```

**Response (409):**
```json
{
    "success": false,
    "error": {
        "code": "garment_not_available",
        "message": "Garment 123 is no longer available.",
        "details": { "garment_id": 123, "current_status": "sold" }
    }
}
```

**Response (422):**
```json
{
    "success": false,
    "error": {
        "code": "below_minimum_price",
        "message": "Final price cannot be below the minimum price.",
        "details": { "garment_id": 123, "final_price": 800, "minimum_price": 900 }
    }
}
```

### GET /api/v1/pos/sales
List sales (scoped by role and date restrictions).

### GET /api/v1/pos/sales/{id}
Get sale detail.

### POST /api/v1/pos/sales/{id}/print
Generate printable receipt/invoice.

---

## 7. Transfer Endpoints

### GET /api/v1/transfers
List transfer requests.

### POST /api/v1/transfers
Create transfer request.

**Request:**
```json
{
    "from_branch_id": 1,
    "to_branch_id": 2,
    "items": [
        { "garment_id": 123 },
        { "garment_id": 124 }
    ],
    "notes": "Customer request"
}
```

### POST /api/v1/transfers/{id}/approve
Approve transfer request (source branch supervisor).

### POST /api/v1/transfers/{id}/reject
Reject transfer request.

### POST /api/v1/transfers/{id}/dispatch
Dispatch transfer (mark garments as in-transit).

### POST /api/v1/transfers/{id}/receive
Receive transfer at destination.

**Request:**
```json
{
    "received_items": [
        { "garment_id": 123, "received": true },
        { "garment_id": 124, "received": false, "reason": "not in package" }
    ]
}
```

### GET /api/v1/transfers/{id}/discrepancies
Get transfer discrepancies.

---

## 8. Online Order Endpoints

### GET /api/v1/online-orders
List online orders.

### POST /api/v1/online-orders
Create online order.

**Request:**
```json
{
    "customer_phone": "01712345678",
    "customer_name": "Customer",
    "delivery_address": "House 1, Road 2, Dhaka",
    "items": [
        {
            "product_id": 1,
            "product_size_id": 2,
            "source_branch_id": 1
        }
    ],
    "payment_method": "cod"
}
```

### POST /api/v1/online-orders/{id}/confirm
Confirm order (triggers transfer requests).

### POST /api/v1/online-orders/{id}/cancel
Cancel order (before dispatch).

### POST /api/v1/online-orders/{id}/dispatch
Dispatch to customer (courier details).

**Request:**
```json
{
    "courier_name": "Steadfast",
    "courier_tracking_number": "SF123456"
}
```

### POST /api/v1/online-orders/{id}/deliver
Mark as delivered.

### POST /api/v1/online-orders/{id}/reservation-release
Manually release a reservation.

---

## 9. Returns Endpoints

### POST /api/v1/returns/physical
Initiate physical return.

**Request:**
```json
{
    "original_invoice_id": 456,
    "items": [
        { "garment_id": 123, "reason": "size issue" }
    ],
    "refund_method": "cash",
    "disposition": "restock"
}
```

### POST /api/v1/returns/online
Initiate online return.

**Request:**
```json
{
    "original_order_id": 78,
    "items": [
        { "garment_id": 123, "reason": "defective" }
    ],
    "refund_path": "immediate",
    "refund_method": "bkash",
    "refund_reference": "TXN789"
}
```

### POST /api/v1/returns/{id}/approve
Approve return (within limit).

### POST /api/v1/returns/{id}/approve-above-limit
Escalate to admin for above-limit approval.

---

## 10. Cash Endpoints

### GET /api/v1/cash/accounts
List cash accounts (scoped by role).

### GET /api/v1/cash/accounts/{id}/balance
Get current cash balance.

### GET /api/v1/cash/accounts/{id}/transactions
List cash transactions (paginated).

### POST /api/v1/cash/handovers
Create cash handover.

**Request:**
```json
{
    "to_user_id": 2,
    "amount": 5000.00
}
```

### POST /api/v1/cash/handovers/{id}/confirm
Confirm cash handover (supervisor).

**Request:**
```json
{
    "received_amount": 5000.00
}
```

### POST /api/v1/cash/closings
Submit daily cash closing.

**Request:**
```json
{
    "closing_date": "2026-11-24",
    "counted_cash": 15500.00
}
```

### POST /api/v1/cash/closings/{id}/resolve
Resolve cash discrepancy (admin).

---

## 11. Courier Reconciliation Endpoints

### POST /api/v1/courier/settlements
Create courier settlement.

**Request:**
```json
{
    "settlement_date": "2026-11-24",
    "total_cod_collected": 25000.00,
    "total_courier_charges": 1500.00,
    "total_cod_fees": 250.00,
    "total_deductions": 0,
    "net_amount_received": 23250.00,
    "lines": [
        {
            "online_order_id": 78,
            "courier_tracking_ref": "SF123456",
            "cod_collected": 1500.00,
            "courier_charge": 60.00,
            "cod_fee": 15.00,
            "net_amount": 1425.00
        }
    ]
}
```

### GET /api/v1/courier/settlements
List settlements.

### GET /api/v1/courier/settlements/{id}
Get settlement detail with line matching status.

---

## 12. Stock Count Endpoints

### POST /api/v1/stock-counts
Initiate stock count.

### POST /api/v1/stock-counts/{id}/freeze
Apply freeze.

### POST /api/v1/stock-counts/{id}/lines
Record counted garment.

### POST /api/v1/stock-counts/{id}/submit
Submit count with discrepancies.

### POST /api/v1/stock-counts/{id}/adjustments
Approve/reject adjustment (admin).

### POST /api/v1/stock-counts/{id}/release
Release freeze.

---

## 13. Customer Endpoints

### GET /api/v1/customers
List customers (paginated).

### GET /api/v1/customers/search
Search customer by phone or name.

### POST /api/v1/customers
Create customer.

### PUT /api/v1/customers/{id}
Update customer (admin).

### POST /api/v1/customers/merge
Merge duplicate customers (admin).

**Request:**
```json
{
    "primary_customer_id": 1,
    "duplicate_customer_id": 2
}
```

### GET /api/v1/customers/{id}/history
Get customer purchase and return history.

---

## 14. Reporting Endpoints

### GET /api/v1/reports/daily-cash
Daily cash report (scoped by role and date restrictions).

### GET /api/v1/reports/daily-sales
Daily sales report.

### GET /api/v1/reports/stock-summary
Stock summary by branch.

### GET /api/v1/reports/salesperson-performance
Salesperson performance (today only for non-admin).

### GET /api/v1/reports/monthly-ledger
Monthly customer ledger (admin).

### GET /api/v1/reports/transfer-history
Transfer history report.

### GET /api/v1/reports/audit-log
Audit log report (scoped by role and date).

### POST /api/v1/reports/export
Export report as PDF or CSV (queued).

---

## 15. Admin Endpoints

### GET /api/v1/admin/users
List users (admin).

### POST /api/v1/admin/users
Create user (admin).

### PUT /api/v1/admin/users/{id}
Update user (admin).

### POST /api/v1/admin/users/{id}/unlock
Unlock user account (admin).

### POST /api/v1/admin/users/{id}/reset-password
Reset user password (admin).

### GET /api/v1/admin/suppliers
List suppliers (admin).

### POST /api/v1/admin/suppliers
Create supplier (admin).

### POST /api/v1/admin/purchases
Create purchase (admin).

### POST /api/v1/admin/purchases/{id}/receive
Receive goods (generates garment identities).

### POST /api/v1/admin/supplier-payments
Record supplier payment (admin).

### POST /api/v1/admin/expenses/{id}/approve
Approve expense (admin).

### POST /api/v1/admin/prices
Update product price (admin).

### GET /api/v1/admin/fiscal-years
List fiscal years (admin).

### POST /api/v1/admin/fiscal-years
Create fiscal year (admin).

### GET /api/v1/admin/settings
List system settings (admin).

### PUT /api/v1/admin/settings/{key}
Update system setting (admin).

---

## 16. Webhook Endpoints (Future)

### POST /api/v1/webhooks/courier/{provider}
Courier delivery status webhook (future integration).

### POST /api/v1/webhooks/payment/{provider}
Payment gateway webhook (future integration).

---

## 17. API Versioning

- URL-based versioning: `/api/v1/`, `/api/v2/`.
- Backward compatibility maintained for one major version.
- Deprecation notice in response headers before removal.
- Minor additions (new endpoints, optional fields) do not require version bump.
- Breaking changes require new major version.

---

## 18. API Security

- All endpoints require authentication (Sanctum token).
- All endpoints enforce authorization policies (same as Livewire).
- Branch scoping applied automatically.
- Sensitive data (purchase cost) stripped from responses for non-admin tokens.
- Rate limiting: 60 req/min default, 600 req/min for sync.
- Input validation on all endpoints (Form Requests).
- SQL injection prevention (Eloquent ORM, parameterized queries).
- No CORS for external origins (same-origin only, unless explicitly configured for a mobile app).

---

*End of API Documentation*
