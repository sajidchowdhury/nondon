# Phase 2: Purchasing & Barcode Generation — Session Breakdown
## Nondon ERP Development Plan

**Project:** Nondon
**Version:** 1.1
**Date:** 27 November 2026
**Status:** Planning complete — ready for implementation
**Phase:** 2 of 10 (Purchasing & Barcode Generation)
**Estimated duration:** 3-4 weeks (8 sessions)
**Depends on:** Phase 1 (Foundation) — COMPLETE

---

## Progress Tracker

| Session | Status | Commit |
|---|---|---|
| 2.1 Supplier Management | COMPLETE | de42522 |
| 2.2 Purchase Order Foundation | COMPLETE | 532cfdd |
| 2.3 Purchase Management UI & Confirmation | COMPLETE | e57ab69 |
| 2.4 Goods Receiving — Core Transaction | COMPLETE | 5ed1cf3 |
| 2.5 Goods Receiving UI & Edge Cases | COMPLETE | 92a9170 |
| 2.6 Barcode Label Generation | PENDING | — |
| 2.7 Label Printing UI & Printer Configuration | PENDING | — |
| 2.8 Phase 2 Integration & Sensitive Data Hardening | PENDING | — |

---

## 1. Purpose

This document splits Phase 2 (Purchasing & Barcode Generation) into 8 focused development sessions. Each session is a self-contained unit of work with clear deliverables, dependencies, and acceptance criteria.

**Principle: best solution, not quickest.** Each session is small enough to implement thoroughly — with proper architecture, complete tests, and careful review — without rushing to the next session. Where a workstream is complex (goods receiving, barcode printing), the core logic and the UI are split into separate sessions so that the business-critical transaction logic can be tested in isolation before a UI is layered on top.

Sessions are sequential. Each builds on the previous one. Do not start a session until the previous session is complete, tested, and committed.

---

## 2. Session Overview

| Session | Module | Focus | Estimated time | Depends on |
|---|---|---|---|---|
| 2.1 | Purchasing | Supplier Management | 3-4 hours | Phase 1 |
| 2.2 | Purchasing | Purchase Order Foundation (models, service, policy) | 3-4 hours | 2.1 |
| 2.3 | Purchasing | Purchase Management UI & Confirmation | 3-4 hours | 2.2 |
| 2.4 | Purchasing + Inventory | Goods Receiving — Core Transaction | 4-5 hours | 2.2, Phase 1 (GarmentIdentityService) |
| 2.5 | Purchasing | Goods Receiving UI & Edge Cases | 3-4 hours | 2.4, 2.3 |
| 2.6 | Purchasing | Barcode Label Generation (Code 128, label data) | 3-4 hours | 2.4 |
| 2.7 | Purchasing + System Settings | Label Printing UI & Printer Configuration | 3-4 hours | 2.6 |
| 2.8 | Integration | Phase 2 Integration & Sensitive Data Hardening | 2-3 hours | 2.1–2.7 |

**Total:** 8 sessions, approximately 24-32 hours of focused development.

### 2.1 Dependency graph

```
Phase 1 (COMPLETE)
    │  (GarmentIdentityService, FiscalYearService with advisory lock,
    │   Garment model, garment_current_stock, inventory_movements, Audit)
    │
    ▼
2.1 Supplier Management
    │
    ▼
2.2 Purchase Order Foundation
    │
    ├──► 2.3 Purchase Management UI & Confirmation
    │        │
    │        ▼
    └──► 2.4 Goods Receiving — Core Transaction ◄── integrates Phase 1 GarmentIdentityService
              │
              ▼
         2.5 Goods Receiving UI & Edge Cases
              │
              ▼
         2.6 Barcode Label Generation
              │
              ▼
         2.7 Label Printing UI & Printer Configuration
              │
              ▼
         2.8 Phase 2 Integration & Sensitive Data Hardening
```

### 2.2 Why goods receiving is split into two sessions (2.4 + 2.5)

Goods receiving (workflow WF-1) is the most complex and risk-critical operation in Phase 2. It involves:
- Advisory locking for fiscal-year serial generation.
- An atomic multi-table transaction (garments + garment_current_stock + inventory_movements + goods_receipts + purchase_items + purchases).
- Integration with the existing Phase 1 `GarmentIdentityService`.
- Per-garment AND per-receipt audit logging.
- A dispatched `PurchaseReceived` event.

Splitting the core transaction logic (Session 2.4) from the UI and edge-case handling (Session 2.5) ensures the transaction can be unit-tested and verified in isolation — including the concurrency test for serial generation — before the Livewire receiving form is built on top. This matches the testing strategy's requirement that serial generation be validated with 100-concurrent-call tests.

### 2.3 Why barcode label printing is split into two sessions (2.6 + 2.7)

Barcode label printing has two distinct concerns:
- **Generation** (Session 2.6): choosing the PHP barcode library, rendering Code 128 barcodes, assembling label data (barcode + serial + product name + size + optional price), defining the four label formats. This is pure backend logic that can be unit-tested.
- **Printing transport** (Session 2.7): the Livewire UI for selecting garments and formats, browser CSS print media (`@page` size directives), and printer configuration in System Settings. This is frontend + integration work that is harder to unit-test.

Splitting them lets the label data contract be tested (does the barcode render? does the label fit the format?) before the printing transport is built.

### 2.4 What Phase 1 already provides (do NOT rebuild)

Phase 1 (Sessions 1.9, 1.10, 1.11) already built the following infrastructure that Phase 2 builds on top of:

| Component | Location | What it does |
|---|---|---|
| `GarmentIdentityService` | `app/Modules/Inventory/Services/GarmentIdentityService.php` | Creates a garment with UUID (`internal_id`), barcode (`NND-{serial}`), and fiscal-year serial (`YY-NNNNN`). Creates the `garment_current_stock` projection in the same transaction. |
| `FiscalYearService::generateNextSerial()` | `app/Modules/Organization/Services/FiscalYearService.php` | Increments `current_serial_sequence` and returns the formatted serial (`25-01209`). Uses `pg_advisory_xact_lock` for concurrency safety. |
| `Garment` model | `app/Modules/Inventory/Models/Garment.php` | Full model with status constants (11 values), scopes, `purchase_cost` hidden via `$hidden`, `withPurchaseCost()` accessor pattern. |
| `garments` table | migration | Includes `purchase_id`, `purchase_item_id`, `goods_receipt_id` FK columns (nullable) and `purchase_cost` column. |
| `garment_current_stock` table | migration | Projection table with status/branch/location. |
| `inventory_movements` table | migration | Append-only ledger. `movement_type` includes `goods_receipt` and `return_to_supplier`. |
| `InventoryMovementService` | `app/Modules/Inventory/Services/InventoryMovementService.php` | Records movements and updates the projection. |
| Audit infrastructure | `app/Modules/Audit/` | `AuditService::log()`, `Auditable` trait, `AuditableEvent`, append-only `AuditLog` model. |

**Phase 2 gaps to fill:**
- `GarmentIdentityService::createGarment()` does NOT currently set `purchase_id`, `purchase_item_id`, `goods_receipt_id`, or `purchase_cost` from a purchase context.
- `GarmentIdentityService::createGarment()` does NOT currently create an `inventory_movements` record (type `goods_receipt`).
- The `app/Modules/Purchasing/` module does not exist yet.
- No `suppliers`, `purchases`, `purchase_items`, or `goods_receipts` tables exist yet.
- No barcode label rendering or printing infrastructure exists.

### 2.5 Session completion criteria

A session is considered complete when:
1. All deliverables are implemented.
2. All acceptance criteria tests pass.
3. Code follows Pint style (no linting errors).
4. No existing tests are broken (`php artisan test` stays green).
5. Changes are committed and pushed to GitHub.
6. The next session's dependencies are satisfied.

---

## Session 2.1: Supplier Management

**Goal:** Build the supplier management CRUD module so Main Branch Admin can create, view, edit, activate/deactivate, and search suppliers. Supplier information is sensitive data — hidden from all non-admin roles.

**Estimated time:** 3-4 hours

### Prerequisites
- Phase 1 complete (User model, policies, audit infrastructure, Branch model).
- The `suppliers` table does not yet exist (this session creates it).

### Deliverables

**1. Migration — `suppliers` table (`database/migrations/2025_01_01_000023_create_suppliers_table.php`):**
```
id BIGSERIAL PRIMARY KEY
code VARCHAR(50) UNIQUE NOT NULL          -- e.g., "SUP-001"
name VARCHAR(255) NOT NULL
contact_person VARCHAR(255) NULL
phone VARCHAR(50) NULL
email VARCHAR(255) NULL
address TEXT NULL
outstanding_balance NUMERIC(12,2) DEFAULT 0   -- read-only in Phase 2; mutated in Phase 6
is_active BOOLEAN DEFAULT TRUE
created_by BIGINT REFERENCES users(id)
created_at TIMESTAMPTZ DEFAULT NOW()
updated_at TIMESTAMPTZ DEFAULT NOW()
```
Indexes: `UNIQUE(code)`. No soft deletes — suppliers are deactivated via `is_active`.

**2. Supplier model (`app/Modules/Purchasing/Models/Supplier.php`):**
- Fillable: `code`, `name`, `contact_person`, `phone`, `email`, `address`, `outstanding_balance`, `is_active`, `created_by`.
- Casts: `outstanding_balance` → `decimal:2`, `is_active` → `boolean`.
- Scopes: `active()`, `inactive()`, `search(string $term)`.
- Relationships: `purchases()` (hasMany), `creator()` (belongsTo User).
- **Sensitive data:** `contact_person`, `phone`, `email`, `address` are hidden from non-admin serialization. Implement via a `SupplierResource` (API Resource) that strips these fields unless `$user->can('supplier.manage')`, AND a model-level accessor pattern mirroring the Garment `withPurchaseCost()` approach.
- Auditable trait applied (supplier create/update/deactivate are audited).

**3. SupplierService (`app/Modules/Purchasing/Services/SupplierService.php`):**
- `create(array $data): Supplier` — admin creates supplier with auto-generated `code` (or manual). Sets `created_by`.
- `update(Supplier $supplier, array $data): Supplier` — admin edits supplier details.
- `activate(Supplier $supplier): Supplier` — set `is_active = true`.
- `deactivate(Supplier $supplier): Supplier` — set `is_active = false` (cannot deactivate if outstanding_balance != 0).
- `search(string $term): Collection` — search by code, name, phone.
- `getOutstandingBalance(Supplier $supplier): float` — returns current balance (read-only; Phase 6 mutates).
- All methods audit the action (`supplier.create`, `supplier.update`, `supplier.activate`, `supplier.deactivate`).

**4. SupplierPolicy (`app/Modules/Purchasing/Policies/SupplierPolicy.php`):**
- `viewAny(User $user): bool` — admin only.
- `view(User $user, Supplier $supplier): bool` — admin only.
- `create(User $user): bool` — admin only.
- `update(User $user, Supplier $supplier): bool` — admin only.
- `activate(User $user, Supplier $supplier): bool` — admin only.
- `deactivate(User $user, Supplier $supplier): bool` — admin only.

**5. Livewire components:**
- `SupplierList` — paginated table with search, status filter. Shows code, name, phone (admin only), outstanding balance (admin only), status.
- `SupplierCreate` — form: code (auto-suggest), name, contact_person, phone, email, address.
- `SupplierEdit` — edit form + activate/deactivate button.
- `SupplierView` — read-only detail with purchase history summary (count + total received garments).

**6. Views:**
- `resources/views/livewire/purchasing/suppliers/list.blade.php`
- `resources/views/livewire/purchasing/suppliers/create.blade.php`
- `resources/views/livewire/purchasing/suppliers/edit.blade.php`
- `resources/views/livewire/purchasing/suppliers/view.blade.php`

**7. Routes:**
- `GET /suppliers` — SupplierList (admin only)
- `GET /suppliers/create` — SupplierCreate (admin only)
- `GET /suppliers/{supplier}` — SupplierView (admin only)
- `GET /suppliers/{supplier}/edit` — SupplierEdit (admin only)

**8. Permission seeding:**
- Add `supplier.manage` permission to the `purchasing` module group in the permission seeder.
- Assign `supplier.manage` to `main_branch_admin` role only.

**9. Tests (`tests/Feature/Purchasing/SupplierManagementTest.php`):**
- Admin can view supplier list
- Admin can create supplier with valid data
- Cannot create supplier with duplicate code
- Admin can edit supplier details
- Admin can activate/deactivate supplier
- Cannot deactivate supplier with non-zero outstanding balance
- Admin can search suppliers by code, name, phone
- Supplier list is paginated
- Supervisor cannot access supplier management (403)
- Salesperson cannot access supplier management (403)
- Online team/supervisor cannot access supplier management (403)
- Supplier contact details (phone, email, address) are never exposed to non-admin roles
- Supplier creation is audited
- Supplier update is audited
- Supplier deactivate is audited

### Acceptance criteria — Session 2.1 (COMPLETE)
- [x] Admin can create, view, edit, activate/deactivate suppliers through the UI
- [x] Supplier code is unique
- [x] Suppliers with outstanding balance cannot be deactivated
- [x] All non-admin roles get 403 on all supplier routes
- [x] Supplier contact information (phone, email, address) is never visible to non-admin roles in any response or view
- [x] All supplier actions are audited
- [x] 30 tests written (all acceptance criteria covered)

---

## Session 2.2: Purchase Order Foundation

**Goal:** Build the purchase order data model, service layer, and policy — so admin can create draft purchases with line items, confirm them, and cancel them. This session builds the backend foundation; the full management UI is Session 2.3.

**Estimated time:** 3-4 hours

### Prerequisites
- Session 2.1 complete (Supplier model and SupplierService exist).

### Deliverables

**1. Migration — `purchases` table (`database/migrations/2025_01_01_000024_create_purchases_table.php`):**
```
id BIGSERIAL PRIMARY KEY
purchase_number VARCHAR(50) UNIQUE NOT NULL    -- e.g., "PO-25-00001"
supplier_id BIGINT NOT NULL REFERENCES suppliers(id)
fiscal_year_id BIGINT NOT NULL REFERENCES fiscal_years(id)
purchase_date DATE NOT NULL
expected_delivery_date DATE NULL
status VARCHAR(20) NOT NULL CHECK (status IN
    ('draft', 'confirmed', 'received', 'partially_received', 'cancelled'))
total_amount NUMERIC(12,2) NULL     -- total at selling price (optional, for reference)
total_cost NUMERIC(12,2) NULL       -- total at purchase cost (admin only)
notes TEXT NULL
created_by BIGINT NOT NULL REFERENCES users(id)
confirmed_at TIMESTAMPTZ NULL
confirmed_by BIGINT REFERENCES users(id)
created_at TIMESTAMPTZ DEFAULT NOW()
updated_at TIMESTAMPTZ DEFAULT NOW()
```
Indexes: `INDEX(supplier_id, purchase_date)`, `INDEX(status)`.

**2. Migration — `purchase_items` table (`database/migrations/2025_01_01_000025_create_purchase_items_table.php`):**
```
id BIGSERIAL PRIMARY KEY
purchase_id BIGINT NOT NULL REFERENCES purchases(id) ON DELETE CASCADE
product_id BIGINT NOT NULL REFERENCES products(id)
product_size_id BIGINT REFERENCES product_sizes(id) NULL
ordered_quantity INT NOT NULL
received_quantity INT DEFAULT 0
unit_cost NUMERIC(12,2) NOT NULL
created_at TIMESTAMPTZ DEFAULT NOW()
```
Indexes: `INDEX(purchase_id)`, `INDEX(product_id)`.

**3. Purchase model (`app/Modules/Purchasing/Models/Purchase.php`):**
- Fillable: all columns except `confirmed_at`, `confirmed_by` (set by service).
- Casts: `purchase_date` → `date`, `expected_delivery_date` → `date`, `total_cost` → `decimal:2`, `total_amount` → `decimal:2`, `confirmed_at` → `datetime`.
- Status constants: `STATUS_DRAFT`, `STATUS_CONFIRMED`, `STATUS_RECEIVED`, `STATUS_PARTIALLY_RECEIVED`, `STATUS_CANCELLED`.
- Scopes: `draft()`, `confirmed()`, `received()`, `partiallyReceived()`, `cancelled()`, `forSupplier($id)`, `forFiscalYear($id)`, `search(string $term)`.
- Relationships: `supplier()` (belongsTo), `fiscalYear()` (belongsTo), `items()` (hasMany PurchaseItem), `creator()` (belongsTo User), `confirmer()` (belongsTo User), `goodsReceipts()` (hasMany).
- Helper methods: `isDraft()`, `isConfirmed()`, `isReceived()`, `isPartiallyReceived()`, `isCancelled()`, `canBeConfirmed()`, `canBeCancelled()`, `canBeReceived()`.
- **Sensitive data:** `total_cost` is hidden from non-admin serialization (via `$hidden` + `withTotalCost()` accessor, mirroring the Garment pattern).
- Auditable trait applied.

**4. PurchaseItem model (`app/Modules/Purchasing/Models/PurchaseItem.php`):**
- Fillable: all columns.
- Casts: `unit_cost` → `decimal:2`, `ordered_quantity` → `integer`, `received_quantity` → `integer`.
- Relationships: `purchase()` (belongsTo), `product()` (belongsTo), `productSize()` (belongsTo).
- Helper methods: `isFullyReceived()` (received >= ordered), `remainingQuantity()` (ordered - received).
- **Sensitive data:** `unit_cost` hidden from non-admin (same pattern).
- Auditable trait applied.

**5. PurchasingService (`app/Modules/Purchasing/Services/PurchasingService.php`):**
- `createPurchase(Supplier $supplier, FiscalYear $fiscalYear, array $items, array $options = []): Purchase`
  - Creates a draft purchase with auto-generated `purchase_number` (format: `PO-{YY}-{NNNNN}`, sequence per fiscal year — use a simple count+1 or a dedicated counter; document the choice).
  - Each item: `product_id`, `product_size_id` (nullable), `ordered_quantity`, `unit_cost`.
  - Validates: supplier exists and is active; fiscal year is not closed; products exist and are active; quantities > 0; unit_cost >= 0.
  - Computes `total_cost` = sum(item.ordered_quantity * item.unit_cost).
  - Audits: `purchase.create`.
- `updateDraftPurchase(Purchase $purchase, array $data): Purchase`
  - Only allowed when status = `draft`. Edits line items, dates, notes.
- `confirmPurchase(Purchase $purchase): Purchase`
  - Only allowed when status = `draft`. Sets status = `confirmed`, `confirmed_at` = now, `confirmed_by` = current user.
  - Audits: `purchase.confirm`.
- `cancelPurchase(Purchase $purchase, string $reason): Purchase`
  - Allowed when status in (`draft`, `confirmed`). Sets status = `cancelled`. Records reason in notes.
  - Audits: `purchase.cancel`.
- `getPurchaseList(array $filters = []): LengthAwarePaginator` — paginated list with filters (supplier, status, date range, fiscal year).
- `getPurchaseDetail(Purchase $purchase): Purchase` — loads items, supplier, goods receipts.
- All methods are wrapped in DB transactions where multi-step.

**6. PurchasePolicy (`app/Modules/Purchasing/Policies/PurchasePolicy.php`):**
- `viewAny(User $user): bool` — admin only.
- `view(User $user, Purchase $purchase): bool` — admin only.
- `create(User $user): bool` — admin only.
- `update(User $user, Purchase $purchase): bool` — admin only, AND purchase must be draft.
- `confirm(User $user, Purchase $purchase): bool` — admin only, AND purchase must be draft.
- `cancel(User $user, Purchase $purchase): bool` — admin only.
- `receive(User $user, Purchase $purchase): bool` — admin + branch supervisor (receiving staff). Purchase must be confirmed or partially_received.
- `viewCost(User $user, Purchase $purchase): bool` — admin only.

**7. Factories:**
- `SupplierFactory` (if not created in 2.1) — code, name, contact_person, phone, is_active.
- `PurchaseFactory` — supplier_id, fiscal_year_id, purchase_number, purchase_date, status.
- `PurchaseItemFactory` — purchase_id, product_id, product_size_id, ordered_quantity, unit_cost.

**8. Permission seeding:**
- Add `purchase.create`, `purchase.receive` permissions to the `purchasing` module group.
- Assign `purchase.create` to `main_branch_admin` only.
- Assign `purchase.receive` to `main_branch_admin` + `branch_supervisor`.

**9. Tests (`tests/Feature/Purchasing/PurchaseFoundationTest.php`):**
- Admin can create a draft purchase with line items
- Purchase number is auto-generated and unique
- Cannot create purchase with inactive supplier
- Cannot create purchase in a closed fiscal year
- Cannot create purchase with zero quantity items
- total_cost is computed correctly from line items
- Admin can update a draft purchase
- Cannot update a confirmed purchase
- Admin can confirm a draft purchase
- Confirming sets confirmed_at and confirmed_by
- Cannot confirm an already-confirmed purchase
- Admin can cancel a draft or confirmed purchase
- Cannot cancel a received purchase
- Purchase list is paginated and filterable
- Purchase cost (total_cost, unit_cost) is hidden from non-admin serialization
- Supervisor cannot create/confirm/cancel purchases (403)
- Supervisor has purchase.receive permission
- Salesperson has no purchase permissions (403)
- Purchase creation is audited
- Purchase confirmation is audited
- Purchase cancellation is audited

### Acceptance criteria — Session 2.2 (COMPLETE)
- [x] Purchase and PurchaseItem models exist with correct schema and relationships
- [x] PurchasingService supports create/update/confirm/cancel with proper status transitions
- [x] Purchase status state machine: draft → confirmed → received/partially_received; draft/confirmed → cancelled
- [x] Purchase cost data (total_cost, unit_cost) is never in non-admin serialization
- [x] Only admin can create/confirm/cancel purchases; admin + supervisor can receive
- [x] All purchase actions are audited
- [x] 38 tests written (all acceptance criteria covered)

---

## Session 2.3: Purchase Management UI & Confirmation

**Goal:** Build the Livewire UI for purchase management — list, create (with dynamic line items), edit (draft only), view, confirm, and cancel — so admin can manage the full purchase lifecycle through the interface.

**Estimated time:** 3-4 hours

### Prerequisites
- Session 2.2 complete (PurchasingService, PurchasePolicy, models exist).

### Deliverables

**1. Livewire components:**
- `PurchaseList` — paginated table with filters: supplier, status, fiscal year, date range. Columns: purchase_number, supplier name, date, status badge, total items, total cost (admin only). Click row → view.
- `PurchaseCreate` — multi-section form:
  - Header: supplier select, fiscal year select (default = active), purchase date, expected delivery date, notes.
  - Line items: dynamic repeatable rows — product select, size select (optional, filtered by product), ordered quantity, unit cost. Add/remove row buttons. Auto-computes line total and grand total.
  - Validation: at least 1 line item, quantities > 0, unit_cost >= 0.
  - Save as draft button.
- `PurchaseEdit` — same form as create, but only editable when status = draft. Shows status badge. Confirm and Cancel action buttons (conditionally visible by status and permission).
- `PurchaseView` — read-only detail: header info, line items table (ordered vs received quantities), goods receipts summary (empty until Session 2.4), audit trail link.

**2. Views:**
- `resources/views/livewire/purchasing/purchases/list.blade.php`
- `resources/views/livewire/purchasing/purchases/create.blade.php`
- `resources/views/livewire/purchasing/purchases/edit.blade.php`
- `resources/views/livewire/purchasing/purchases/view.blade.php`

**3. Routes:**
- `GET /purchases` — PurchaseList (admin only)
- `GET /purchases/create` — PurchaseCreate (admin only)
- `GET /purchases/{purchase}` — PurchaseView (admin only)
- `GET /purchases/{purchase}/edit` — PurchaseEdit (admin only, draft only)

**4. Navigation:**
- Add "Purchases" menu item to admin navigation (under a "Purchasing" section).
- Add "Suppliers" menu item (from Session 2.1) if not already added.

**5. Cost visibility in UI:**
- `unit_cost`, `total_cost`, `total_amount` columns/fields are only rendered in Blade when `$user->can('supplier.manage')` (admin).
- Non-admin users never see these fields — not even in the page source.

**6. Tests (`tests/Feature/Purchasing/PurchaseManagementUITest.php`):**
- Admin can view purchase list
- Admin can view create form
- Admin can create a purchase with multiple line items via the UI
- Form rejects submission with zero line items
- Form rejects submission with invalid product
- Admin can view a purchase detail
- Admin can edit a draft purchase
- Admin cannot edit a confirmed purchase (edit button hidden / route returns 403)
- Admin can confirm a draft purchase via UI button
- Admin can cancel a purchase via UI button with reason
- Purchase list filters work (by supplier, status, fiscal year)
- Purchase cost fields are not visible in the HTML for non-admin roles
- Supervisor cannot access purchase management routes (403)
- Salesperson cannot access purchase management routes (403)
- Purchase list shows correct status badges

### Acceptance criteria — Session 2.3 (COMPLETE)
- [x] Admin can create a purchase with dynamic line items through the UI
- [x] Admin can confirm and cancel purchases through the UI
- [x] Only draft purchases are editable
- [x] Purchase cost fields are not rendered for non-admin roles
- [x] Purchase list is filterable and paginated
- [x] All UI actions are protected by policies
- [x] 20 tests written (all acceptance criteria covered)

---

## Session 2.4: Goods Receiving — Core Transaction

**Goal:** Implement the goods receiving core transaction (workflow WF-1): when a confirmed purchase is received, the system generates garment identities, creates goods receipt records, records inventory movements, and updates purchase status — all inside a single atomic transaction with advisory locking. This session builds the service-layer logic and tests it thoroughly, including concurrency. No UI in this session (that is Session 2.5).

**Estimated time:** 4-5 hours (this is the hardest session in Phase 2)

### Prerequisites
- Session 2.2 complete (PurchasingService, Purchase, PurchaseItem exist).
- Phase 1 `GarmentIdentityService` exists and handles UUID/barcode/serial generation with advisory locking.
- Phase 1 `InventoryMovementService` exists.

### Deliverables

**1. Migration — `goods_receipts` table (`database/migrations/2025_01_01_000026_create_goods_receipts_table.php`):**
```
id BIGSERIAL PRIMARY KEY
purchase_id BIGINT NOT NULL REFERENCES purchases(id)
receipt_number VARCHAR(50) UNIQUE NOT NULL    -- e.g., "GR-25-00001"
received_date DATE NOT NULL
received_by BIGINT NOT NULL REFERENCES users(id)
total_received_quantity INT NOT NULL
notes TEXT NULL
created_at TIMESTAMPTZ DEFAULT NOW()
```
Indexes: `INDEX(purchase_id)`, `UNIQUE(receipt_number)`.

**2. GoodsReceipt model (`app/Modules/Purchasing/Models/GoodsReceipt.php`):**
- Fillable: `purchase_id`, `receipt_number`, `received_date`, `received_by`, `total_received_quantity`, `notes`.
- Casts: `received_date` → `date`, `total_received_quantity` → `integer`.
- Relationships: `purchase()` (belongsTo), `receiver()` (belongsTo User), `garments()` (hasMany Garment).
- Scopes: `forPurchase($id)`, `search(string $term)`.
- Auditable trait applied.

**3. Extend GarmentIdentityService (or add a purchase-context method):**
- Add a method `createGarmentForReceipt()` OR extend `createGarment()` to accept optional `purchase_id`, `purchase_item_id`, `goods_receipt_id`, and `purchase_cost` parameters.
- The garment record must be created with these FKs set when called from the receiving flow.
- **Design decision:** Prefer extending the existing `createGarment()` signature with optional purchase context parameters, so the method serves both manual (Phase 1 testing) and purchase-linked (Phase 2) creation. Document this in the method docblock.

**4. ReceivingService (`app/Modules/Purchasing/Services/ReceivingService.php`):**
- `receiveGoods(Purchase $purchase, array $receivedQuantities, User $receivedBy, ?string $notes = null): GoodsReceipt`
  - `$receivedQuantities` = `[purchase_item_id => received_qty]`.
  - **Transaction flow (WF-1 step 5):**
    1. Begin DB transaction.
    2. Validate: purchase status is `confirmed` or `partially_received`; each received qty <= remaining qty for that line (excess flagged, not auto-accepted); received_by has `purchase.receive` permission.
    3. For each purchase item with received qty > 0:
       a. For each garment (1 to received_qty):
          - Call `GarmentIdentityService::createGarmentForReceipt()` with: product, size, fiscal year (from purchase), branch = Main Branch, purchase_cost = item.unit_cost, purchase_id, purchase_item_id.
          - This generates UUID + barcode + serial (advisory lock inside FiscalYearService) and creates the garment + garment_current_stock projection.
          - Create `inventory_movements` record: `movement_type = 'goods_receipt'`, `to_branch_id = Main Branch`, `to_status = 'available'`, `reference_type = 'purchase'`, `reference_id = purchase_id`, `performed_by = receivedBy`.
          - Link the garment to the goods_receipt (set `goods_receipt_id` after the receipt record is created — see below).
       b. Update `purchase_item.received_quantity += received_qty`.
    4. Create `goods_receipts` record (receipt_number auto-generated: `GR-{YY}-{NNNNN}`).
    5. Update all garment records created in this receipt to set `goods_receipt_id` (bulk update within transaction).
    6. Update `purchase.status`: if all items fully received → `received`; if some remain → `partially_received`.
    7. Compute `goods_receipts.total_received_quantity`.
    8. Audit: `purchase.receive` per garment AND `purchase.receive` per receipt (two audit entries per garment — or one per garment + one per receipt; document the choice and be consistent).
    9. Dispatch `PurchaseReceived` event.
    10. Commit transaction (releases advisory lock).
  - **Error handling:** If any step fails, the entire transaction rolls back — no partial garment identities, no partial movements, no partial receipt.
- `getGoodsReceiptsForPurchase(Purchase $purchase): Collection`
- `getGoodsReceiptDetail(GoodsReceipt $receipt): GoodsReceipt` — loads garments, purchase, receiver.

**5. PurchaseReceived event (`app/Modules/Purchasing/Events/PurchaseReceived.php`):**
- Dispatchable event carrying: `Purchase`, `GoodsReceipt`, `User` (receiver).
- Listeners (registered in `PurchasingServiceProvider` or `EventServiceProvider`):
  - `AuditListener` — writes a summary audit entry (the per-garment audit is done in the transaction; this listener writes a receipt-level summary if not already done).
  - (Future phases may add listeners for notifications, stock distribution suggestions, etc.)

**6. Receipt number generation:**
- Format: `GR-{YY}-{NNNNN}` where YY = fiscal year prefix, NNNNN = sequential per fiscal year.
- Use the same advisory-lock pattern as fiscal-year serials, OR a separate counter column on `fiscal_years` (e.g., `current_receipt_sequence`). Document the choice.
- Alternative simpler approach: `GR-{purchase_number}-{sequence}` (unique within a purchase). Choose whichever is cleaner; document the decision.

**7. Concurrency safety:**
- The advisory lock in `FiscalYearService::generateNextSerial()` (Phase 1, using `pg_advisory_xact_lock`) already protects serial generation.
- The receiving transaction wraps all garment creation in a single DB transaction, so concurrent receives on the same purchase are serialized by the transaction + advisory lock.
- The `UNIQUE(fiscal_year_id, fiscal_year_serial)` constraint on `garments` is the backstop.

**8. Tests (`tests/Feature/Purchasing/GoodsReceivingCoreTest.php`):**
- Can receive a fully-confirmed purchase (all items received)
- Receiving creates the correct number of garment records
- Each garment has a valid UUID, barcode, and fiscal-year serial
- Barcode format is `NND-{serial}` (e.g., `NND-25-00001`)
- Serial format is `YY-NNNNN` (e.g., `25-00001`) and increments sequentially
- Each garment is linked to purchase_id, purchase_item_id, goods_receipt_id
- Each garment has purchase_cost set from the purchase item's unit_cost
- Each garment has current_status = available at Main Branch
- garment_current_stock projection is created for each garment
- inventory_movements record (type goods_receipt) is created for each garment
- Purchase status transitions to `received` when fully received
- Purchase status transitions to `partially_received` when partially received
- Can receive a partially_received purchase again (second receipt)
- goods_receipts record is created with correct total_received_quantity
- receipt_number is unique
- Receiving a draft purchase throws an error (must be confirmed first)
- Receiving a cancelled purchase throws an error
- Receiving more than ordered quantity is flagged (not auto-accepted)
- If transaction fails partway, no garments/projections/movements are created (rollback)
- Receiving is audited (per garment and per receipt)
- PurchaseReceived event is dispatched
- **Concurrency test:** 100 serials generated concurrently are all unique (validate advisory lock)
- Receiving requires `purchase.receive` permission (admin + supervisor)
- Salesperson cannot receive goods (403)
- Receiving only happens at Main Branch (received_by must be Main Branch user)

### Acceptance criteria — Session 2.4 (COMPLETE)
- [x] Full WF-1 goods receiving transaction is implemented and atomic
- [x] Garment identities (UUID, barcode, serial) are generated correctly during receiving
- [x] Advisory locking prevents duplicate serials under concurrency (100-serial test passes)
- [x] Each received garment has garment + projection + movement records created in one transaction
- [x] Purchase status transitions correctly (confirmed → received or partially_received)
- [x] Partial receipts are supported (multiple receipts per purchase)
- [x] Excess quantity is flagged, not auto-accepted
- [x] Transaction rollback on failure leaves no partial data
- [x] Receiving is audited per garment and per receipt
- [x] PurchaseReceived event is dispatched
- [x] Only admin + supervisor (with purchase.receive) can receive; salesperson cannot
- [x] 32 tests written (all acceptance criteria + concurrency)

---

## Session 2.5: Goods Receiving UI & Edge Cases

**Goal:** Build the Livewire UI for goods receiving — the receiving form where staff enter received quantities per line item and click "Receive & Generate Identities" — plus handling edge cases: partial receipts, excess quantity flagging, and receipt list/detail views.

**Estimated time:** 3-4 hours

### Prerequisites
- Session 2.4 complete (ReceivingService exists).
- Session 2.3 complete (purchase view exists to link from).

### Deliverables

**1. Livewire components:**
- `ReceiveGoods` — the receiving form:
  - Loaded from a confirmed/partially_received purchase (via route `/purchases/{purchase}/receive`).
  - Shows purchase header (supplier, date, status) and line items table.
  - Each line item row: product name, size, ordered qty, received-so-far qty, remaining qty, input field for "received now" qty.
  - "Receive & Generate Identities" button → calls `ReceivingService::receiveGoods()`.
  - Notes field.
  - On success: redirect to goods receipt detail page showing generated garment identities + print labels link (Session 2.7).
- `GoodsReceiptList` — paginated list of goods receipts with filters (purchase, date, received_by).
- `GoodsReceiptView` — detail view: receipt header, garment table (barcode, serial, product, size, cost [admin only]), link to print labels.

**2. Views:**
- `resources/views/livewire/purchasing/receiving/form.blade.php`
- `resources/views/livewire/purchasing/receiving/list.blade.php`
- `resources/views/livewire/purchasing/receiving/view.blade.php`

**3. Routes:**
- `GET /purchases/{purchase}/receive` — ReceiveGoods form (admin + supervisor with purchase.receive)
- `GET /goods-receipts` — GoodsReceiptList (admin only)
- `GET /goods-receipts/{receipt}` — GoodsReceiptView (admin only)

**4. Edge case handling in UI:**
- **Excess quantity:** if user enters received qty > remaining qty, show a warning and disable the receive button until corrected (or require explicit admin override — document the choice).
- **Partial receipt:** after a successful partial receipt, show a confirmation that the purchase is now `partially_received` and offer to receive the remaining items later.
- **Already received:** disable the receive button if purchase status is `received` or `cancelled` or `draft`.
- **Permission:** only show the "Receive" button to users with `purchase.receive` permission.

**5. Tests (`tests/Feature/Purchasing/GoodsReceivingUITest.php`):**
- Admin can view the receiving form for a confirmed purchase
- Receiving form shows correct line items with ordered/received/remaining quantities
- Admin can enter received quantities and submit
- After receiving, redirect to goods receipt detail showing generated garments
- Supervisor with purchase.receive can access the receiving form
- Salesperson cannot access the receiving form (403)
- Receiving form rejects excess quantity (warning + disabled button)
- Receiving form is disabled for draft purchases
- Receiving form is disabled for already-received purchases
- Partial receipt updates purchase to partially_received
- Second receipt on a partially_received purchase works
- Goods receipt list is paginated and filterable
- Goods receipt detail shows garment identities (barcode, serial)
- Purchase cost is not visible in goods receipt detail for non-admin (supervisor sees garments but not cost)

### Acceptance criteria — Session 2.5
- [ ] Receiving form is accessible for confirmed/partially_received purchases
- [ ] Staff can enter received quantities per line item and generate garment identities
- [ ] Excess quantity is flagged and blocked
- [ ] Partial receipts work and allow subsequent receipts
- [ ] Goods receipt list and detail views work
- [ ] Only admin + supervisor can access receiving; salesperson is denied
- [ ] Purchase cost is hidden from supervisor in receipt views
- [ ] 13+ tests written (all acceptance criteria covered)

---

## Session 2.6: Barcode Label Generation

**Goal:** Build the barcode label generation backend — choose the PHP barcode library, implement Code 128 rendering, assemble label data (barcode + serial + product name + size + optional price), and define the four label formats. This is pure backend logic, unit-tested without a browser.

**Estimated time:** 3-4 hours

### Prerequisites
- Session 2.4 complete (garments with barcodes exist after receiving).

### Deliverables

**1. Barcode library selection and installation:**
- Evaluate `picqer/php-barcode-generator` (recommended for Code 128 1D barcodes) vs `bacon/bacon-qr-code`.
- Install the chosen library via Composer.
- Document the decision in an ADR (`docs/10-architecture-decisions.md` — ADR-019: Barcode Generation Library. Number re-assigned during Session 2.6 because ADR-018 was already taken by the Search vs. Report Boundary decision).

**2. LabelPrintingService (`app/Modules/Purchasing/Services/LabelPrintingService.php`):**
- `generateBarcodeImage(string $barcode, int $height = 60): string`
  - Renders the barcode as Code 128.
  - Returns the image as a base64-encoded PNG or SVG string (for inline embedding in HTML/print).
- `generateLabelData(Garment $garment, string $format): array`
  - Returns an array with: `barcode` (string), `barcode_image` (base64 PNG/SVG), `serial` (e.g., `25-00001`), `product_name`, `size_label`, `standard_price` (optional, from current product price), `format` (the label format spec).
  - The `standard_price` is included only if a `show_price` flag is set (configurable per print job).
- `generateLabelsForReceipt(GoodsReceipt $receipt, string $format): Collection`
  - Returns a collection of label data arrays, one per garment in the receipt.
- `generateLabelsForRange(int $fromGarmentId, int $toGarmentId, string $format): Collection`
  - For printing a range of garments by ID.
- `getLabelFormats(): array`
  - Returns the four supported formats with their specs (see below).

**3. Label format definitions (four formats):**
```php
[
    'hang_tag_small' => [
        'name' => 'Hang Tag 50×30mm',
        'width_mm' => 50,
        'height_mm' => 30,
        'barcode_height_px' => 40,
        'font_size_pt' => 7,
        'layout' => 'compact',  // barcode + serial + product name
    ],
    'hang_tag_large' => [
        'name' => 'Hang Tag 75×50mm',
        'width_mm' => 75,
        'height_mm' => 50,
        'barcode_height_px' => 50,
        'font_size_pt' => 9,
        'layout' => 'standard',  // barcode + serial + product name + size + optional price
    ],
    'sticker_small' => [
        'name' => 'Sticker 1"×1.5" (25.4×38.1mm)',
        'width_mm' => 25.4,
        'height_mm' => 38.1,
        'barcode_height_px' => 35,
        'font_size_pt' => 6,
        'layout' => 'compact',
    ],
    'sticker_large' => [
        'name' => 'Sticker 1"×2.625" (25.4×66.7mm)',
        'width_mm' => 25.4,
        'height_mm' => 66.7,
        'barcode_height_px' => 45,
        'font_size_pt' => 8,
        'layout' => 'standard',
    ],
]
```

**4. Barcode rendering verification:**
- The generated barcode image must be scannable by the supported printers (Xprinter XP-420B, G-Printer GP-3120TUC, Rongta RP400H).
- Code 128 symbology (per WF-2 step 2: "Code 128 or similar").
- Quiet zones (minimum 10x the narrowest bar width) on both sides.

**5. System Settings integration (data model only — UI is Session 2.7):**
- Add a `barcode_settings` config group to the System Settings module:
  - `default_label_format` — one of the four formats.
  - `default_printer` — printer name/model.
  - `show_price_on_label` — boolean (whether to include standard price on labels by default).
  - `barcode_symbology` — default `CODE128`.
- Store in `config/system_settings` table or a dedicated settings table (follow the existing System Settings pattern from Phase 1, if any; otherwise use Laravel's `config()` + database override).

**6. ADR documentation:**
- Add ADR-019 to `docs/10-architecture-decisions.md`: "Barcode Generation Library — picqer/php-barcode-generator for Code 128." Document context, decision, rationale, consequences.

**7. Tests (`tests/Feature/Purchasing/BarcodeLabelGenerationTest.php`):**
- generateBarcodeImage returns a valid base64 PNG/SVG string
- Barcode image contains the correct Code 128 encoding for the input string
- generateLabelData returns all required fields (barcode, serial, product_name, size_label)
- generateLabelData includes standard_price when show_price flag is true
- generateLabelData excludes standard_price when show_price flag is false
- generateLabelsForReceipt returns one label per garment in the receipt
- generateLabelsForRange returns labels for the correct garment range
- getLabelFormats returns all four formats with correct dimensions
- Hang tag small format is 50×30mm
- Hang tag large format is 75×50mm
- Sticker small format is 25.4×38.1mm
- Sticker large format is 25.4×66.7mm
- Label data for a garment with no size still works (size_label = null or 'N/A')
- Label data for a garment with a size includes the correct size_label
- Barcode image for a 14-character barcode (NND-25-00001) renders without error

### Acceptance criteria — Session 2.6
- [ ] Code 128 barcode library installed and working
- [ ] LabelPrintingService generates barcode images (base64 PNG/SVG)
- [ ] Label data includes barcode, serial, product name, size, optional price
- [ ] All four label formats are defined with correct dimensions
- [ ] Barcode rendering works for the NND-{serial} format
- [ ] ADR-019 documents the library choice
- [ ] 15+ tests written (all acceptance criteria covered)

---

## Session 2.7: Label Printing UI & Printer Configuration

**Goal:** Build the Livewire UI for label printing — select garments (by receipt or range), choose label format and printer, preview labels, and print via browser CSS print media. Also build the printer configuration UI in System Settings.

**Estimated time:** 3-4 hours

### Prerequisites
- Session 2.6 complete (LabelPrintingService exists).

### Deliverables

**1. Livewire components:**
- `PrintLabels` — the label printing interface:
  - Selection method: by goods receipt (dropdown) or by garment ID range (from/to).
  - Label format selector: dropdown with the four formats (showing dimensions).
  - "Show price on label" checkbox (default from system settings).
  - "Preview" button → renders label previews inline (barcode image + text fields) so user can verify before printing.
  - "Print" button → opens the browser print dialog with CSS `@media print` styling for the selected label format.
- `PrinterSettings` — System Settings component:
  - Default label format dropdown.
  - Default printer dropdown (Xprinter XP-420B, G-Printer GP-3120TUC, Rongta RP400H, Other).
  - Show price on label toggle.
  - Barcode symbology (default CODE128).

**2. Views:**
- `resources/views/livewire/purchasing/labels/print.blade.php`
- `resources/views/livewire/settings/printer.blade.php`

**3. CSS print media implementation:**
- A dedicated print stylesheet (`resources/css/print-labels.css` or inline `@media print` block).
- `@page` size directives for each label format:
  ```css
  @media print {
      @page hang-tag-small { size: 50mm 30mm; margin: 0; }
      @page hang-tag-large { size: 75mm 50mm; margin: 0; }
      @page sticker-small { size: 25.4mm 38.1mm; margin: 0; }
      @page sticker-large { size: 25.4mm 66.7mm; margin: 0; }
  }
  ```
- Each label is rendered as a page-break-separated block with the barcode image and text fields laid out per the format's `layout` spec.
- The print view uses a minimal layout (no app navigation, no sidebar) — a dedicated Blade layout `layouts.print`.

**4. Routes:**
- `GET /labels/print` — PrintLabels (admin + supervisor with purchase.receive)
- `GET /labels/print?receipt={id}` — pre-selected by receipt
- `GET /settings/printers` — PrinterSettings (admin only)

**5. Print workflow (WF-2):**
1. Staff selects garments to print labels for (by receipt number or garment range).
2. System generates label data (calls LabelPrintingService).
3. Staff selects label format (hang tag or sticker).
4. Staff selects printer (or uses browser default).
5. System renders labels in HTML with CSS print media.
6. Staff clicks "Print" → browser print dialog → physical labels printed.
7. (Optional) Audit log: `label.print` event with count and format.

**6. Tests (`tests/Feature/Purchasing/LabelPrintingUITest.php`):**
- Admin can access the print labels page
- Page shows receipt selector and garment range selector
- Selecting a receipt loads its garments for label preview
- Label preview shows barcode image and text fields
- Format selector shows all four formats
- Print view renders with correct @page size for each format
- Print view uses the print layout (no navigation/sidebar)
- Supervisor with purchase.receive can access the print page
- Salesperson cannot access the print page (403)
- Printer settings page is admin-only
- Printer settings can save default label format
- Printer settings can save default printer
- Label data does not include purchase cost (even for admin — labels never show cost)
- Preview updates when format or show-price toggle changes

### Acceptance criteria — Session 2.7
- [ ] Print labels UI is accessible and shows garment selection
- [ ] Label preview renders barcode + text fields before printing
- [ ] All four label formats are selectable and render with correct @page sizes
- [ ] Browser print dialog produces correctly-sized labels
- [ ] Printer settings are configurable in System Settings (admin only)
- [ ] Labels never include purchase cost
- [ ] Only admin + supervisor can print labels; salesperson denied
- [ ] 14+ tests written (all acceptance criteria covered)

---

## Session 2.8: Phase 2 Integration & Sensitive Data Hardening

**Goal:** Verify end-to-end integration of all Phase 2 modules, harden sensitive data protection across all roles and views, test fiscal-year carry-forward identity preservation, and confirm Phase 2 exit criteria are met.

**Estimated time:** 2-3 hours

### Prerequisites
- Sessions 2.1–2.7 all complete.

### Deliverables

**1. Phase 2 integration test (`tests/Feature/Phase2IntegrationTest.php`):**
- **Full purchasing lifecycle:** create supplier → create purchase with line items → confirm purchase → receive goods → verify garment identities generated → print labels → view goods receipt → audit log captures all actions.
- **Multi-receipt flow:** create purchase → confirm → partial receipt (half items) → verify purchase is partially_received → second receipt (remaining) → verify purchase is received.
- **Sensitive data across all roles:** for each of the 5 roles, verify:
  - Purchase cost (`unit_cost`, `total_cost`) is visible only to admin.
  - Supplier info (contact_person, phone, email, address) is visible only to admin.
  - Supplier outstanding_balance is visible only to admin.
  - These restrictions hold in: purchase list, purchase view, goods receipt view, garment views, stock queries, API responses.
- **Audit completeness:** verify that the following actions are all audited with correct category, action, reference, and user:
  - `supplier.create`, `supplier.update`, `supplier.deactivate`
  - `purchase.create`, `purchase.confirm`, `purchase.cancel`, `purchase.receive`
  - `label.print` (if implemented)
- **Permission matrix:** verify each role's purchasing permissions:
  - Main Branch Admin: full access (create, confirm, cancel, receive, view cost, view suppliers, manage suppliers, print labels).
  - Branch Supervisor: can receive goods + print labels only. Cannot create/confirm/cancel purchases, cannot view cost, cannot view/manage suppliers.
  - Salesperson: no purchasing access at all.
  - Online Team: no purchasing access.
  - Online Supervisor: no purchasing access.
- **Branch scope:** receiving only happens at Main Branch; non-Main-Branch users cannot receive even with `purchase.receive` permission.

**2. Carry-forward identity test:**
- Create garments in FY 25_26 (serials `25-00001`...`25-00005`).
- Close FY 25_26 and open FY 26_27.
- Receive new garments in FY 26_27 (serials `26-00001`...).
- Verify old garments retain their original serials (`25-00001`) and barcodes.
- Verify new garments get new-format serials (`26-00001`).
- Verify both old and new garments are queryable in stock availability.

**3. Concurrency verification:**
- Run the 100-serial concurrent generation test (from Session 2.4) and confirm all unique.
- Run a concurrent receiving test: two simultaneous receipts on different purchases → both succeed, no serial collisions.

**4. Sensitive data hardening review:**
- Audit every Purchasing-module API response, Livewire view, and Blade template for accidental purchase_cost / supplier_info exposure.
- Verify the Garment model's `$hidden = ['purchase_cost']` works through all query paths (StockQueryService, garment detail views, etc.).
- Verify that `withPurchaseCost()` / `withTotalCost()` accessors are only called in admin-authorized code paths.
- Verify that sensitive-data access by admin is itself audit-logged (per security design §4.3).

**5. Navigation finalization:**
- Complete the navigation bar with all Phase 2 module links (Suppliers, Purchases, Goods Receipts, Print Labels, Printer Settings).
- Role-appropriate menu items: admin sees full Purchasing menu; supervisor sees only Receiving + Print Labels; salesperson sees no Purchasing menu.

**6. Phase 2 acceptance criteria checklist:**
- Run through all acceptance criteria from Sessions 2.1–2.7.
- Verify all tests pass.
- Verify no linting errors.
- Verify all modules are registered and working.

**7. Tests (in `tests/Feature/Phase2IntegrationTest.php`):**
- Full lifecycle: supplier → purchase → confirm → receive → labels → audit
- Multi-receipt partial receiving flow
- Purchase cost hidden from all non-admin roles in all views and APIs
- Supplier info hidden from all non-admin roles in all views and APIs
- Permission matrix verified for all 5 roles
- Receiving only at Main Branch
- Carry-forward: old garments retain identity across fiscal year boundary
- New fiscal year serials restart from 00001
- Concurrent serial generation: 100 unique
- Concurrent receiving: no collisions
- Audit log captures all purchasing actions
- Navigation is role-appropriate

### Acceptance criteria — Session 2.8
- [ ] Full purchasing lifecycle integration test passes
- [ ] Multi-receipt partial receiving works end-to-end
- [ ] Purchase cost is never visible to non-admin in any view, API, or response
- [ ] Supplier info is never visible to non-admin in any view, API, or response
- [ ] Permission matrix is verified for all 5 roles
- [ ] Receiving is restricted to Main Branch
- [ ] Carry-forward preserves garment identity across fiscal years
- [ ] Concurrency tests pass (100 unique serials)
- [ ] Audit log captures all purchasing actions with correct metadata
- [ ] Navigation is complete and role-appropriate
- [ ] All Phase 2 tests pass (Sessions 2.1–2.8)
- [ ] Phase 2 is ready for Phase 3 (POS & Transfers)

---

## 3. Session Execution Guidelines

### 3.1 Before starting a session

1. Ensure the previous session is complete and all its tests pass.
2. Review the session deliverables and acceptance criteria.
3. Check that the dependencies are in place (especially Phase 1 infrastructure for Session 2.4).
4. Create a feature branch: `git checkout -b feature/session-2.X-description`

### 3.2 During a session

1. Implement deliverables in the order listed.
2. Write tests alongside implementation (test-driven where practical — especially for the goods receiving transaction and serial generation).
3. Run tests frequently: `php artisan test`
4. Check code style: `./vendor/bin/pint --test`
5. Commit after each deliverable is complete.

### 3.3 After completing a session

1. Run all tests: `php artisan test`
2. Run code style check: `./vendor/bin/pint`
3. Verify acceptance criteria are met.
4. Commit and push to GitHub.
5. Review the next session's deliverables.
6. Do not start the next session until this one is fully verified.

### 3.4 Quality principles (carry forward from Phase 1)

- **No shortcuts.** Every deliverable is implemented properly, not stubbed.
- **Tests are mandatory.** Every session has tests that must pass.
- **Audit everything.** Every business action writes an audit log entry.
- **Server-side authorization.** Every action is protected by a policy.
- **Sensitive data protection.** Purchase cost and supplier info are never exposed to non-admin — at model, query, resource, and view levels.
- **Atomic transactions.** All multi-step operations (especially goods receiving) use database transactions.
- **Append-only integrity.** Movements and audit logs are never updated or deleted.
- **Concurrency safety.** Serial generation uses advisory locking; the unique constraint is the backstop.
- **PostgreSQL for integration tests.** Advisory locks and unique constraints must be tested on PostgreSQL (not SQLite).

---

## 4. Phase 2 Exit Criteria

Phase 2 is complete when:

1. All 8 sessions are implemented and their tests pass.
2. The full lifecycle integration test (Session 2.8) passes.
3. All acceptance criteria from all sessions are verified.
4. No linting errors.
5. Code is committed and pushed to GitHub.
6. The system can:
   - Manage suppliers (admin only) with full CRUD and search.
   - Create, confirm, and cancel purchase orders with line items (admin only).
   - Receive goods with automatic garment identity generation (UUID, barcode, fiscal-year serial).
   - Support partial receipts and multiple receipts per purchase.
   - Generate garment identities that are concurrency-safe (advisory locking, no duplicate serials).
   - Print barcode labels (Code 128) in four formats via browser CSS print media.
   - Configure default label format and printer in System Settings.
   - Restrict purchase cost and supplier info to Main Branch Admin only — across all views, APIs, and queries.
   - Audit all purchasing actions (supplier, purchase, receiving, label printing).
   - Preserve garment identity across fiscal-year boundaries (carry-forward).
7. Phase 3 (POS & Transfers) can begin.

---

## 5. Open Design Decisions to Close During Phase 2

These decisions are unresolved in the current design docs and must be finalized during the relevant session:

| # | Decision | Session | Recommendation |
|---|---|---|---|
| 1 | Final barcode format string | 2.4 (verify existing) | Keep Phase 1's `NND-{serial}` (e.g., `NND-25-00001`) — simple, unique, embeds serial. |
| 2 | Barcode generation PHP library | 2.6 | `picqer/php-barcode-generator` (Code 128 support). Document as ADR-019. |
| 3 | Print transport | 2.7 | Browser CSS print media first (works for all formats via `@page`). ESC/POS/TSCL JS bridge as future enhancement. |
| 4 | Does PurchasingService create garments directly or via GarmentIdentityService? | 2.4 | Via GarmentIdentityService (extended signature) — respects architecture §3.2 service-interface rule. Single transaction wraps all calls. |
| 5 | Advisory lock key derivation | 2.4 (verify existing) | Already implemented in Phase 1 FiscalYearService — verify the key derivation and document it. |
| 6 | Purchase number format | 2.2 | `PO-{YY}-{NNNNN}` per fiscal year (mirrors serial format). |
| 7 | Receipt number format | 2.4 | `GR-{YY}-{NNNNN}` per fiscal year (separate counter) OR `GR-{purchase_number}-{seq}`. Choose and document. |
| 8 | Audit granularity for receiving | 2.4 | One `purchase.receive` audit entry per garment + one summary entry per receipt. Document and be consistent. |
| 9 | Excess quantity handling | 2.5 | Flag and block (require admin override to accept excess). Do not auto-accept. |
| 10 | Optional trigger for projection sync | 2.4 | Skip in Phase 2 — application-level transactional update is sufficient. Add the `sync_garment_current_stock()` trigger only if drift is detected in Phase 2 integration testing. |

---

*End of Phase 2 Session Breakdown*
