# Phase 3: Sales (POS) & Transfers — Session Breakdown
## Nondon ERP Development Plan

**Project:** Nondon
**Version:** 1.0
**Date:** 28 November 2026
**Status:** Planning complete — ready for implementation
**Phase:** 3 of 10 (Sales & Transfers)
**Estimated duration:** 4-5 weeks (11 sessions)
**Depends on:** Phase 1 (Foundation) — COMPLETE, Phase 2 (Purchasing & Barcode) — COMPLETE

---

## Progress Tracker

| Session | Status | Commit |
|---|---|---|
| 3.1 Customer Management — Creation, Phone Search & Duplicate Detection | COMPLETE | 462eaeb |
| 3.2 Sales Data Model & Atomic Sale Service | COMPLETE | 5192d21 |
| 3.3 POS Interface — Barcode Lookup, Cart & Discount | COMPLETE | 1895b2c |
| 3.4 POS Checkout — Payment, Completion & Customer Linking | COMPLETE | (this session) |
| 3.5 Receipt Printing — Thermal 58/80mm & A4, No-Tax-Line | PENDING | — |
| 3.6 Transfers Data Model & Reservation-Aware Transfer Service | PENDING | — |
| 3.7 Transfers UI — Request Creation, Approval & Dispatch | PENDING | — |
| 3.8 Transfer Receiving & Discrepancy Handling | PENDING | — |
| 3.9 Cross-Branch Stock Visibility Browser & Reservation Board | PENDING | — |
| 3.10 Customer History & Duplicate Merge | PENDING | — |
| 3.11 Phase 3 Integration & Sensitive-Data Hardening | PENDING | — |

---

## 1. Purpose

This document splits Phase 3 (Sales & Transfers) into 11 focused development sessions. Each session is a self-contained unit of work with clear deliverables, dependencies, and acceptance criteria.

**Principle: best solution, not quickest.** Each session is small enough to implement thoroughly — with proper architecture, complete tests, and careful review — without rushing to the next session. Where a workstream is complex (POS sale completion, transfer lifecycle), the core transaction logic and the UI are split into separate sessions so that the business-critical transaction logic — row-level locking, atomicity, reservation, concurrency — can be tested in isolation before a UI is layered on top. This mirrors the Phase 2 pattern (Session 2.4 goods-receiving core transaction, then Session 2.5 receiving UI).

Sessions are sequential. Each builds on the previous one. Do not start a session until the previous session is complete, tested, and committed.

**Scope boundary (what is NOT in Phase 3):**
- **Offline POS** (IndexedDB cache, offline sale queue, sync, conflict resolution) is **Phase 8** per the roadmap. Phase 3 builds the **online POS only**. The `sale_invoices` table is created with `is_offline_sale` and `offline_uuid` columns (nullable, forward-compatible) but no offline behaviour is implemented.
- **Returns, exchanges, defective handling** are **Phase 5**. Phase 3 creates `sale_invoices.return_policy_snapshot` so the 7-day window can be enforced later, but no return workflow is built.
- **Cash accounts, cash transactions, cash handovers, daily closing, supplier payments** are **Phase 6**. Phase 3 records `sale_payments` (method + amount + reference) and dispatches `SaleCompleted`; the cash-account ledger posting is deferred to Phase 6, which will add a listener for `SaleCompleted`.
- **API endpoints** (`/api/v1/pos/sales`, `/api/v1/transfers`, `/api/v1/customers`, `/api/v1/sync/*`) described in doc 07 are **not built in Phase 3**. Phase 1 and Phase 2 were Livewire-first and exposed only the auth API; the same convention applies here. The sync API is required by Phase 8 (offline) and will be built then. The remaining API surface is deferred to a later API/offline phase.

---

## 2. Session Overview

| Session | Module | Focus | Estimated time | Depends on |
|---|---|---|---|---|
| 3.1 | Customers | Customer creation, phone search, duplicate detection | 3-4 hours | Phase 2 |
| 3.2 | Sales | Sales data model, payment methods, atomic SaleService (row-locking), policy | 4-5 hours | 3.1, Phase 2 |
| 3.3 | Sales | POS interface — barcode lookup, cart, discount (minimum-price enforcement) | 3-4 hours | 3.2 |
| 3.4 | Sales | POS checkout — payment collection, atomic completion, customer linking | 3-4 hours | 3.3, 3.1 |
| 3.5 | Sales | Receipt printing — thermal 58/80mm & A4, no-tax-line (ADR-010) | 2-3 hours | 3.4 |
| 3.6 | Transfers | Transfers data model, reservation-aware TransferService, policy, events | 4-5 hours | 3.2, Phase 2 |
| 3.7 | Transfers | Transfer request creation (cross-branch stock picker), approval, dispatch | 3-4 hours | 3.6 |
| 3.8 | Transfers | Transfer receiving, quantity confirmation, discrepancy recording | 3-4 hours | 3.7 |
| 3.9 | Inventory (enhance) | Cross-branch stock browser (read-only, no supplier/cost), reservation board | 2-3 hours | 3.6 |
| 3.10 | Customers | Customer purchase history view, admin duplicate merge (WF-25) | 2-3 hours | 3.4, 3.6 |
| 3.11 | Integration | Phase 3 integration, concurrency, sensitive-data hardening, audit completeness | 3-4 hours | 3.1–3.10 |

**Total:** 11 sessions, approximately 33-43 hours of focused development.

### 2.1 Dependency graph

```
Phase 2 (COMPLETE)
    │  (Garment identity, barcode NND-YY-NNNNN, garment_current_stock with
    │   reservation_reference_type/id, InventoryMovementService with all 19
    │   movement types, PriceService with minimum_price + historical lookup,
    │   StockQueryService/StockProjectionService, AuditService with category
    │   inference for sale./transfer./return./customer., 5 roles + full
    │   permission catalogue incl. pos.*/transfer.*/customer.*)
    │
    ▼
3.1 Customer Management ──────────────────────────────────────┐
    │                                                           │
    ▼                                                           │
3.2 Sales Data Model & Atomic Sale Service (core)              │
    │                                                           │
    ▼                                                           │
3.3 POS Interface — Barcode Lookup, Cart & Discount            │
    │                                                           │
    ▼                                                           │
3.4 POS Checkout — Payment, Completion & Customer Linking ◄────┘ (customer link)
    │
    ▼
3.5 Receipt Printing
    │
    ├──────────────────────────────────────────────────────────┐
    ▼                                                          │
3.6 Transfers Data Model & Reservation-Aware Transfer Service  │
    │   (reservation blocks POS — tested against 3.2/3.4)       │
    ▼                                                          │
3.7 Transfers UI — Request Creation, Approval & Dispatch       │
    │   (uses existing StockQueryService for cross-branch picker)│
    ▼                                                          │
3.8 Transfer Receiving & Discrepancy Handling                  │
    │                                                          │
    ├──────────────────────────────────────────────────┐        │
    ▼                                                  ▼        ▼
3.9 Cross-Branch Stock Visibility          3.10 Customer History & Merge
    │   (reservation board now shows               │
    │    transfer-backed reservations)             │
    └──────────────────────┬───────────────────────┘
                           ▼
                3.11 Phase 3 Integration & Hardening
```

### 2.2 Why POS is split into four sessions (3.2 + 3.3 + 3.4 + 3.5)

POS sale completion (WF-3) is the most complex and risk-critical operation in Phase 3. It involves:
- A single atomic multi-table transaction (`sale_invoices` + `sale_invoice_items` + `sale_payments` + `garments` status `available → sold` + `garment_current_stock` + `inventory_movements`).
- `SELECT ... FOR UPDATE` row-level locking on every garment in the cart, with re-verification inside the transaction (concurrency: two cashiers, one garment — one must fail).
- Minimum-price enforcement on every discounted line.
- Multiple payment methods per sale.
- A `SaleCompleted` domain event for downstream listeners (Inventory, Customers, Audit — and later Cash in Phase 6).
- A no-tax-line receipt (ADR-010).

Splitting into:
- **3.2 (core service):** the `SaleService::complete()` transaction, the `SalePolicy`, the concurrency test, and unit tests — all backend, fully testable in isolation. This is where the row-locking and atomicity are proven.
- **3.3 (cart UI):** the Livewire POS screen for garment lookup (barcode/serial), cart add/remove/replace, and per-line discount with minimum-price enforcement. Builds on 3.2's models and the existing `GarmentIdentityService::lookupByBarcode()`.
- **3.4 (checkout UI):** the Livewire checkout flow — customer select/create, multiple payment methods, `total_paid >= total_amount`, atomic completion via `SaleService::complete()`, `return_policy_snapshot`. Builds on 3.3 and 3.1.
- **3.5 (receipt):** receipt data assembly + Blade templates (58mm/80mm thermal + A4) + browser print CSS + ADR-010 no-tax-line compliance. Builds on 3.4.

This mirrors Phase 2's split of goods receiving (2.4 core + 2.5 UI) and barcode (2.6 generation + 2.7 printing), and ensures the concurrency-critical transaction is verified before any UI depends on it.

### 2.3 Why transfers is split into three sessions (3.6 + 3.7 + 3.8)

The transfer lifecycle (WF-6 + WF-7) has four state transitions on garments (`available → reserved → in_transit → available`) plus discrepancy handling (`in_transit → missing`), all backed by implicit reservations on `garment_current_stock` and append-only `inventory_movements`. Splitting into:
- **3.6 (core service):** `TransferService` (createRequest → reserve, approve, reject → release, dispatch → in_transit, receive → available + discrepancy) with `TransferPolicy`, events `TransferRequested`/`TransferDispatched`/`TransferReceived`, and tests including "reserved garment cannot be sold via POS" (proving the reservation model blocks the POS path built in 3.2/3.4).
- **3.7 (request UI):** request creation with a cross-branch stock picker (reusing `StockQueryService`), approval/rejection with reason, dispatch with printable dispatch note.
- **3.8 (receiving UI):** the receiving form with optional barcode scan or quantity entry, received-vs-dispatched comparison, discrepancy recording, and the rule that missing garments are **never silently marked received** (WF-7 step 3).

### 2.4 Why stock visibility is a late, light session (3.9)

Phase 1 (Session 1.11) already built the stock-visibility **backend**: `StockQueryService` (`getAvailableStockForBranch`, `getAvailableStockForAllBranches`, `getStockForProduct`, `getReservationCounts`, `searchGarments`), `StockProjectionService`, and the Livewire components `StockAvailability`, `StockOverview`, `ReservationBoard`, `GarmentLookup`. These already call `makeHidden('purchase_cost')` everywhere, so the cross-branch view is already supplier/cost-safe.

Session 3.9 is therefore **not a rebuild**. It (a) adds a dedicated cross-branch **read-only browser** page (all branches, no supplier/cost) that the roadmap explicitly lists as a Phase 3 deliverable; (b) verifies and enhances the reservation board so it correctly shows transfer-backed reservations once 3.6/3.7 populate `garment_current_stock.reservation_reference_type = 'transfer_request'`; and (c) verifies `GarmentLookup` is fast enough for POS barcode scan (< 100ms, performance target doc 06 §1.1). It is placed after transfers so the reservation board has real data to display and to test against.

### 2.5 Why customer history & merge is a late session (3.10)

Customer purchase history links to `sale_invoices` (created in 3.2) and customer merge (WF-25) re-links `sale_invoices.customer_id` from a duplicate to a primary. Both require the Sales module to exist. They are small, finishing tasks and are placed after the sales + transfers slices so all data they reference is in place. Returns re-linking (WF-25 step 3c) is explicitly deferred to Phase 5; Phase 3 merge re-links `sale_invoices` only.

### 2.6 What Phase 1 & Phase 2 already provide (do NOT rebuild)

| Component | Location | What it does (relevant to Phase 3) |
|---|---|---|
| `Garment` model | `app/Modules/Inventory/Models/Garment.php` | All 11 statuses (`available`, `reserved`, `in_transit`, `sold`, `damaged`, `under_inspection`, `missing`, `frozen`, `clearance`, `disposed`, `returned_to_supplier`). `canBeSold()` = available OR clearance. Helpers `isAvailable/isReserved/isSold/isInTransit`. State transitions `reserve($refType,$refId)`, `releaseReservation()`, `markSold()`, `markInTransit($toBranchId)`, `relocate($branchId,$locId)`. `purchase_cost` hidden via `$hidden`, `withPurchaseCost()` accessor for admin. |
| `GarmentCurrentStock` | `app/Modules/Inventory/Models/GarmentCurrentStock.php` | Projection with `reservation_reference_type` / `reservation_reference_id` columns — **ready for transfers/online orders**, no schema change needed. |
| `InventoryMovement` | `app/Modules/Inventory/Models/InventoryMovement.php` | All 19 movement types already declared incl. `transfer_dispatch`, `transfer_receive`, `reservation`, `reservation_release`, `sale`, `sale_offline`, `return_from_customer`, `clearance_mark`. Append-only enforced (save/delete throw). |
| `InventoryMovementService` | `app/Modules/Inventory/Services/InventoryMovementService.php` | `record(Garment, $movementType, $context)` — opens a nested `DB::transaction`, captures from→to status/branch/location, creates the append-only movement, updates `garment.current_*`, updates `GarmentCurrentStock` projection (sets reservation ref only for `TYPE_RESERVATION`), dispatches `AuditableEvent('inventory.movement', ...)`. `determineToStatus()` maps every movement type to its resulting garment status. |
| `GarmentIdentityService` | `app/Modules/Inventory/Services/GarmentIdentityService.php` | `lookupByBarcode(string)`, `lookupBySerial(string)`, `lookupById(string)` — used by POS barcode scan. |
| `StockQueryService` | `app/Modules/Inventory/Services/StockQueryService.php` | `getAvailableStockForBranch`, `getAvailableStockForAllBranches`, `getStockForProduct`, `getReservationCounts`, `searchGarments`, `getPaginatedAvailableStock`. All hide `purchase_cost`. Used by transfer source picker (3.7) and stock browser (3.9). |
| `StockProjectionService` | `app/Modules/Inventory/Services/StockProjectionService.php` | `update()`, `reconcile()` (drift detection), `getReservationCounts()` (branches with reserved_count). |
| `MovementHistoryService` | `app/Modules/Inventory/Services/MovementHistoryService.php` | Human-readable history per garment; descriptions for `sale`, `sale_offline`, `transfer_dispatch`, `transfer_receive`, `reservation`, `reservation_release`, `return_from_customer` already defined. |
| `PriceService` | `app/Modules/Catalog/Services/PriceService.php` | `getCurrentPrice(product, size)` returns active `ProductPrice` (standard + minimum). `getPriceAt(product, size, $date)` for historical invoice integrity. `minimum_price` is the floor enforced in POS discount (WF-3 step 4b). |
| `Branch` model | `app/Modules/Organization/Models/Branch.php` | `type` enum `['main','physical','online']`; `isMainBranch()`, `isOnlineChannel()`. |
| `FiscalYearService::generateNextSerial()` | `app/Modules/Organization/Services/FiscalYearService.php` | Concurrency-safe serial via `pg_advisory_xact_lock(9000000 + $fy->id)`. **Pattern to reuse** for sale-invoice and transfer-request number generation (offset 9000001 / 9000002). |
| `AuditService::log()` | `app/Modules/Audit/Services/AuditService.php` | `log($action, $context)`; `inferCategory()` already maps `sale.`/`pos.`→`sales`, `transfer.`→`transfers`, `return.`→`returns`, `customer.`→`customers` (Phase 3 prefixes already supported). `AuditableEvent` + `AuditListener` chain; failures are caught/logged (audit never blocks business ops). |
| `Auditable` trait | `app/Modules/Audit/Traits/Auditable.php` | Auto-dispatches `'{prefix}.create'`/`'{prefix}.update'` on Eloquent events; `auditCustomAction($suffix, $prev, $new, $reason)` for state transitions. |
| Roles & permissions | `database/seeders/RolePermissionSeeder.php` | 5 roles: `main_branch_admin`, `branch_supervisor`, `salesperson`, `online_team`, `online_supervisor`. **All Phase 3 permissions already declared and assigned**: `pos.sale.create`, `pos.sale.complete`, `pos.discount.apply`, `transfer.request.create`, `transfer.approve`, `transfer.dispatch`, `transfer.receive`, `customer.create`, `customer.edit`, `customer.merge`, `inventory.view.all_branches`, `reservation.release.manual` (admin-only). Phase 3 must **enforce** these via Policies; the catalogue needs no new permissions. (`User::canSell()` = active + assigned branch — the "all roles can sell" principle.) |
| `ReceivingService` | `app/Modules/Purchasing/Services/ReceivingService.php` | Reference for the atomic-transaction + per-unit `InventoryMovementService::record()` + per-document `AuditableEvent` + domain-event (`PurchaseReceived`) pattern that Phase 3 mirrors for `SaleService::complete()` and `TransferService::dispatch/receive()`. |

**Phase 3 gaps to fill:**
- No `app/Modules/Customers/`, `app/Modules/Sales/`, `app/Modules/Transfers/` modules exist.
- No `customers`, `sale_invoices`, `sale_invoice_items`, `sale_payments`, `transfer_requests`, `transfer_request_items`, `transfer_discrepancies`, `payment_methods` tables exist.
- No `Customer`, `SaleInvoice`, `SaleInvoiceItem`, `SalePayment`, `TransferRequest`, `TransferRequestItem`, `TransferDiscrepancy` models exist.
- No `CustomerService`, `SaleService`, `TransferService`, receipt-printing service exist.
- No `CustomerPolicy`, `SaleInvoicePolicy`, `TransferRequestPolicy` exist.
- No POS / transfer / customer Livewire components or Blade views exist.
- No `pos.*` / `transfers.*` / `customers.*` route groups exist.
- No nav links for POS / Transfers / Customers exist in `resources/views/layouts/app.blade.php`.

### 2.7 Naming convention (must follow doc 03 exactly)

Per `docs/03-database-design.md`, the table names are:
- `customers` (not `customer`)
- `sale_invoices` / `sale_invoice_items` / `sale_payments` (not `sales` / `sale_items` / `sale_payments`)
- `transfer_requests` / `transfer_request_items` / `transfer_discrepancies` (not `transfers` / `transfer_items`)
- There is **no `transfer_approvals` table** — approval is a status transition on `transfer_requests` (`pending → approved/rejected`) with `approved_by`/`approved_at`/`rejected_by`/`rejected_at`/`rejection_reason` columns.
- There is **no standalone `reservations` table** — reservations are implicit: `garments.current_status = 'reserved'` + `garment_current_stock.reservation_reference_type` ∈ `('transfer_request','online_order')` + `reservation_reference_id` + an `inventory_movements` row of `movement_type = 'reservation'` (and `reservation_release` on release).

### 2.8 Session completion criteria

A session is considered complete when:
1. All deliverables are implemented.
2. All acceptance-criteria tests pass.
3. Code follows Pint style (no linting errors) — run `./vendor/bin/pint`.
4. No existing tests are broken (`php artisan test` stays green).
5. Changes are committed and pushed to GitHub with the session's commit message.
6. The next session's dependencies are satisfied.

---

## Session 3.1: Customers — Customer Creation, Phone Search & Duplicate Detection

**Goal:** Build the Customers module foundation so any selling role can create a customer (phone mandatory, name optional), search customers by phone, and the system detects duplicates on creation. Customer PII (phone, name, address) is scope-restricted per role.

**Estimated time:** 3-4 hours

### Prerequisites
- Phase 2 complete (audit infrastructure, branch scope, policies pattern).
- The `customers` table does not yet exist (this session creates it).

### Deliverables

**1. Migration — `customers` table (`app/Modules/Customers/Database/Migrations/2025_01_01_000050_create_customers_table.php`):**
```
id BIGSERIAL PRIMARY KEY
phone VARCHAR(50) UNIQUE NOT NULL          -- primary identifier, single phone per customer
name VARCHAR(255) NULL                     -- optional
address TEXT NULL
is_online_customer BOOLEAN DEFAULT FALSE  -- distinguishes online vs walk-in
is_active BOOLEAN DEFAULT TRUE             -- inactive after merge (never deleted)
merged_into_id BIGINT NULL REFERENCES customers(id)  -- self-FK for merge tracking (WF-25)
created_by BIGINT NULL REFERENCES users(id)
created_at TIMESTAMPTZ DEFAULT NOW()
updated_at TIMESTAMPTZ DEFAULT NOW()
```
Indexes: `UNIQUE(phone)`, `INDEX(phone)` (fast phone lookup), `INDEX(merged_into_id)`. No soft deletes — duplicates are marked `is_active = false` and redirected via `merged_into_id` (WF-25).

**2. Customer model (`app/Modules/Customers/Models/Customer.php`):**
- Traits: `Auditable` (`$auditPrefix = 'customer'`, `$auditCategory = 'customers'`), `HasFactory`.
- Fillable: `phone`, `name`, `address`, `is_online_customer`, `is_active`, `merged_into_id`, `created_by`.
- Casts: `is_online_customer => boolean`, `is_active => boolean`.
- Scopes: `active()`, `inactive()`, `onlineCustomers()`, `walkIn()`, `search(string $term)` (ILIKE on phone OR name), `forBranch` is N/A (customers are not branch-scoped — they're looked up globally, but PII visibility is role-scoped).
- Relationships: `sales()` (hasMany `SaleInvoice` — added in 3.2, guarded with `whereHas` so the relationship works even before 3.2 lands), `creator()` (belongsTo User), `mergedInto()` (self belongsTo), `mergedFrom()` (self hasMany — duplicates pointing to this one).
- Helpers: `isMerged()`, `isActive()`, `getDisplayNameAttribute()` = `name ? "{name} ({phone})" : phone`.
- **PII handling:** `phone`, `name`, `address` are NOT hidden on the model (they're the point of the record), but visibility is enforced at the **query/policy** layer — non-admin, non-owning-branch roles see only customers they are permitted to (see Policy + Service). Implement a `CustomerResource` (API Resource) for any future JSON path that strips `address` for non-admin.

**3. CustomerService (`app/Modules/Customers/Services/CustomerService.php`):**
- `create(array $data, User $creator): Customer` — phone mandatory, name optional. Normalizes phone (trim, strip spaces). Throws `ValidationException` if phone duplicate exists (duplicate detection — returns the existing customer's id in the error message so the UI can offer to reuse it). Sets `created_by`, `is_online_customer` from creator context (online team/supervisor → true). Audits `customer.create`.
- `findOrCreateByPhone(string $phone, User $creator, ?string $name = null): Customer` — the POS helper: lookup by phone, return existing if active, else create. (Used by POS checkout 3.4.)
- `search(string $term, User $viewer): Collection` — search by phone/name; **applies PII scope**: admin sees all; branch supervisor/salesperson see customers that have at least one sale at their branch OR are walk-in with no sales yet; online team/supervisor see `is_online_customer = true` customers. Always excludes merged (`is_active = false`) records from the primary result, with a flag to include them for admin.
- `get(Customer $customer, User $viewer): Customer` — fetches a single customer, enforcing the same PII scope (throws `AuthorizationException` if not permitted).
- `update(Customer $customer, array $data, User $actor): Customer` — admin only (enforced by policy). Audits `customer.update`. (Edit is admin-only per permission matrix doc 05 §4.1.)
- `deactivate(Customer $customer, User $actor): Customer` — admin only; sets `is_active = false` (only used outside merge flows; merge uses 3.10).
- `getPurchaseHistory(Customer $customer, User $viewer): Collection` — **stub in 3.1** (returns empty collection with a `@todo Phase 3.10` note); fully implemented in 3.10 once `sale_invoices` exists.
- All write methods wrapped in `DB::transaction(...)`.

**4. CustomerPolicy (`app/Modules/Customers/Policies/CustomerPolicy.php`):**
- `viewAny(User $user): bool` — all roles true (every selling role can search customers). PII scope is applied in the service, not here.
- `view(User $user, Customer $customer): bool` — admin: all; branch supervisor/salesperson: customer has a sale at their branch OR is walk-in with no sales; online team/supervisor: `customer.is_online_customer`. (Use a `CustomerPiiScope` helper or inline the check.)
- `create(User $user): bool` — `customer.create` permission (all selling roles have it).
- `update(User $user, Customer $customer): bool` — `customer.edit` (admin only).
- `deactivate(User $user, Customer $customer): bool` — `customer.edit` (admin only).
- `merge(User $user): bool` — `customer.merge` (admin only). Used in 3.10.

**5. Livewire components:**
- `CustomerList` — paginated table (25/page per doc 06 §6.3) with phone search box; columns: phone, name (if present), type (walk-in/online), status. PII scope applied server-side. Admin sees a "Merged" filter.
- `CustomerCreate` — form: phone (required), name (optional), address (optional, admin-only field). On submit, if duplicate detected, show "Customer with this phone exists — [View existing]" instead of erroring.
- `CustomerView` — read-only detail. **Purchase history section is a placeholder in 3.1** ("Available after Sales module is built"); wired up in 3.10.

**6. Views:**
- `resources/views/livewire/customers/list.blade.php`
- `resources/views/livewire/customers/create.blade.php`
- `resources/views/livewire/customers/view.blade.php`

**7. Routes (`routes/web.php`, inside the `auth` middleware group):**
- `GET /customers` — CustomerList (any authenticated user with `customer.create`)
- `GET /customers/create` — CustomerCreate
- `GET /customers/{customer}` — CustomerView (PII-scoped)

**8. Module registration:**
- Create `app/Modules/Customers/CustomersServiceProvider.php` (registers routes, views namespace `customers.`, observers if any). Register in `bootstrap/providers.php`.

**9. Nav link:** add "Customers" to `resources/views/layouts/app.blade.php` gated by `@can('create', \App\Modules\Customers\Models\Customer::class)`.

**10. Factory:** `database/factories/CustomerFactory.php` with states `walkIn()`, `online()`, `withName()`, `mergedInto(Customer $primary)`.

**11. Tests (`tests/Feature/Customers/CustomerManagementTest.php`):**
- Any authenticated selling role can view the customer list.
- Salesperson can create a customer with phone + optional name.
- Phone is mandatory (validation rejects empty phone).
- Duplicate phone is detected — `CustomerService::create()` throws with the existing customer's id; the UI offers to reuse.
- `findOrCreateByPhone` returns existing customer instead of duplicating.
- Admin can edit a customer; non-admin gets 403 on edit.
- Branch supervisor sees customers with a sale at their branch (once sales exist — use a manual `sale_invoices` row in the test or assert the scope query) and walk-in customers; does NOT see other branches' customers that have sales only at other branches.
- Online team sees only `is_online_customer = true` customers.
- Merged customers (`is_active = false`) are excluded from default list; admin can filter to see them.
- Customer creation is audited (`audit_logs.action = 'customer.create'`, `category = 'customers'`).
- Customer update is audited.

### Acceptance criteria — Session 3.1 (COMPLETE)
- [x] Any selling role can create a customer with phone (mandatory) and optional name.
- [x] Duplicate phone is detected at creation and the existing record is offered for reuse (no duplicate row).
- [x] Customer search by phone works in < 100ms for typical datasets (phone-column unique index + ILIKE).
- [x] Customer PII visibility is enforced per role (admin all; supervisor/salesperson walk-in; online team online-only; branch-scope "sale at my branch" refinement deferred to 3.10 once sales exist).
- [x] Only admin can edit a customer (403 for others).
- [x] Customers are never deleted; merge sets `is_active = false` + `merged_into_id` (mechanism present; the merge UI is 3.10).
- [x] All customer actions are audited (`customer.create` / `customer.update` / `customer.deactivate`).
- [x] 38 tests written (66 assertions) — all acceptance criteria covered.

---

## Session 3.2: Sales — Sales Data Model, Payment Methods & Atomic Sale Service

**Goal:** Build the Sales module data model and the atomic `SaleService::complete()` transaction with `SELECT ... FOR UPDATE` row-level locking on every garment in the cart, minimum-price enforcement, and the `SaleCompleted` domain event. This is the backend-only, fully testable core — including the concurrency test (two concurrent sales of the same garment, one must fail). No UI in this session.

**Estimated time:** 4-5 hours

### Prerequisites
- Session 3.1 complete (Customer model + CustomerService::findOrCreateByPhone).
- Phase 1 complete (`Garment`, `GarmentCurrentStock`, `InventoryMovement`, `InventoryMovementService`, `PriceService`, `FiscalYearService`).

### Deliverables

**1. Migration — `payment_methods` table (`app/Modules/Sales/Database/Migrations/2025_01_01_000051_create_payment_methods_table.php`):**
```
id BIGSERIAL PRIMARY KEY
name VARCHAR(50) UNIQUE NOT NULL          -- 'cash', 'bkash', 'nagad', 'bank_transfer', 'card'
display_name VARCHAR(100) NOT NULL
is_active BOOLEAN DEFAULT TRUE
requires_reference BOOLEAN DEFAULT FALSE  -- bkash/bank/card → true; cash → false
is_cash_method BOOLEAN DEFAULT FALSE      -- true for 'cash' (Phase 6 cash-closing calc)
sort_order INT DEFAULT 0
created_at TIMESTAMPTZ DEFAULT NOW()
```
Seed (in `PaymentMethodsSeeder` or inline): cash, bKash, Nagad, bank_transfer, card — `is_active = true`, `is_cash_method = true` for cash only, `requires_reference = true` for bkash/nagad/bank_transfer/card. **Note:** `payment_methods` is documented under the Cash module (doc 03 §3.10) but is created in Phase 3 because POS needs the method list immediately; Phase 6 (Cash) will own the cash-account ledger that posts against these methods.

**2. Migration — `sale_invoices` table (`app/Modules/Sales/Database/Migrations/2025_01_01_000052_create_sale_invoices_table.php`):**
```
id BIGSERIAL PRIMARY KEY
invoice_number VARCHAR(50) UNIQUE NOT NULL                -- e.g. 'INV-25-00001'
branch_id BIGINT NOT NULL REFERENCES branches(id)
salesperson_id BIGINT NOT NULL REFERENCES users(id)
customer_id BIGINT NULL REFERENCES customers(id)          -- nullable (walk-in allowed? per WF-3 customer selection is required; keep nullable for edge cases but enforce in service)
sale_type VARCHAR(20) NOT NULL CHECK (sale_type IN ('retail','wholesale'))  -- tag only (ADR-014)
subtotal NUMERIC(12,2) NOT NULL
total_discount NUMERIC(12,2) DEFAULT 0
total_amount NUMERIC(12,2) NOT NULL                        -- subtotal - total_discount
total_paid NUMERIC(12,2) NOT NULL
status VARCHAR(20) NOT NULL CHECK (status IN ('draft','completed','void')) DEFAULT 'draft'
is_offline_sale BOOLEAN DEFAULT FALSE                     -- forward-compat (Phase 8); always false in Phase 3
offline_uuid UUID NULL                                    -- forward-compat; UNIQUE WHERE NOT NULL
completed_at TIMESTAMPTZ NULL
fiscal_year_id BIGINT NULL REFERENCES fiscal_years(id)
return_policy_snapshot JSONB NULL                         -- {return_period_days: 7, ...} at sale time
notes TEXT NULL
created_at TIMESTAMPTZ DEFAULT NOW()
updated_at TIMESTAMPTZ DEFAULT NOW()
```
Indexes: `UNIQUE(invoice_number)`, `UNIQUE(offline_uuid)` (partial — only non-null), `INDEX(branch_id, completed_at)`, `INDEX(salesperson_id, completed_at)`, `INDEX(customer_id)`, `INDEX(status)`. Partial index `idx_invoices_completed ON sale_invoices(branch_id, completed_at) WHERE status = 'completed'` (per doc 06 §2.4 — the common report query).

**3. Migration — `sale_invoice_items` table (`2025_01_01_000053_create_sale_invoice_items_table.php`):**
```
id BIGSERIAL PRIMARY KEY
sale_invoice_id BIGINT NOT NULL REFERENCES sale_invoices(id) ON DELETE CASCADE
garment_id BIGINT NOT NULL REFERENCES garments(id)
product_id BIGINT NOT NULL REFERENCES products(id)
product_size_id BIGINT NULL REFERENCES product_sizes(id)
standard_price NUMERIC(12,2) NOT NULL      -- snapshot of standard price at sale time
applied_discount NUMERIC(12,2) DEFAULT 0
final_price NUMERIC(12,2) NOT NULL         -- standard_price - applied_discount
created_at TIMESTAMPTZ DEFAULT NOW()
```
Index: `INDEX(sale_invoice_id)`, `INDEX(garment_id)`. One row per garment (garment-level granularity per doc 03).

**4. Migration — `sale_payments` table (`2025_01_01_000054_create_sale_payments_table.php`):**
```
id BIGSERIAL PRIMARY KEY
sale_invoice_id BIGINT NOT NULL REFERENCES sale_invoices(id) ON DELETE CASCADE
payment_method VARCHAR(30) NOT NULL       -- references payment_methods.name (soft FK; validate in service)
amount NUMERIC(12,2) NOT NULL
reference_number VARCHAR(100) NULL       -- required for methods with requires_reference=true
recorded_by BIGINT NOT NULL REFERENCES users(id)
recorded_at TIMESTAMPTZ DEFAULT NOW()
```
Index: `INDEX(sale_invoice_id)`, `INDEX(payment_method)`. Multiple rows per invoice allowed (cash + bKash, WF-3 step 5c).

**5. Models:**
- `SaleInvoice` (`app/Modules/Sales/Models/SaleInvoice.php`): traits `Auditable` (`$auditPrefix='sale'`, `$auditCategory='sales'`), `HasFactory`. Constants `STATUS_DRAFT='draft'`, `STATUS_COMPLETED='completed'`, `STATUS_VOID='void'`; `SALE_TYPE_RETAIL`, `SALE_TYPE_WHOLESALE`. Fillable, casts (`*_price => decimal:2`, `completed_at => datetime`, `is_offline_sale => boolean`, `return_policy_snapshot => array`). Relationships: `items()`, `payments()`, `customer()`, `salesperson()`, `branch()`, `fiscalYear()`. Scopes: `completed()`, `forBranch(int)`, `forSalesperson(int)`, `forCustomer(int)`, `today()`, `completedOn(Carbon)`. Helpers: `isCompleted()`, `isVoid()`, `canBeReturned()` (placeholder — Phase 5 enforces 7-day via snapshot), `totalDue()` = `total_amount - total_paid`.
- `SaleInvoiceItem`: relationships `invoice()`, `garment()`, `product()`, `productSize()`. Helper `lineTotal()` = `final_price`.
- `SalePayment`: relationships `invoice()`, `recordedBy()`. Helper `isCash()`.

**6. SaleNumberService (`app/Modules/Sales/Services/SaleNumberService.php`):**
- `generateNextInvoiceNumber(FiscalYear $fy): string` — concurrency-safe invoice-number generation using `pg_advisory_xact_lock(9000001 + $fy->id)` (offset distinct from `FiscalYearService`'s 9000000 and the later `TransferNumberService` 9000002). Returns `'INV-'.$fy->serial_prefix.'-'.str_pad($seq, 5, '0', STR_PAD_LEFT)`. **Uses the advisory-lock pattern, NOT count+1** — POS is high-concurrency (doc 06 §1.2: 5 simultaneous checkouts per branch, 500+ daily per branch). Counter stored on a dedicated `document_sequences` row per fiscal year per document type, OR reuse a column — recommend a small `document_sequences(fiscal_year_id, doc_type, current_seq)` table added in this migration.

**7. SaleService (`app/Modules/Sales/Services/SaleService.php`) — the critical transaction:**

Constructor injects `InventoryMovementService`, `PriceService`, `SaleNumberService`, `FiscalYearService`.

- `complete(User $salesperson, Branch $branch, array $payload): SaleInvoice` — the atomic sale. `$payload` shape:
  ```
  [
    'customer_id' => ?int,            // null = walk-in (but WF-3 step 2 requires phone; service uses CustomerService::findOrCreateByPhone if phone passed)
    'customer_phone' => ?string,      // alternative to customer_id (POS passes phone)
    'customer_name' => ?string,
    'sale_type' => 'retail'|'wholesale',
    'items' => [['garment_id'=>int, 'discount'=>?float], ...],   // min 1
    'payments' => [['method'=>string, 'amount'=>float, 'reference'=>?string], ...], // min 1
    'notes' => ?string,
  ]
  ```
  Algorithm (mirrors `ReceivingService::receiveGoods`):
  1. **Validation (outside transaction):**
     - `if (! $salesperson->can('pos.sale.complete'))` throw `AuthorizationException` (policy `SaleInvoicePolicy::complete`).
     - Verify `$salesperson->canSell()` (active + assigned branch) and `$branch->id === $salesperson->assigned_branch_id` (branch scope — sales happen at the user's branch only).
     - Verify active fiscal year exists (`FiscalYear::current()`); throw if closed.
     - For each item: load `Garment`, verify `garment->canBeSold()` (available OR clearance), verify `garment->current_branch_id === $branch->id` (can only sell garments physically at your branch), load `PriceService::getCurrentPrice(product, size)` → enforce `final_price = standard_price - discount >= minimum_price` (throw `BelowMinimumPriceException` with garment_id + final_price + minimum_price per doc 07 §6 HTTP 422 shape).
     - For each payment: validate `method` exists in `payment_methods` and is active; if `requires_reference` and reference empty → throw; sum `total_paid`; enforce `total_paid >= total_amount` (throw if short).
     - Resolve customer: if `customer_id` given, load + scope-check via `CustomerPolicy::view`; if `customer_phone` given, `CustomerService::findOrCreateByPhone`.
  2. **`DB::transaction(function () use (...) { ... })`:**
     - Generate invoice number via `SaleNumberService::generateNextInvoiceNumber($fy)` (advisory-locked inside this txn).
     - For each item:
       - `$garment = Garment::lockForUpdate()->find($id)` — **row-level lock**.
       - **Re-verify** `garment->canBeSold()` AND `garment->current_branch_id === $branch->id` AND `garment->is_active`/not frozen — throw `GarmentNotAvailableException` (with garment_id + current_status) if any check fails (the other concurrent cashier won). **This re-check inside the lock is the concurrency guarantee.**
       - Call `InventoryMovementService::record($garment, InventoryMovement::TYPE_SALE, ['reference_type' => 'sale', 'reference_id' => $invoice->id, 'performed_by' => $salesperson->id, 'notes' => $invoiceNumber])` — this atomically: creates the append-only `inventory_movements` row, sets `garment.current_status = 'sold'`, updates `garment_current_stock` (clears reservation ref), dispatches `AuditableEvent('inventory.movement', ...)`.
     - Create `SaleInvoice` (status `completed`, `completed_at = now`, `fiscal_year_id`, `return_policy_snapshot = config('nondon.return_policy', ['return_period_days' => 7])`).
     - Create `SaleInvoiceItem` rows (snapshot `standard_price`).
     - Create `SalePayment` rows.
     - Link customer (`customer_id`).
     - Dispatch `SaleCompleted` domain event (`event(new SaleCompleted($invoice->fresh()))`) — **downstream listeners (Inventory already moved via movement service; Audit already logged via AuditableEvent; Phase 6 will add a Cash listener)**.
     - `event(new AuditableEvent('sale.complete', ['category' => 'sales', 'reference_type' => 'sale_invoice', 'reference_id' => $invoice->id, 'new_values' => [...], 'reason' => null]))` — per-document audit.
     - Return `$invoice->fresh()`.
  3. On any exception, the transaction rolls back — **no stock is deducted, no invoice created** (WF-3 error handling: "If any garment becomes unavailable between cart creation and checkout, the transaction fails and the specific garment is flagged").

- `void(SaleInvoice $invoice, User $actor): SaleInvoice` — **NOT supported in Phase 3** per WF-3 post-condition ("Invoice is immutable — cannot be deleted or cancelled"). Throw `BadMethodCallException` with message "Completed sales cannot be voided; use the Returns module (Phase 5) for corrections." (Define the method so the route doesn't 404; it deliberately refuses.) Actually — per roadmap Phase 3 "Completed sale correction (via returns, not cancellation)" — so no void path. Document this in the policy and service.

**8. SaleInvoicePolicy (`app/Modules/Sales/Policies/SaleInvoicePolicy.php`):**
- `viewAny(User $user): bool` — `pos.sale.create` (any selling role can see their own sale list).
- `view(User $user, SaleInvoice $inv): bool` — admin: all; branch supervisor: `$inv->branch_id === $user->assigned_branch_id && $inv->completed_at->isToday()` (today-only per doc 05 §5.1 ADR-018); salesperson: `$inv->salesperson_id === $user->id && $inv->completed_at->isToday()`.
- `create(User $user): bool` — `pos.sale.create` AND `$user->canSell()`.
- `complete(User $user, SaleInvoice $inv): bool` — `pos.sale.complete` AND `$inv->branch_id === $user->assigned_branch_id`.
- `applyDiscount(User $user): bool` — `pos.discount.apply`.
- `void(User $user, SaleInvoice $inv): bool` — false (no voiding in Phase 3).

**9. SaleCompleted event (`app/Modules/Sales/Events/SaleCompleted.php`):**
- Simple data event: `__construct(public SaleInvoice $invoice)`. No listeners registered in Phase 3 (Phase 6 adds Cash listener). Discoverable for future phases.

**10. BelowMinimumPriceException + GarmentNotAvailableException** (`app/Modules/Sales/Exceptions/`): typed exceptions with `withMessage` payloads matching doc 07 §6 error shapes (`below_minimum_price`, `garment_not_available`).

**11. Module registration:** `app/Modules/Sales/SalesServiceProvider.php` registered in `bootstrap/providers.php`.

**12. Tests (`tests/Feature/Sales/SaleServiceTest.php`):**
- Sale completes atomically: invoice `completed`, garment `current_status = 'sold'`, exactly one `inventory_movements` row (type `sale`) per garment, `garment_current_stock` updated, customer linked.
- Only `available` or `clearance` garments can be added (reserved/in_transit/sold/damaged/frozen rejected with `GarmentNotAvailableException`).
- Cannot sell a garment from another branch (branch scope).
- Discount below `minimum_price` is rejected (`BelowMinimumPriceException` with garment_id + final_price + minimum_price).
- `total_paid < total_amount` is rejected.
- Multiple payment methods supported (cash + bkash in one sale).
- Payment method requiring reference (bkash) without reference is rejected.
- Invoice number is unique and follows `INV-YY-NNNNN` format.
- `return_policy_snapshot` is stored as JSON with `return_period_days: 7`.
- `SaleCompleted` event is dispatched (use `Event::fake()` and assert dispatched).
- `sale.complete` audit log entry written (`category = sales`, `reference_type = sale_invoice`).
- **Concurrency test: two concurrent sales of the same garment — one succeeds, one fails.** Use parallel `DB::transaction` / async or process fork (per doc 08 §5); assert exactly one invoice created, exactly one garment `sold`, the other attempt throws `GarmentNotAvailableException`, and `$successCount === 1`.
- Sale in a closed fiscal year is rejected.
- Salesperson with `pos.sale.complete` at their own branch succeeds; at another branch rejected (branch scope).
- Salesperson without `pos.sale.complete` (hypothetical) rejected.
- Void throws (not supported).

### Acceptance criteria — Session 3.2 (COMPLETE)
- [x] `SaleService::complete()` runs as a single atomic transaction; on any failure, no stock is deducted and no invoice is created. (atomic-rollback test confirms: a mid-cart unavailable garment leaves 0 invoices/items/payments and the available garment stays available.)
- [x] `SELECT ... FOR UPDATE` row-level locking prevents two concurrent sales of the same garment. (DB::listen query-capture asserts a `for update` lock query on `garments` per cart line; the in-transaction re-verification rejects an already-sold garment with `GarmentNotAvailableException`.)
- [x] Reserved/in-transit/frozen/damaged garments cannot be sold (only `available` and `clearance`). (eligibility tests cover each non-saleable status + clearance acceptance.)
- [x] Garments can only be sold at their current branch (branch scope enforced). (wrong-branch test throws `GarmentNotAvailableException`, 0 invoices.)
- [x] Discount below `minimum_price` is rejected with a typed `BelowMinimumPriceException` carrying the doc-07 error payload (garment_id, final_price, minimum_price).
- [x] `total_paid >= total_amount` enforced; multiple payment methods supported (cash + bkash test).
- [x] Invoice is immutable after completion; voiding is not supported — `SaleService::void()` throws `BadMethodCallException` (returns module handles corrections in Phase 5).
- [x] `SaleCompleted` event dispatched (Event::fake assertion); `sale.complete` audit entry written (DB assertion); `return_policy_snapshot` stored as JSON with `return_period_days: 7`.
- [x] Invoice numbers are unique, concurrency-safe (advisory-lock `pg_advisory_xact_lock(9000001 + fy.id)` via `document_sequences`), and follow `INV-YY-NNNNN`.
- [x] 32 tests written (83 assertions) including the 3 concurrency tests.

---

## Session 3.3: Sales — POS Interface, Barcode Lookup, Cart & Discount

**Goal:** Build the POS Livewire interface for building a sale: garment lookup by barcode/serial, cart add/remove/replace, and per-line discount with minimum-price enforcement. This session covers the "building the sale" half; checkout/payment/completion is 3.4. Cart state is per-session (Livewire component state), persisted only in memory (no DB draft invoices — WF-3 step 3d "temporary in-memory reservation" per the open design choice; we choose in-memory cart with no DB reservation, matching ADR-008 "no automatic reservation expiry" philosophy and keeping the transaction simple).

**Estimated time:** 3-4 hours

### Prerequisites
- Session 3.2 complete (`SaleService`, models, `SaleInvoicePolicy`).
- Phase 1 `GarmentIdentityService::lookupByBarcode/lookupBySerial` available.

### Deliverables

**1. Cart value object (`app/Modules/Sales/Support/CartState.php`):**
- A plain PHP class (or Livewire component property bag) holding an ordered list of `CartItem` (garment_id, barcode, serial, product_name, size_label, standard_price, applied_discount, final_price, is_clearance). Methods: `add(Garment, ProductPrice)`, `remove(garment_id)`, `replace(garment_id, newGarment_id)` (WF-3 step 3 "replace items"), `applyDiscount(garment_id, float)`, `clear()`, `subtotal()`, `totalDiscount()`, `total()`, `count()`, `toArray()`. Enforces minimum-price in `applyDiscount()` (throws `BelowMinimumPriceException`). **No DB persistence** — lives in the Livewire component's public properties (Livewire serializes to the session).

**2. POS Livewire component (`app/Modules/Http/Livewire/POS/PointOfSale.php`):**
- Public state: `$cart` (CartState), `$searchTerm` (barcode/serial/product), `$customerId`, `$customerPhone`, `$customerName`, `$saleType = 'retail'`.
- Actions:
  - `scanBarcode($code)` — calls `GarmentIdentityService::lookupByBarcode($code)` (or `lookupBySerial` if input matches `/\d{2}-\d{5}/`); on hit, validates the garment is `canBeSold()` AND at the user's branch (`current_branch_id === active branch`) AND not already in cart; on miss, sets a flash error with the reason (sold/reserved/wrong-branch/damaged/already-in-cart). **Performance target: < 100ms** (doc 06 §1.1).
  - `searchProducts($term)` — product/size search via `StockQueryService::searchGarments` scoped to the user's branch + available; renders a dropdown; click adds to cart.
  - `removeFromCart($garmentId)` — removes the line.
  - `replaceItem($oldGarmentId, $newBarcode)` — swaps one garment for another (WF-3 "replace items").
  - `applyDiscount($garmentId, $amount)` — updates line discount; enforces `final_price >= minimum_price` (client-side preview + server-side enforcement on submit; throws on violation).
  - `clearCart()`.
- Computed: subtotal, total discount, total amount, item count.
- **No completion in this session** — the "Complete Sale" button calls into 3.4's checkout (a separate Livewire component or a modal sub-component). For 3.3, the button can be disabled or emit an event consumed by 3.4.
- Branch context: the component reads the active branch from `app('active.branch')` (set by `SetActiveBranchScope` middleware) and the salesperson from `Auth::user()`.
- Authorization: `pos.sale.create` required to view the POS; policy enforced in `render()`.

**3. View (`resources/views/livewire/sales/pos/point-of-sale.blade.php`):**
- Layout: left column = search/scan input + barcode scan field (auto-focus) + product search results dropdown; right column = cart table (garment, product/size, standard price, discount input, final price, remove/replace buttons) + totals + "Proceed to Checkout" button.
- Barcode input: supports hardware scanner (which types Enter) — `wire:keydown.enter="scanBarcode"`. Auto-focus, clears after each scan.
- Discount input: inline editable per line; shows minimum-price hint; red highlight if at/below minimum.
- Responsive: works on tablet (primary POS device) and desktop.
- No sensitive data shown (never `purchase_cost`).

**4. Route (`routes/web.php`):**
- `GET /pos` — PointOfSale (auth + `pos.sale.create`).

**5. Nav link:** add "POS / New Sale" to the layout, gated by `@can('create', \App\Modules\Sales\Models\SaleInvoice::class)`, placed prominently (first nav item for selling roles).

**6. Tests (`tests/Feature/Sales/PosInterfaceTest.php`):**
- POS page loads for a user with `pos.sale.create`; 403 for a user without.
- Barcode scan of an `available` garment at the user's branch adds it to the cart.
- Barcode scan of a `sold`/`reserved`/`in_transit`/`damaged` garment shows the correct rejection reason and does NOT add to cart.
- Barcode scan of a garment at another branch shows "wrong branch" and does not add.
- Scanning the same garment twice shows "already in cart".
- Serial lookup (`25-00001`) works the same as barcode.
- Product search returns only `available` garments at the user's branch.
- Remove from cart works.
- Replace item swaps the garment.
- Apply discount above minimum succeeds; discount below minimum is rejected (client preview blocks + server re-validates).
- Cart totals compute correctly (subtotal, total discount, total).
- Clear cart empties all lines.
- `purchase_cost` never appears in the rendered POS HTML.

### Acceptance criteria — Session 3.3 (COMPLETE)
- [x] POS page renders for authorized users (salesperson + admin); 403 for a role without `pos.sale.create`; guest redirected to login.
- [x] Barcode scan and serial lookup (`YY-NNNNN` auto-detected) resolve a garment via `GarmentIdentityService::lookupByBarcode/lookupBySerial`; both paths covered by tests (the lookup itself hits a unique barcode/serial index, well under the 100ms target).
- [x] Only `available`/`clearance` garments at the user's branch can be added; sold/reserved/damaged/missing/frozen/under-inspection show a specific status-named rejection reason; wrong-branch shows "another branch". The search dropdown filters to available + own-branch only.
- [x] Cart supports add (scan + search-dropdown click), remove, replace (scan replacement barcode), and per-line discount via the `CartState` value object.
- [x] Discount below minimum price is blocked server-side (`BelowMinimumPriceException` → `@error` on the discount field); negative and over-standard discounts also rejected.
- [x] No sensitive data (`purchase_cost`, supplier info) appears in the POS UI — verified for both the initial page render and the post-add-to-cart render; `StockQueryService::searchGarments` already calls `makeHidden('purchase_cost')`.
- [x] Cart state survives Livewire round-trips (in-memory via the serializable `$cartItems` public property; no DB drafts — `SaleInvoice::count()` is 0 throughout).
- [x] 33 tests written (75 assertions) — all acceptance criteria covered.

---

## Session 3.4: Sales — POS Checkout, Payment, Completion & Customer Linking

**Goal:** Build the POS checkout flow: customer selection (search by phone / create walk-in), payment collection with multiple methods, `total_paid >= total_amount` enforcement, and atomic completion via `SaleService::complete()`. Wires the cart from 3.3 to the customer and payment inputs, then commits the sale.

**Estimated time:** 3-4 hours

### Prerequisites
- Sessions 3.1 (CustomerService::findOrCreateByPhone), 3.2 (SaleService::complete), 3.3 (POS cart) complete.

### Deliverables

**1. Checkout Livewire component (`app/Modules/Sales/Http/Livewire/POS/Checkout.php`):**
- Receives the cart (via Livewire event from PointOfSale, or shared parent component state). Public state: `$customerId`, `$customerPhone`, `$customerName`, `$customerExists`, `$payments = [['method' => 'cash', 'amount' => 0, 'reference' => null]]`, `$saleType = 'retail'`, `$notes`.
- Actions:
  - `lookupCustomer($phone)` — calls `CustomerService::search($phone, $viewer)`; if found, populates `$customerId/$customerName` and shows "existing customer"; if not, shows "create new" with name field.
  - `addPaymentMethod()` / `removePaymentMethod($index)` — dynamic payment lines.
  - `updatePaymentAmount($index, $amount)` — recomputes `totalPaid` and `change = totalPaid - total` (if overpaid, show change due — purely informational; per WF-3 step 5d only `total_paid >= total_amount` is required, change is given physically).
  - `completeSale()` — assembles the `SaleService::complete()` payload from cart + customer + payments; calls the service inside a try/catch; on `BelowMinimumPriceException`/`GarmentNotAvailableException`/validation error, shows the specific garment and lets the salesperson remove it and retry (WF-3 error handling); on success, clears the cart and shows a "Sale completed — Print receipt" action (routes to 3.5) and the invoice number.
- Authorization: `pos.sale.complete` required; policy `SaleInvoicePolicy::complete` enforced (branch match).
- Payment methods dropdown sourced from active `payment_methods` (`is_active = true`), sorted; reference field shown only when `requires_reference = true`.
- Validates: at least one payment line, all amounts > 0, `total_paid >= total_amount`, reference present where required.

**2. View (`resources/views/livewire/sales/pos/checkout.blade.php`):**
- Customer section: phone input + search button; existing-customer card (name) OR new-customer form (name optional); "Walk-in (no customer)" toggle (per WF-3 customer selection — phone is mandatory in the workflow, but the service allows null for true walk-in edge cases; default to requiring phone in the UI).
- Payment section: dynamic lines (method dropdown, amount, reference); running total paid; change-due display; "Add payment method" button.
- Summary: subtotal, total discount, total amount, total paid, change due.
- "Complete Sale" button (disabled until valid); on success, success card with invoice number + "Print receipt" + "New sale".

**3. Wire-up:** `PointOfSale` "Proceed to Checkout" emits `openCheckout` → `Checkout` mounts (modal or full-page). On completion, `Checkout` emits `saleCompleted(invoiceId)` → `PointOfSale` clears cart.

**4. Tests (`tests/Feature/Sales/PosCheckoutTest.php`):**
- Checkout requires `pos.sale.complete`; 403 without.
- Customer lookup by phone finds an existing customer; creating a new walk-in customer works via `findOrCreateByPhone`.
- Multiple payment methods (cash + bkash) sum correctly.
- `total_paid < total_amount` blocks completion with a "short by X" message.
- bkash payment without reference is rejected.
- Successful completion: invoice `completed`, garment `sold`, customer linked, `sale_payments` rows correct, cart cleared, success card shows invoice number.
- If a garment became sold between cart-build and completion (simulate by selling it in a parallel `DB::transaction` before `completeSale()`), `completeSale()` throws `GarmentNotAvailableException`, shows the specific garment, and does NOT create an invoice (atomic rollback).
- Discount re-validation at completion (if price changed or discount pushed below minimum, completion rejects).
- Sale is audited (`sale.complete`).
- Branch scope: completing a sale for another branch's garment is rejected.

### Acceptance criteria — Session 3.4 (COMPLETE)
- [x] Customer can be looked up by phone (`lookupCustomer` → existing card) or created as walk-in at checkout (`CustomerService::findOrCreateByPhone` on completion); a true-walk-in toggle allows no-customer sales.
- [x] Multiple payment methods supported with correct summation (cash + bkash test asserts `total_paid` = sum; add/remove payment lines tested).
- [x] `total_paid >= total_amount` enforced (blocked with a "short by X" message); change-due shown when overpaid (overpayment accepted, change displayed).
- [x] Reference required for non-cash methods (bkash without reference rejected; the reference field only renders for methods with `requires_reference = true`).
- [x] Atomic completion via `SaleService::complete()`; on a mid-checkout garment conflict the specific garment id is flagged ("no longer available") and the sale rolls back — 0 invoices, 0 items, 0 payments, the other garment stays available (no partial deduction).
- [x] On success, cart clears (both Checkout's `cartItems` and PointOfSale's via `checkoutClosed` event) and the invoice number (INV-YY-NNNNN) + "Print receipt"/"New sale" actions are shown.
- [x] Branch scope enforced (SaleService rejects a garment at another branch; checkout uses the salesperson's active branch) and `pos.sale.complete` required (a role without it is denied).
- [x] 21 tests written (66 assertions) — all acceptance criteria covered.

---

## Session 3.5: Sales — Receipt Printing (Thermal 58/80mm & A4, No-Tax-Line)

**Goal:** Build receipt generation and printing for completed sales. Receipts print on thermal 58mm and 80mm rolls and on A4, with **no tax/VAT line** (ADR-010, pending legal review). Reuse the Phase 2 print-transport pattern (browser CSS `@page` print media) and the print layout (`resources/views/layouts/print.blade.php`).

**Estimated time:** 2-3 hours

### Prerequisites
- Session 3.4 complete (completed sale invoices exist).
- Phase 2 complete (`resources/views/layouts/print.blade.php`, printer settings infrastructure).

### Deliverables

**1. ReceiptService (`app/Modules/Sales/Services/ReceiptService.php`):**
- `generateReceiptData(SaleInvoice $invoice): array` — assembles receipt payload: invoice number, branch name + address + phone, salesperson name, completed_at (formatted), customer phone + name (if linked), line items (product name, size, barcode/serial, standard price, discount, final price), subtotal, total discount, total amount, payment breakdown (method + amount + reference), `return_policy_snapshot.return_period_days` rendered as a footer note ("Returns accepted within N days at this branch"), and a **deliberate absence of any tax/VAT line** (ADR-010). Hides `purchase_cost` (never in receipt).
- `render(SaleInvoice $invoice, string $format = '80mm'): HtmlString` — renders the appropriate Blade view with the data. Formats: `'58mm'`, `'80mm'`, `'a4'`.
- `getAvailableFormats(): array` — `[['key'=>'80mm','label'=>'Thermal 80mm'], ['key'=>'58mm','label'=>'Thermal 58mm'], ['key'=>'a4','label'=>'A4']]`. Default `80mm`.

**2. Receipt views (under `resources/views/sales/receipts/`):**
- `_line-items.blade.php` — shared line-items partial.
- `80mm.blade.php`, `58mm.blade.php`, `a4.blade.php` — format-specific wrappers using `@page { size: 80mm auto; }` / `58mm auto` / `A4` CSS, monospaced thermal fonts for 58/80mm, normal fonts for A4. Extend `resources/views/layouts/print.blade.php`.
- **No tax line in any format.** Add a code comment `// ADR-010: no tax/VAT line on customer-facing receipt (pending legal review)`.
- Footer: thank-you line + return-policy note (N days, original branch only — per Phase 5 WF-10 rule previewed from the snapshot).

**3. PrintReceipt Livewire component (`app/Modules/Sales/Http/Livewire/POS/PrintReceipt.php`):**
- Receives `invoiceId`; loads `SaleInvoice` (policy `view`); renders a format selector (default per branch/system setting) and a "Print" button that opens the rendered receipt view in a new window (`window.open(route('sales.receipts.print', [$invoice, $format]))`) which calls the browser print dialog. A "Back to POS" button returns to the POS.

**4. Route:**
- `GET /pos/receipts/{invoice}` — renders the receipt HTML (format via `?format=80mm`) for printing. Policy `view` on the invoice.
- Livewire `PrintReceipt` component mounted on a small modal/page after 3.4's completion.

**5. Receipt print from invoice list:** add a "Print receipt" link on the sale-invoice list (built in a later session or minimally here) for re-printing. (The sale list UI itself is part of reporting — minimal list here, full reports in Phase 9.)

**6. Tests (`tests/Feature/Sales/ReceiptPrintingTest.php`):**
- All three formats render without errors for a completed invoice.
- Receipt contains invoice number, branch, salesperson, line items, totals, payment breakdown.
- Receipt does **not** contain the strings "VAT", "Tax", "ট্যাক্স" (no-tax-line compliance — ADR-010).
- `purchase_cost` does not appear in any receipt format.
- Return-policy footer shows the snapshot's `return_period_days`.
- Draft invoice cannot be printed (only `completed`).
- Non-owning-branch salesperson cannot print another branch's invoice (403 via policy).
- Walk-in sale (no customer) renders without a customer block.

### Acceptance criteria — Session 3.5
- [ ] Receipts render for 58mm, 80mm, and A4 with correct `@page` sizing.
- [ ] No tax/VAT line on any receipt format (ADR-010).
- [ ] `purchase_cost` never appears on a receipt.
- [ ] Only completed invoices are printable; branch scope enforced.
- [ ] Return-policy footer uses the sale-time snapshot.
- [ ] ~12 tests written.

---

## Session 3.6: Transfers — Data Model & Reservation-Aware Transfer Service

**Goal:** Build the Transfers module data model and the `TransferService` implementing the full lifecycle (WF-6 + WF-7): create request → implicit reservation, approve, reject → release, dispatch → in_transit, receive → available (with discrepancy). Includes `TransferPolicy`, the `TransferRequested`/`TransferDispatched`/`TransferReceived` domain events, and the critical test that a reserved garment **cannot be sold via POS** (proving the reservation model blocks the POS path from 3.2/3.4). Backend-only, fully testable.

**Estimated time:** 4-5 hours

### Prerequisites
- Sessions 3.2 complete (SaleService — needed to prove reservation blocks POS).
- Phase 1 complete (`Garment::reserve/releaseReservation/markInTransit/relocate`, `InventoryMovementService` with `TYPE_RESERVATION/RESERVATION_RELEASE/TRANSFER_DISPATCH/TRANSFER_RECEIVE`, `garment_current_stock.reservation_reference_type/id`).

### Deliverables

**1. Migrations:**

`transfer_requests` (`app/Modules/Transfers/Database/Migrations/2025_01_01_000060_create_transfer_requests_table.php`):
```
id BIGSERIAL PRIMARY KEY
request_number VARCHAR(50) UNIQUE NOT NULL           -- 'TR-25-00001'
from_branch_id BIGINT NOT NULL REFERENCES branches(id)
to_branch_id BIGINT NOT NULL REFERENCES branches(id)
status VARCHAR(20) NOT NULL CHECK (status IN
  ('pending','approved','rejected','dispatched','received','partially_received','cancelled','discrepancy'))
  DEFAULT 'pending'
requested_by BIGINT NOT NULL REFERENCES users(id)
requested_at TIMESTAMPTZ DEFAULT NOW()
approved_by BIGINT NULL REFERENCES users(id)
approved_at TIMESTAMPTZ NULL
rejected_by BIGINT NULL REFERENCES users(id)
rejected_at TIMESTAMPTZ NULL
rejection_reason TEXT NULL
dispatched_by BIGINT NULL REFERENCES users(id)
dispatched_at TIMESTAMPTZ NULL
received_by BIGINT NULL REFERENCES users(id)
received_at TIMESTAMPTZ NULL
online_order_id BIGINT NULL                          -- forward-compat for Phase 4 (no FK; added later)
notes TEXT NULL
created_at TIMESTAMPTZ DEFAULT NOW()
updated_at TIMESTAMPTZ DEFAULT NOW()
```
Indexes: `INDEX(status)`, `INDEX(from_branch_id, to_branch_id)`, `INDEX(from_branch_id, status)`, `INDEX(to_branch_id, status)`. No separate approvals table.

`transfer_request_items` (`2025_01_01_000061_create_transfer_request_items_table.php`):
```
id BIGSERIAL PRIMARY KEY
transfer_request_id BIGINT NOT NULL REFERENCES transfer_requests(id) ON DELETE CASCADE
garment_id BIGINT NOT NULL REFERENCES garments(id)
product_id BIGINT NOT NULL REFERENCES products(id)
product_size_id BIGINT NULL REFERENCES product_sizes(id)
dispatched BOOLEAN DEFAULT FALSE
received BOOLEAN DEFAULT FALSE
created_at TIMESTAMPTZ DEFAULT NOW()
```
One row per garment (garment-level granularity per doc 03). Index `INDEX(transfer_request_id)`, `INDEX(garment_id)`.

`transfer_discrepancies` (`2025_01_01_000062_create_transfer_discrepancies_table.php`):
```
id BIGSERIAL PRIMARY KEY
transfer_request_id BIGINT NOT NULL REFERENCES transfer_requests(id)
discrepancy_type VARCHAR(20) CHECK (discrepancy_type IN ('shortage','excess','wrong_item'))
reason VARCHAR(255) NULL
investigated_by BIGINT NULL REFERENCES users(id)
resolved_by BIGINT NULL REFERENCES users(id)
resolution_notes TEXT NULL
status VARCHAR(20) DEFAULT 'open' CHECK (status IN ('open','investigating','resolved'))
created_at TIMESTAMPTZ DEFAULT NOW()
updated_at TIMESTAMPTZ DEFAULT NOW()
```
Note: doc 03 also has `expected_quantity`, `received_quantity`, `missing_garment_ids BIGINT[]`. In our garment-level model, expected/received quantities are derived from `transfer_request_items` (count dispatched vs received), so we store per-discrepancy rows referencing the specific garments via a separate `transfer_discrepancy_items(transfer_discrepancy_id, garment_id, type)` OR reuse the `transfer_request_items` flags. **Decision:** keep `transfer_discrepancies` as a header with `missing_garment_ids JSON` (array of garment ids not received) and `excess_garment_ids JSON` to avoid PostgreSQL array dependency (Prisma/SQLite-friendly note — but this is Laravel/Postgres; arrays are fine). Use `missing_garment_ids BIGINT[]` to match doc 03. Track per-garment via `transfer_request_items.received`.

**2. Models:**
- `TransferRequest` (`app/Modules/Transfers/Models/TransferRequest.php`): traits `Auditable` (`$auditPrefix='transfer'`, `$auditCategory='transfers'`), `HasFactory`. Status constants for the 8 values. Fillable + casts. Relationships: `items()`, `fromBranch()`, `toBranch()`, `requestedBy()`, `approvedBy()`, `dispatchedBy()`, `receivedBy()`, `discrepancies()`. Scopes: `pending()`, `approved()`, `dispatched()`, `received()`, `forBranch($id)` (from OR to), `incomingTo($id)`, `outgoingFrom($id)`. Helpers: `isPending/approved/dispatched/received/hasDiscrepancy/canApprove/canDispatch/canReceive`.
- `TransferRequestItem`: relationships `transferRequest()`, `garment()`, `product()`, `productSize()`.
- `TransferDiscrepancy`: relationships `transferRequest()`, `investigatedBy()`, `resolvedBy()`.

**3. TransferNumberService (`app/Modules/Transfers/Services/TransferNumberService.php`):**
- `generateNextRequestNumber(FiscalYear $fy): string` — `pg_advisory_xact_lock(9000002 + $fy->id)`, returns `'TR-'.$prefix.'-'.str_pad($seq,5,'0',STR_PAD_LEFT)`. Uses the `document_sequences` table from 3.2.

**4. TransferService (`app/Modules/Transfers/Services/TransferService.php`):**

Constructor injects `InventoryMovementService`, `StockQueryService`, `TransferNumberService`, `FiscalYearService`.

- `createRequest(User $requester, Branch $fromBranch, Branch $toBranch, array $garmentIds, ?string $notes = null): TransferRequest`
  - Policy: `TransferRequestPolicy::create` — `transfer.request.create`. Requester must be a supervisor (or admin); the from-branch must be a valid source. (Online supervisor creates requests TO the online channel.)
  - Validate: `$fromBranch->id !== $toBranch->id`; at least one garment; each garment must be `available` AND at `$fromBranch` (cannot request a reserved/in-transit/sold garment); fiscal year open.
  - `DB::transaction`: generate number; create `TransferRequest` (status `pending`); for each garment: call `InventoryMovementService::record($garment, TYPE_RESERVATION, ['reference_type' => 'transfer_request', 'reference_id' => $request->id, 'performed_by' => $requester->id, 'notes' => 'Transfer request '.$number])` — this atomically sets `garment.current_status = 'reserved'`, updates `garment_current_stock` (sets `reservation_reference_type = 'transfer_request'`, `reservation_reference_id = $request->id`), writes the append-only movement, audits `inventory.movement`. Create `TransferRequestItem` rows. Dispatch `TransferRequested` event. Audit `transfer.request`. Return fresh request.

- `approve(TransferRequest $request, User $approver): TransferRequest`
  - Policy: `TransferRequestPolicy::approve` — `transfer.approve` AND `$approver` is at the **source** branch (`from_branch_id === $approver->assigned_branch_id`) or admin (Main Admin approves when source = Main). 
  - Validate status `pending`.
  - `DB::transaction`: set `status = 'approved'`, `approved_by`, `approved_at`. Audit `transfer.approve`. Garments remain `reserved`. Return fresh.

- `reject(TransferRequest $request, User $approver, string $reason): TransferRequest`
  - Same policy as approve (source supervisor).
  - Validate status `pending` (or `approved` — allow rejecting an approved-but-not-dispatched request).
  - `DB::transaction`: set `status = 'rejected'`, `rejected_by`, `rejected_at`, `rejection_reason`. For each item garment: `InventoryMovementService::record($garment, TYPE_RESERVATION_RELEASE, ['reference_type' => 'transfer_request', 'reference_id' => $request->id, 'performed_by' => $approver->id, 'notes' => 'Released: '.$reason])` — sets garment back to `available`, clears reservation ref. Audit `transfer.reject`. Return fresh.

- `dispatch(TransferRequest $request, User $dispatcher): TransferRequest`
  - Policy: `TransferRequestPolicy::dispatch` — `transfer.dispatch` AND source-branch supervisor/admin.
  - Validate status `approved`.
  - `DB::transaction`: for each item garment: `InventoryMovementService::record($garment, TYPE_TRANSFER_DISPATCH, ['reference_type' => 'transfer_request', 'reference_id' => $request->id, 'to_branch_id' => $toBranch->id, 'performed_by' => $dispatcher->id, 'notes' => 'Dispatch'])` — sets `garment.current_status = 'in_transit`, keeps `current_branch_id = fromBranch` (physically still leaving) OR sets to null — **decision:** set `current_branch_id` to the source branch still (garment is in transit, not yet at destination); `markInTransit($toBranchId)` on the Garment model sets status only. Set `transfer_request_items.dispatched = true`. Set `request.status = 'dispatched'`, `dispatched_by`, `dispatched_at`. Dispatch `TransferDispatched`. Audit `transfer.dispatch`. Return fresh.

- `receive(TransferRequest $request, User $receiver, array $receivedGarmentIds, ?array $discrepancyData = null): TransferRequest`
  - Policy: `TransferRequestPolicy::receive` — `transfer.receive` AND destination-branch supervisor/admin.
  - Validate status `dispatched`.
  - Compute: expected = items where dispatched=true; received set = `$receivedGarmentIds`; missing = expected minus received; excess = received not in expected.
  - `DB::transaction`:
    - For each received garment: `InventoryMovementService::record($garment, TYPE_TRANSFER_RECEIVE, ['reference_type' => 'transfer_request', 'reference_id' => $request->id, 'to_branch_id' => $toBranch->id, 'to_location_id' => $toBranch default location, 'performed_by' => $receiver->id])` — sets `garment.current_status = 'available'`, `current_branch_id = toBranch`, clears reservation ref. Set `transfer_request_items.received = true`.
    - For each missing garment: leave `in_transit` OR set to `missing` — **decision per WF-7 step 2e:** set missing garments to `missing` status (via `InventoryMovementService::record($garment, TYPE_MISSING_REPORT, [...])`) so they're flagged for investigation (WF-20, Phase 7). **Never silently mark missing as received.**
    - If excess garments scanned: create a discrepancy row `type = 'excess'`.
    - Determine final status: all received → `received`; some missing → `discrepancy` (or `partially_received` if some received and rest still in transit pending another receipt — keep simple: `partially_received` when some-but-not-all received and no discrepancy flag needed, `discrepancy` when missing/excess recorded). Set `received_by`, `received_at`.
    - Create `TransferDiscrepancy` row if missing or excess, with `missing_garment_ids`/excess arrays.
    - Dispatch `TransferReceived`. Audit `transfer.receive`. Return fresh.

- `cancel(TransferRequest $request, User $actor, string $reason): TransferRequest`
  - Policy: requester or admin, only if status `pending` or `approved` (not dispatched).
  - Release all reservations (same as reject). Set `cancelled`. Audit `transfer.cancel`.

**5. TransferRequestPolicy (`app/Modules/Transfers/Policies/TransferRequestPolicy.php`):**
- `viewAny(User $user): bool` — `transfer.request.create` OR `transfer.approve` OR `transfer.dispatch` OR `transfer.receive` (any transfer role sees the list, scoped by branch).
- `view(User $user, TransferRequest $r): bool` — admin: all; others: `$r->from_branch_id === $user->assigned_branch_id` OR `$r->to_branch_id === $user->assigned_branch_id`.
- `create(User $user): bool` — `transfer.request.create` (supervisor or online supervisor).
- `approve(User $user, TransferRequest $r): bool` — `transfer.approve` AND ($r->from_branch_id === user's branch OR admin).
- `dispatch(User $user, TransferRequest $r): bool` — `transfer.dispatch` AND (source branch OR admin).
- `receive(User $user, TransferRequest $r): bool` — `transfer.receive` AND (dest branch OR admin).
- `reject/cancel` — same as approve / create respectively.

**6. Events:** `TransferRequested`, `TransferDispatched`, `TransferReceived` (data events; no listeners in Phase 3 — Phase 4 Online Orders will listen to drive `online_order_items.status`).

**7. Module registration:** `app/Modules/Transfers/TransfersServiceProvider.php` in `bootstrap/providers.php`.

**8. Tests (`tests/Feature/Transfers/TransferServiceTest.php`):**
- `createRequest` reserves all garments (`current_status = 'reserved'`, `garment_current_stock.reservation_reference_type = 'transfer_request'`); movement `reservation` written; `TransferRequested` dispatched; audit `transfer.request`.
- Cannot request a garment not `available` (reserved/in_transit/sold/damaged/frozen rejected).
- Cannot request a garment from another branch as the source (source validation).
- `approve` sets `approved`; garments stay `reserved`.
- `reject` releases reservations (`current_status = 'available'`, reservation ref cleared); movement `reservation_release` written; audit `transfer.reject`.
- `dispatch` sets garments `in_transit`; movement `transfer_dispatch`; `TransferDispatched` dispatched; audit `transfer.dispatch`.
- `receive` with all garments sets them `available` at destination (`current_branch_id = toBranch`, status cleared); movement `transfer_receive`; `TransferReceived` dispatched; request `received`; audit `transfer.receive`.
- `receive` with shortage: missing garments → `missing` status (not silently received); discrepancy row created with `missing_garment_ids`; request status `discrepancy`.
- `receive` with excess: discrepancy row `type = 'excess'`.
- **Critical cross-module test: a reserved garment (by a transfer request) cannot be sold via POS.** Create a transfer request reserving garment G; attempt `SaleService::complete()` with G in the cart; assert `GarmentNotAvailableException` thrown (because G is `reserved`, not `available`/`clearance`); assert no invoice created, no movement.
- `cancel` releases reservations.
- Number generation `TR-YY-NNNNN` is concurrency-safe (parallel `createRequest` → unique numbers).
- Source supervisor can approve; destination supervisor cannot approve (403); salesperson cannot create/dispatch/receive (403).

### Acceptance criteria — Session 3.6
- [ ] Full transfer lifecycle (create → approve → dispatch → receive) works atomically with correct garment status transitions and append-only movements.
- [ ] Reservation is implicit (garment status + `garment_current_stock` ref + movement) — no separate reservations table.
- [ ] Rejection and cancellation release reservations back to `available`.
- [ ] Receiving a shortage marks missing garments `missing` (never silently received); discrepancy recorded.
- [ ] **A garment reserved by a transfer cannot be sold via POS** (cross-module guarantee proven by test).
- [ ] Role gating: source supervisor approves/dispatches; destination supervisor receives; salesperson blocked.
- [ ] Transfer numbers concurrency-safe (`TR-YY-NNNNN`).
- [ ] All actions audited; `TransferRequested/Dispatched/Received` events dispatched.
- [ ] ~28 tests written including the cross-module reservation-blocks-POS test.

---

## Session 3.7: Transfers — UI: Request Creation (Cross-Branch Stock Picker), Approval & Dispatch

**Goal:** Build the Livewire UI for the transfer lifecycle up to dispatch: request creation with a cross-branch stock picker (using `StockQueryService`), supervisor approval/rejection with reason, and dispatch with a printable dispatch note.

**Estimated time:** 3-4 hours

### Prerequisites
- Session 3.6 complete (`TransferService`).
- Phase 1 `StockQueryService::getStockForProduct` / `getAvailableStockForAllBranches` available.

### Deliverables

**1. TransferList Livewire component (`app/Modules/Transfers/Http/Livewire/TransferList.php`):**
- Paginated table (25/page) of transfer requests scoped to the user's branch (from OR to). Filters: status, direction (incoming/outgoing). Columns: number, from→to, status, requested by, requested at, item count. Click → detail view. Role-gated actions (Approve/Reject/Dispatch/Receive buttons shown per policy).

**2. TransferCreate Livewire component (`app/Modules/Transfers/Http/Livewire/Transfers/TransferCreate.php`):**
- Steps:
  1. Select source branch (dropdown of active branches excluding the requester's own? — no, requester creates a request FROM another branch TO their own, OR from their own to another; per WF-6 the requesting branch needs stock from another branch, so typically from=other, to=own. But also allow own→other for sending stock out. Keep both directions selectable.) and destination branch.
  2. **Cross-branch stock picker:** select a product → `StockQueryService::getStockForProduct($product, $size)` returns per-branch available counts; the picker shows available garments at the source branch (by barcode/serial/product). Add garments to the request (one row per garment — garment-level). Validates each is `available` at source.
  3. Notes.
  4. Submit → `TransferService::createRequest()`.
- Authorization: `transfer.request.create`.

**3. TransferView Livewire component (`app/Modules/Transfers/Http/Livewire/Transfers/TransferView.php`):**
- Read-only detail: header (number, from→to, status, timestamps, actors), items table (garment barcode/serial, product/size, dispatched, received), discrepancy section if any.
- Action buttons (per policy + status):
  - Status `pending` + source supervisor → Approve / Reject (reject opens a reason modal).
  - Status `approved` + source supervisor → Dispatch (confirms, calls `TransferService::dispatch()`, shows printable dispatch note).
  - Status `dispatched` + destination supervisor → Receive (routes to 3.8's receiving component).
  - Status `pending`/`approved` + requester → Cancel.

**4. Dispatch note view (`resources/views/transfers/dispatch-note.blade.php`):**
- Printable dispatch note (extends `layouts/print.blade.php`): request number, from→to, dispatched by/at, items list (garment barcode/serial, product/size). Browser print via `@page`.

**5. Views:**
- `resources/views/livewire/transfers/list.blade.php`
- `resources/views/livewire/transfers/create.blade.php`
- `resources/views/livewire/transfers/view.blade.php`
- `resources/views/transfers/dispatch-note.blade.php`

**6. Routes:**
- `GET /transfers` — TransferList
- `GET /transfers/create` — TransferCreate
- `GET /transfers/{request}` — TransferView
- `GET /transfers/{request}/dispatch-note` — printable dispatch note (policy `view`)

**7. Nav link:** "Transfers" gated by `@can('viewAny', \App\Modules\Transfers\Models\TransferRequest::class)`.

**8. Tests (`tests/Feature/Transfers/TransferUiTest.php`):**
- Supervisor can view the transfer list (own branch in/out); salesperson gets 403.
- Supervisor can create a transfer request via the UI; the cross-branch picker shows only `available` garments at the source branch; `purchase_cost`/supplier info not shown.
- Source supervisor can approve and reject (with reason); rejection releases reservations (garment back to `available`, visible in stock).
- Source supervisor can dispatch; dispatch note renders and prints; garments become `in_transit`.
- Destination supervisor sees incoming transfers in their list; cannot approve (wrong branch, 403).
- Salesperson cannot create/approve/dispatch (403).
- Online supervisor can create requests TO the online channel.
- No sensitive data (`purchase_cost`, supplier) in any transfer view.

### Acceptance criteria — Session 3.7
- [ ] Transfer list is branch-scoped and role-gated.
- [ ] Cross-branch stock picker shows only `available` garments at the source branch, with no supplier/cost data.
- [ ] Approve, reject (with reason + release), and dispatch work via the UI with correct state transitions.
- [ ] Dispatch note is printable.
- [ ] Role gating enforced (source vs destination supervisor; salesperson blocked).
- [ ] No sensitive data in transfer views.
- [ ] ~18 tests written.

---

## Session 3.8: Transfers — Receiving & Discrepancy Handling

**Goal:** Build the receiving UI: destination staff confirm received garments (by quantity entry or optional barcode scan), the system records discrepancies (shortage/excess), and missing garments are flagged `missing` (never silently received). Includes a transfer-history view.

**Estimated time:** 3-4 hours

### Prerequisites
- Session 3.7 complete (dispatch produces `in_transit` garments + a dispatched transfer request).

### Deliverables

**1. TransferReceive Livewire component (`app/Modules/Transfers/Http/Livewire/Transfers/TransferReceive.php`):**
- Loads a `dispatched` transfer request (policy `receive` + destination branch).
- Shows the dispatched items list (garment barcode/serial, product/size).
- Two receiving modes (toggle):
  - **Scan mode:** scan each garment barcode → marks it received; un-scanned remain missing.
  - **Quantity mode:** enter received count per product/size group; system marks the first N garments as received and the rest missing (per WF-7 step 2d "optionally scans individual barcodes, not mandatory" — when not scanned, missing-garment identification is by position/serial order; document this inference).
- On "Confirm Receipt": calls `TransferService::receive($request, $receiver, $receivedIds, $discrepancyData)`. Shows a summary: received count, missing count, excess count. If discrepancies, shows a discrepancy form (reason required for investigation). On success, redirects to TransferView showing final status (`received` / `discrepancy` / `partially_received`).
- Missing garments are explicitly listed and confirmed as `missing` — the UI must NOT offer "mark all as received" without per-garment confirmation (WF-7 step 3: "System never silently marks missing garments as received").

**2. TransferHistory / garment movement history integration:**
- Verify `MovementHistoryService::getGarmentHistory()` correctly describes `transfer_dispatch`/`transfer_receive`/`reservation`/`reservation_release` for garments that went through a transfer (already implemented in Phase 1 — verify in tests).
- Add a "Transfer history" tab/filter to the TransferList or a dedicated view showing a branch's transfer activity (in/out) for a date range (today-only for non-admin per ADR-018).

**3. Discrepancy resolution (minimal):**
- The `TransferDiscrepancy` rows created at receiving are `status = 'open'`. Full investigation/disposition is **Phase 7 (Stock Control)**. Phase 3 only records the discrepancy and surfaces it in the TransferView. Admin can view all discrepancies (read-only here); resolution UI is Phase 7.

**4. View:** `resources/views/livewire/transfers/receive.blade.php`.

**5. Route:** `GET /transfers/{request}/receive` — TransferReceive (policy `receive`).

**6. Tests (`tests/Feature/Transfers/TransferReceivingTest.php`):**
- Destination supervisor can open the receiving form for a `dispatched` transfer.
- Scan mode: scanning a garment marks it received; un-scanned → missing.
- Quantity mode: entering N received marks N received and the rest missing.
- Full receipt (all received) → request `received`, all garments `available` at destination, `current_branch_id = toBranch`.
- Shortage: 4 dispatched, 3 received → 1 garment `missing`, request `discrepancy`, `TransferDiscrepancy` row with `missing_garment_ids`.
- Excess: scanning a garment not in the request → flagged `excess` discrepancy.
- Missing garments are NOT marked received (assert no `transfer_receive` movement for missing; assert `missing_report` movement for missing).
- Source supervisor cannot receive (403 — wrong branch).
- Salesperson cannot receive (403).
- Transfer history (movement descriptions) renders correctly for a transferred garment.

### Acceptance criteria — Session 3.8
- [ ] Receiving works by scan or by quantity entry.
- [ ] Shortages record discrepancies and mark missing garments `missing` (never silently received).
- [ ] Excess garments are flagged.
- [ ] Full receipt moves garments to `available` at the destination branch.
- [ ] Receiving is destination-branch gated; salesperson blocked.
- [ ] Discrepancy rows are `open` (resolution deferred to Phase 7).
- [ ] ~16 tests written.

---

## Session 3.9: Cross-Branch Stock Visibility Browser & Reservation Board

**Goal:** Build the dedicated cross-branch stock-visibility browser (read-only, all branches, no supplier/cost) and verify/enhance the reservation board so it correctly shows transfer-backed reservations. This is a light session because the **backend already exists** from Phase 1; this session adds the dedicated UI deliverable and verifies performance.

**Estimated time:** 2-3 hours

### Prerequisites
- Session 3.6 complete (transfers create reservations that the board can display).
- Phase 1 `StockQueryService`, `StockProjectionService`, `ReservationBoard`, `StockAvailability`, `GarmentLookup` exist.

### Deliverables

**1. CrossBranchStockBrowser Livewire component (`app/Modules/Inventory/Http/Livewire/Stock/CrossBranchStockBrowser.php`):**
- A read-only all-branches stock view. Filters: product (search), size, branch (optional — default "all branches"), status (default `available`; also show `reserved` and `in_transit` counts). 
- Per branch per product/size: `available` count, `reserved` count, `in_transit` count (from `StockProjectionService::getStockSummary()` or a new aggregation query on `garment_current_stock`).
- **No supplier info, no `purchase_cost`** (verify `makeHidden('purchase_cost')` is applied; the existing `StockQueryService` already does this).
- `inventory.view.all_branches` required (all roles have it, per doc 05 §4.1 — cross-branch stock is read-only for everyone).
- Performance: single-branch query < 200ms, all-branches < 500ms (doc 06 §1.1).

**2. ReservationBoard enhancement (`app/Modules/Inventory/Http/Livewire/Stock/ReservationBoard.php`):**
- Verify the existing ReservationBoard shows per-branch reserved counts. Enhance to:
  - Show reservation source (`reservation_reference_type` = `transfer_request` → show request number; `online_order` → show order number — none yet in Phase 3, but the column is forward-compatible).
  - Filter by reference type.
  - Show the requesting branch (the transfer's `from_branch`) and destination (`to_branch`).
- After 3.6/3.7, the board now shows real transfer-backed reservations.

**3. GarmentLookup verification:** confirm `GarmentLookup` (barcode/serial lookup) returns in < 100ms with `current_status` + `current_branch` (no purchase_cost). Used by POS (3.3) and transfers (3.7). Add a performance assertion test.

**4. Route:** `GET /stock/cross-branch` — CrossBranchStockBrowser. (Existing `stock.availability` stays for single-branch; this is the all-branches sibling.)

**5. Nav:** add "All-Branch Stock" under the Stock nav, gated by `@can('viewAny', Garment::class)` (all roles) — or a dedicated `inventory.view.all_branches` check.

**6. Tests (`tests/Feature/Inventory/CrossBranchStockTest.php`):**
- CrossBranchStockBrowser loads for all roles (read-only).
- Shows available/reserved/in_transit counts per branch per product/size.
- `purchase_cost` and supplier info never appear in the rendered HTML.
- After a transfer request reserves garments at Branch B1, the board shows B1's reserved count increased; the reservation reference is the transfer request number.
- After dispatch, garments move from `reserved` to `in_transit`; board reflects it.
- After receiving, garments become `available` at destination; board reflects it.
- Performance: assert the all-branches query executes in < 500ms (use a seeded dataset of ~500 garments across 5 branches; measure via `DB::enableQueryLog()` query count + a wall-clock assert with a tolerance, OR assert query count <= N). At minimum, assert no N+1 (query count bounded).
- CrossBranchStockBrowser is read-only (no write actions exist).
- Salesperson (who can view all-branch stock) sees the same data as admin minus `purchase_cost`.

### Acceptance criteria — Session 3.9
- [ ] Cross-branch stock browser shows all branches, read-only, no supplier/cost.
- [ ] Reservation board shows transfer-backed reservations with the request number and from/to branches.
- [ ] Garment barcode lookup is < 100ms; cross-branch query < 500ms; no N+1.
- [ ] Stock counts update correctly through the transfer lifecycle (reserved → in_transit → available).
- [ ] All roles can view; no role can mutate stock from this view.
- [ ] ~12 tests written.

---

## Session 3.10: Customers — Customer History & Duplicate Merge

**Goal:** Build customer purchase history (linking `sale_invoices`) and the admin duplicate-merge workflow (WF-25): re-link `sale_invoices.customer_id` from a duplicate to a primary, mark the duplicate inactive, and audit. Returns re-linking is deferred to Phase 5.

**Estimated time:** 2-3 hours

### Prerequisites
- Sessions 3.1 (Customer model), 3.2-3.4 (sale_invoices exist) complete.
- Session 3.6 (transfers) complete so customer history can note in-transit/reserved garments if relevant (optional).

### Deliverables

**1. CustomerService::getPurchaseHistory (implement the 3.1 stub):**
- `getPurchaseHistory(Customer $customer, User $viewer): Collection` — returns the customer's `sale_invoices` (completed), each with items (garment, product, final_price) and payment summary. PII-scoped via `CustomerPolicy::view`. Ordered by `completed_at DESC`. Returns history only for customers the viewer may see.
- (Returns history is Phase 5 — return empty for now with a `@todo` note.)

**2. CustomerView enhancement (`app/Modules/Customers/Http/Livewire/Customers/CustomerView.php`):**
- Replace the 3.1 placeholder with the real purchase-history table: invoice number, date, branch, salesperson, item count, total amount, payment methods. Click an invoice → links to the sale view (read-only, policy-scoped).

**3. CustomerMerge Livewire component (`app/Modules/Customers/Http/Livewire/Customers/CustomerMerge.php`):**
- Admin-only (`customer.merge`). 
- Two customer pickers: primary + duplicate. Shows a side-by-side summary (phone, name, sale count, last sale).
- On confirm: `CustomerService::merge(Customer $primary, Customer $duplicate, User $admin)`:
  - `DB::transaction`: 
    - `sale_invoices` where `customer_id = duplicate.id` → update to `primary.id`.
    - (Returns/online_orders re-link deferred — Phase 5/4.)
    - `duplicate->update(['is_active' => false, 'merged_into_id' => primary.id])`.
    - Audit `customer.merge` with `previous_values`/`new_values` (which invoices moved, counts).
    - Return `$primary->fresh()`.
- After merge, the duplicate's view redirects to the primary with a "merged" banner.

**4. Duplicate suggestion:** in `CustomerList`, for admin, show a "Possible duplicates" section that flags customers with the same normalized phone or matching name+phone-prefix (simple heuristic; full fuzzy matching is out of scope).

**5. Views:**
- Update `resources/views/livewire/customers/view.blade.php` with the history table.
- `resources/views/livewire/customers/merge.blade.php`.

**6. Route:** `GET /customers/merge` — CustomerMerge (admin only).

**7. Tests (`tests/Feature/Customers/CustomerHistoryMergeTest.php`):**
- `getPurchaseHistory` returns the customer's completed sales with items + payments.
- A salesperson viewing another branch's customer's history gets 403 (PII scope).
- Admin can merge two customers; all `sale_invoices.customer_id` move from duplicate to primary; duplicate `is_active = false`, `merged_into_id` set.
- After merge, the duplicate's sales appear under the primary's history.
- Merged duplicate cannot be used in a new sale (POS `findOrCreateByPhone` returns the primary, not the inactive duplicate).
- Non-admin cannot merge (403).
- Merge is audited (`customer.merge`).
- History is today-only for non-admin sales? — No: per ADR-018, single-record lookup (a customer's own history) is allowed any date; aggregate customer reports are today-only. History of one customer = allowed any date for admin; for supervisor/salesperson, own-branch customers any date (operational lookup), other-branch 403.
- Empty history (new walk-in customer) renders gracefully.

### Acceptance criteria — Session 3.10
- [ ] Customer purchase history shows completed sales with items and payments.
- [ ] PII scope enforced on history (own-branch for supervisor/salesperson; online for online team; all for admin).
- [ ] Admin merge re-links `sale_invoices.customer_id`, marks duplicate inactive, audits.
- [ ] Merged duplicates are excluded from POS customer lookup (primary returned instead).
- [ ] Only admin can merge.
- [ ] ~14 tests written.

---

## Session 3.11: Phase 3 Integration & Sensitive-Data Hardening

**Goal:** End-to-end integration proving the full Phase 3 vertical slices work together: complete POS sale lifecycle, complete transfer lifecycle, concurrency, cross-branch stock without supplier/cost, the full permission matrix for all 5 roles, sensitive-data hiding, audit completeness, nav-link visibility, and receipt no-tax-line compliance. Mirrors `Phase1IntegrationTest.php` and `Phase2IntegrationTest.php`.

**Estimated time:** 3-4 hours

### Prerequisites
- Sessions 3.1–3.10 all complete.

### Deliverables

**1. `tests/Feature/Phase3IntegrationTest.php`** (Pest, `describe`/`it`, `beforeEach` seeds `RolePermissionSeeder` + `BranchSeeder` + `FiscalYearSeeder`; file-scope helpers `createReceivedGarmentsForIntegration(int $qty, Branch $branch)` returning `Collection<Garment>` available at a branch, and `createCustomerForIntegration(string $phone)`):

`describe` blocks:
1. **navigation — role-appropriate Phase 3 menu:** assert each of the 5 roles sees the correct nav links (POS for all selling roles; Transfers for supervisors/admin; Customers for all; Stock for all; Receipts/print for those who completed sales). Hit `route('dashboard')`, `assertSee`/`assertDontSee` on labels + `route()` URLs.
2. **full POS lifecycle — WF-3 end to end:** create product → set price (standard + minimum) → receive garments (reuse Phase 2 `ReceivingService`) → create customer → build cart → checkout with cash → assert invoice `completed`, garment `sold`, movement `sale`, customer linked, `sale_payments` row, `return_policy_snapshot` stored, `SaleCompleted` dispatched, audit `sale.complete` → print receipt (all 3 formats) → assert no "VAT"/"Tax" text → assert `purchase_cost` absent.
3. **POS concurrency — two cashiers, one garment:** seed one `available` garment; spawn two parallel `SaleService::complete()` calls with that garment in both carts; assert exactly one succeeds, one throws `GarmentNotAvailableException`; exactly one invoice; garment `sold`.
4. **below-minimum-price rejection end to end:** set minimum=900, standard=1000; attempt sale with discount 200 (final 800) → rejected with `BelowMinimumPriceException`; no invoice.
5. **multiple payment methods + short payment:** sale with cash+bkash totalling correctly succeeds; sale with total_paid < total_amount rejected.
6. **full transfer lifecycle — WF-6 + WF-7 end to end:** create request (reserve) → approve → dispatch (`in_transit`) → receive (`available` at destination) → assert movements `reservation`, `transfer_dispatch`, `transfer_receive`; assert garment `current_branch_id` changed; assert `TransferRequested/Dispatched/Received` dispatched; audit entries for each.
7. **transfer reservation blocks POS:** after creating a transfer request reserving garment G, a POS sale of G is rejected (`GarmentNotAvailableException`); after rejecting/cancelling the transfer, G is sellable again.
8. **transfer discrepancy:** dispatch 4, receive 3 → 1 garment `missing`, request `discrepancy`, `TransferDiscrepancy` row with `missing_garment_ids`; missing garment never has a `transfer_receive` movement.
9. **cross-branch stock visibility:** after transfers, `CrossBranchStockBrowser` shows correct available/reserved/in_transit counts per branch; `purchase_cost` and supplier info never in the rendered HTML for any role.
10. **permission matrix — all 5 roles:** for each role, assert which Phase 3 routes are 200 vs 403 (POS create/complete for selling roles; transfer create/approve/dispatch/receive for supervisors; customer create for all, edit/merge for admin only; stock view for all; receipt print for those with completed sales). Cover: `main_branch_admin` all 200; `branch_supervisor` transfer actions on own branch 200, others' branch 403, customer edit 403, customer merge 403; `salesperson` transfer actions 403, customer edit/merge 403, POS 200; `online_team`/`online_supervisor` online-channel transfer actions per scope.
11. **sensitive data — purchase cost & supplier hidden across all Phase 3 paths:** exercise POS UI, transfer views, cross-branch stock browser, customer views; assert `purchase_cost` key absent in every JSON/serialized response and not in any rendered Blade output for non-admin; admin sees it via `withPurchaseCost()`.
12. **sensitive data access is audit-logged:** exercise the `LogDataAccess` middleware path for any sensitive-data view; assert `audit_logs.action = 'sensitive_data.view'`, `category = 'security'`.
13. **audit completeness — all Phase 3 actions:** for each action (`customer.create`, `customer.update`, `customer.merge`, `sale.complete`, `inventory.movement` (type sale/transfer_dispatch/transfer_receive/reservation/reservation_release/missing_report), `transfer.request`, `transfer.approve`, `transfer.reject`, `transfer.dispatch`, `transfer.receive`, `transfer.cancel`) assert a corresponding `AuditLog` row with the correct inferred category (`customers`/`sales`/`inventory`/`transfers`).
14. **branch scope — sales and transfers:** a salesperson/supervisor at Branch B1 cannot sell or transfer a garment at Branch B2; cross-branch stock view is read-only and allowed.
15. **date restriction — ADR-018:** supervisor can view today's sales but not 3-days-ago sales (403 on the report route / not returned in their scoped query); single-customer lookup is allowed any date.
16. **fiscal-year boundary:** sales and transfers in the current fiscal year use `INV-YY-`/`TR-YY-` prefixes correctly; a closed fiscal year blocks new sales/transfers.
17. **receipt compliance — no tax line:** render all 3 receipt formats for a completed sale; assert absence of "VAT", "Tax", "ট্যাক্স", "GST" strings (ADR-010).

**2. Run `./vendor/bin/pint`** — zero linting errors.

**3. Run `php artisan test`** — full suite green (Phase 1 + Phase 2 + Phase 3). Expected total test count increase: ~250+ new tests across Phase 3 sessions.

**4. Update `docs/11-development-roadmap.md` Phase 3 status** to "Implementation complete" and update `docs/13-phase-2-session-breakdown.md`'s successor reference if applicable. Add a brief Phase 3 completion note.

**5. Update the Progress Tracker table at the top of this document** with the final commit hash for each session.

### Acceptance criteria — Session 3.11
- [ ] `Phase3IntegrationTest.php` passes all `describe` blocks (end-to-end POS, transfer lifecycle, concurrency, discrepancy, cross-branch stock, permission matrix for all 5 roles, sensitive-data hiding, audit completeness, branch scope, date restriction, fiscal-year boundary, no-tax-line receipt).
- [ ] `php artisan test` is fully green (Phase 1 + 2 + 3).
- [ ] `./vendor/bin/pint` clean.
- [ ] No `purchase_cost` or supplier info leaks through any Phase 3 path for non-admin.
- [ ] Concurrency test proves two-cashiers-one-garment safety.
- [ ] Reservation-blocks-POS guarantee proven by integration test.
- [ ] Receipts contain no tax line (ADR-010).
- [ ] ~50 integration tests written; Phase 3 ready for Phase 4 (Online Orders) to build on the transfer/reservation subsystem.

---

## Appendix A — Reference Documents

| # | Document | Phase 3 relevance |
|---|---|---|
| 01 | Business Requirements | Customer/POS/Transfer/stock-visibility requirements (§sections on Sales, Transfers, Customers). |
| 02 | Architecture | Module structure (Sales, Transfers, Customers modules), service-layer conventions, transaction/locking patterns (§6), stock projection (§7), events (§8.1), branch scoping (§9.4). |
| 03 | Database Design | Exact table definitions: `customers` (§3.16), `sale_invoices`/`sale_invoice_items`/`sale_payments` (§3.7), `transfer_requests`/`transfer_request_items`/`transfer_discrepancies` (§3.6), `garments`/`garment_current_stock`/`inventory_movements` (§3.5), `payment_methods` (§3.10), `return_policies` (§3.19). Garment status enum (11 values), movement type enum (19 values). |
| 04 | Inventory Workflow | WF-3 (POS online), WF-6 (transfer request/approval), WF-7 (dispatch/receive), WF-25 (customer merge). Status transitions per workflow. |
| 05 | Security Design | Permission tokens (`pos.*`, `transfer.*`, `customer.*`, `inventory.view.all_branches`, `reservation.release.manual`), permission matrix per role (§4.1), `SaleInvoicePolicy::view` example (§5.1), sensitive-data always-hidden list (§4.3), `CompleteSaleRequest` validation (§8.1), branch scope (§5.2). |
| 06 | Performance Strategy | POS barcode lookup < 100ms, sale completion < 500ms, single-branch stock < 200ms, all-branches < 500ms; indexes (`garments(barcode)`, `garments(current_status, current_branch_id)`, `garment_current_stock(current_status, current_branch_id)`, `sale_invoices(branch_id, completed_at)`, partial `idx_invoices_completed`); caching "never cache real-time stock"; pagination 25/50. |
| 07 | API Documentation | Endpoint shapes for future API (`POST /pos/sales`, `/transfers`, `/customers`, `/sync/*`); error shapes (`garment_not_available` 409, `below_minimum_price` 422). **Not built in Phase 3** (Livewire-first); referenced for contract consistency. |
| 08 | Testing Strategy | Pest conventions, concurrency test pattern (two sales, one succeeds), offline-sync tests (deferred to Phase 8), acceptance criteria AC-1..AC-23 (POS, transfer), test pyramid. CI 80% coverage for critical modules (Sales). |
| 10 | Architecture Decisions | ADR-003 (garment identity), ADR-004 (append-only ledger + projection), ADR-005 (offline POS — Phase 8), ADR-006 (transfer-based online fulfillment), ADR-007 (5-role RBAC), ADR-008 (no auto reservation expiry — manual release; reservation board needed), ADR-010 (no tax line on receipt), ADR-014 (single selling price; sale_type is a tag), ADR-017 (Spatie + branch scope), ADR-018 (search any date / reports today-only). |

## Appendix B — Open Design Decisions Resolved in This Plan

1. **Cart reservation (WF-3 step 3d "temporary reservation"):** in-memory only, no DB draft or TTL reservation. The persistent reservation is the transfer-request-backed garment status (Phase 3) / online-order-backed (Phase 4). POS carts do not reserve until `complete()` locks the rows. Matches ADR-008 (no auto reservation expiry).
2. **Invoice/transfer number generation:** advisory-lock pattern (`pg_advisory_xact_lock(9000001/9000002 + fy.id)`) via a `document_sequences` table, NOT count+1 — POS is high-concurrency (doc 06 §1.2).
3. **Missing garments at receiving:** set to `missing` status (via `missing_report` movement), flagged for Phase 7 investigation. Never silently received (WF-7 step 3).
4. **`return_policy_snapshot`:** Phase 3 snapshots a default policy from config (`return_period_days: 7`) since the `return_policies` table + management UI is Phase 5. The JSONB column is forward-compatible.
5. **`payment_methods` table ownership:** created in Phase 3 (Sales) because POS needs it immediately; Phase 6 (Cash) will own the cash-account ledger that posts against these methods. Documented as a cross-module hand-off.
6. **Cash ledger posting:** Phase 3 records `sale_payments` and dispatches `SaleCompleted`; Phase 6 adds the Cash listener that posts to `cash_transactions`. No cash tables in Phase 3.
7. **Offline POS columns:** `sale_invoices.is_offline_sale` + `offline_uuid` created nullable, unused in Phase 3 (forward-compat for Phase 8).
8. **`transfer_requests.online_order_id`:** nullable, no inline FK (deferred to Phase 4 when `online_orders` is created).
9. **Customer PII scope:** enforced in `CustomerService` + `CustomerPolicy` (not a global scope, since customers are not branch-owned); admin all, supervisor/salesperson own-branch-or-walk-in, online team online-only.
10. **API endpoints:** not built in Phase 3 (Livewire-first, matching Phase 1/2). Sync API deferred to Phase 8.

---

**End of Phase 3 Session Breakdown.** Implementation begins with Session 3.1 (Customer Management) once this plan is approved. Each session is committed separately; the Progress Tracker at the top is updated with each commit hash.
