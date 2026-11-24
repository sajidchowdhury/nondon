# Architecture Document
## Nondon — Multi-Branch Women's Dress Retail & Wholesale ERP

**Project:** Nondon
**Version:** 1.0
**Date:** 24 November 2026
**Status:** Design document — pending approval

---

## 1. Architecture Overview

### 1.1 Architecture style

**Laravel Modular Monolith** — a single deployable Laravel application with internal module boundaries. No microservices, no distributed deployment complexity.

**Rationale:**
- The business operates 3-5 physical branches plus an online channel with 1-10 concurrent users initially.
- A modular monolith provides clear domain separation for a complex business (purchasing, inventory, sales, finance, reporting) without the operational overhead of distributed services.
- The architecture is designed to scale to 100+ branches and millions of garment movements without architectural rebuild.
- If a module later requires independent scaling (e.g., reporting), it can be extracted to a service without redesigning the core.

### 1.2 High-level diagram

```
+-------------------------------------------------------------------+
|                     Laravel Modular Monolith                      |
|                                                                   |
|  +-----------+  +-----------+  +-----------+  +-----------+      |
|  | Identity  |  | Catalog   |  | Purchasing|  | Inventory |      |
|  | Module    |  | Module    |  | Module    |  | Module    |      |
|  +-----------+  +-----------+  +-----------+  +-----------+      |
|                                                                   |
|  +-----------+  +-----------+  +-----------+  +-----------+      |
|  | Sales     |  | Online    |  | Transfers |  | Returns   |      |
|  | (POS)     |  | Orders    |  | Module    |  | Module    |      |
|  | Module    |  | Module    |  |           |  |           |      |
|  +-----------+  +-----------+  +-----------+  +-----------+      |
|                                                                   |
|  +-----------+  +-----------+  +-----------+  +-----------+      |
|  | Cash &    |  | Courier   |  | Stock     |  | Expense & |      |
|  | Payment   |  | Recon     |  | Count     |  | Supplier  |      |
|  | Module    |  | Module    |  | Module    |  | Pay Module|      |
|  +-----------+  +-----------+  +-----------+  +-----------+      |
|                                                                   |
|  +-----------+  +-----------+  +-----------+  +-----------+      |
|  | Reporting |  | Audit     |  | Notifi-   |  | System    |      |
|  | Module    |  | Module    |  | cation    |  | Settings  |      |
|  |           |  |           |  | Module    |  | Module    |      |
|  +-----------+  +-----------+  +-----------+  +-----------+      |
|                                                                   |
|  +-----------------------------------------------------------+   |
|  |              Cross-Cutting Concerns Layer                 |   |
|  |  Authorization · Validation · Audit · Transactions       |   |
|  +-----------------------------------------------------------+   |
+-------------------------------------------------------------------+
         |                    |                    |
    +---------+          +---------+          +---------+
    |PostgreSQL|         | Redis   |          | Queue   |
    |(primary)|          | (cache, |          | (jobs,  |
    |         |          |  later) |          |  later) |
    +---------+          +---------+          +---------+
```

---

## 2. Technology Stack

| Component | Technology | Notes |
|---|---|---|
| Backend framework | Laravel (latest stable) | Modular monolith |
| Language | PHP 8.3+ | Strict typing enabled |
| Database | PostgreSQL 15+ | Primary datastore |
| Cache | Redis (optional initially) | Can start with file/database cache; Redis introduced when needed |
| Queue | Laravel Queues (database driver initially) | Switch to Redis when needed |
| Frontend | Blade + Livewire | Server-rendered with interactivity |
| UI framework | Tailwind CSS + Alpine.js | Via Livewire |
| Authentication | Laravel's built-in auth | Custom guards for branch-scoped sessions |
| Authorization | Spatie Laravel Permission | Role + custom permission layer |
| API | Laravel API Resources | RESTful, for future integrations |
| Testing | Pest PHP | Modern PHP testing framework |
| Deployment | Nginx + PHP-FPM | Standard Laravel deployment |
| Version control | Git / GitHub | |
| Barcode | ESC/POS / TSCL protocol via thermal printers | Xprinter XP-420B, G-Printer GP-3120TUC, Rongta RP400H |
| Receipt printing | Thermal 58mm/80mm + A4 | Browser-based print via CSS |
| Background jobs | Laravel Scheduler + Queues | Scheduled reports, alert detection, sync |

### 2.1 Why not microservices

- Business operates with 1-10 concurrent users initially.
- Distributed transactions across services would add complexity for inventory and payment atomicity.
- Operational overhead (deployment, monitoring, service discovery) is not justified at this scale.
- A well-structured modular monolith handles 100+ branches and millions of movements with proper indexing and projections.

### 2.2 When to reconsider microservices

- If reporting workloads grow to require independent scaling (extract reporting module as a read-only service).
- If the online channel grows to require a separate deployment cycle.
- If courier/payment integrations require independent scaling.
- None of these apply at initial launch.

---

## 3. Module Decomposition

Each module is a self-contained domain with its own models, services, HTTP controllers, Livewire components, database migrations, and tests. Modules communicate through well-defined service interfaces, not direct cross-module model access.

### 3.1 Module list

| # | Module | Responsibility | Primary consumers |
|---|---|---|---|
| 1 | Identity & Access | Users, roles, permissions, authentication, login security, session, role-switching | All |
| 2 | Organization | Branches, online channel, stock locations, fiscal years | All |
| 3 | Catalog | Products, designs, sizes, variants, pricing, product lifecycle | Admin, all (read) |
| 4 | Purchasing | Suppliers, purchase orders, goods receiving, garment identity generation, barcode printing | Admin, receiving staff |
| 5 | Inventory | Garment records, stock balances, inventory movements, reservations, statuses | All |
| 6 | Transfers | Transfer requests, approval, dispatch, receiving, discrepancies | Supervisors, admin |
| 7 | Sales (POS) | Physical POS sales, invoices, checkout, discounts, offline mode, receipt printing | Salespersons, supervisors |
| 8 | Online Orders | Online order lifecycle, multi-branch sourcing, reservation via transfer, courier dispatch, cancellation | Online team, online supervisor |
| 9 | Returns | Customer returns, exchanges, refunds, return verification, online return routing | Salespersons, supervisors, online team |
| 10 | Cash & Payment | Salesperson cash, branch cash, online channel cash, handovers, closing, payment methods, discrepancy handling | All |
| 11 | Courier Reconciliation | COD settlement, reconciliation matching, discrepancy flags | Online supervisor, admin |
| 12 | Stock Count | Full/selective counts, freeze, discrepancies, adjustments, missing garment recovery | Supervisors, admin |
| 13 | Damaged Stock | Damage reporting, inspection, disposition, supplier return | Supervisors, admin |
| 14 | Expenses | Expense submission, approval, categories | Supervisors, admin |
| 15 | Supplier Payments | Supplier payment recording, outstanding balances, duplicate detection | Admin |
| 16 | Customers | Customer profiles, phone-based lookup, duplicate merge, customer history | All (read/create), admin (edit/merge) |
| 17 | Reporting | Role-scoped reports, dashboards, exports, scheduled email reports | Admin (full), supervisors (today), others (own today) |
| 18 | Audit | Audit log writing, querying, retention, sensitive-data access logging | Admin, system |
| 19 | Notifications | Inventory alerts, scheduled report emails, in-app notifications | System, admin |
| 20 | System Settings | Payment methods, return policies, approval limits, fiscal year config, barcode/printer config | Admin |

### 3.2 Module dependency rules

- Modules may depend on the **Identity, Organization, Catalog, Inventory, Audit** modules (core/foundation modules).
- Business modules (Sales, Transfers, Returns, Online Orders, etc.) may depend on each other only through **service interfaces**, not direct model access.
- No circular dependencies.
- All cross-module write operations go through a service class, not a controller calling another module's model directly.

### 3.3 Module structure (per module)

```
app/Modules/<ModuleName>/
├── Models/
│   └── <ModuleModel>.php
├── Services/
│   └── <ModuleName>Service.php
├── Http/
│   ├── Controllers/
│   ├── Livewire/
│   └── Requests/
├── Database/
│   ├── Migrations/
│   └── Seeders/
├── Policies/
├── Events/
├── Listeners/
└── Tests/
```

---

## 4. Layered Architecture (within each module)

```
┌─────────────────────────────────────────────────┐
│  Presentation Layer                              │
│  Controllers · Livewire Components · Blade Views │
│  Input validation (Form Requests)               │
│  Authorization (Policies)                        │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│  Application / Service Layer                     │
│  Business logic · Transaction boundaries         │
│  Cross-module orchestration                      │
│  Audit dispatching · Event emission              │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│  Domain / Model Layer                            │
│  Eloquent models · Relationships · Scopes        │
│  Domain constants · Enums                        │
│  No business logic (kept in services)            │
└──────────────────────┬──────────────────────────┘
                       │
┌──────────────────────▼──────────────────────────┐
│  Data Access Layer                               │
│  PostgreSQL · Migrations · Query scopes          │
│  Read models / projections (denormalized)        │
│  Constraints · Indexes                           │
└─────────────────────────────────────────────────┘
```

### 4.1 Key principles

- **Controllers are thin.** They validate input, call services, return responses.
- **Services contain business logic.** They manage transactions, call other services, dispatch events.
- **Models define relationships and scopes only.** No business logic in models.
- **Policies enforce authorization.** Every sensitive action has a policy.
- **Form Requests validate input.** Every endpoint has a dedicated request class.
- **Events drive cross-module communication.** A sale completion dispatches `SaleCompleted`, which Inventory listens to for stock deduction, Cash listens to for payment recording, Audit listens to for logging.

---

## 5. Offline POS Architecture

### 5.1 Overview

POS devices must operate when internet is unavailable. The offline mode supports sales and customer lookup only.

```
┌──────────────────────────────────────────────────┐
│  POS Device (Browser, per-branch)                │
│                                                  │
│  ┌─────────────┐    ┌──────────────────────┐    │
│  │ Online Mode │    │ Offline Mode         │    │
│  │             │    │                      │    │
│  │ Full server │◄──►│ Local cache (LS/IDB) │    │
│  │ access      │    │ - Product catalog    │    │
│  │             │    │ - Prices             │    │
│  │             │    │ - Stock snapshot     │    │
│  │             │    │ - Customer phone idx │    │
│  │             │    │ - Offline sale queue │    │
│  └─────────────┘    └──────────┬───────────┘    │
│                                │                │
│                    ┌───────────▼───────────┐    │
│                    │ Sync Engine           │    │
│                    │ - Auto on reconnect   │    │
│                    │ - Manual button       │    │
│                    │ - Conflict detection  │    │
│                    └───────────┬───────────┘    │
└────────────────────────────────┼────────────────┘
                                 │
                    ┌────────────▼───────────┐
                    │ Laravel Server          │
                    │ /api/sync endpoints     │
                    └─────────────────────────┘
```

### 5.2 Local cache strategy

| Data | Cache source | Refresh trigger | Conflict risk |
|---|---|---|---|
| Product catalog | Server pull | On sync, on login | None (read-only) |
| Prices (standard + minimum) | Server pull | On sync, on login | None (read-only) |
| Stock availability snapshot | Server pull | On sync | **High** — offline sale may sell a garment that was reserved/sold online |
| Customer phone index | Server pull | On sync | Low — new customers created offline, merged on sync |
| Offline sale queue | Local write | On sale completion | **High** — same garment may be sold on another device |

### 5.3 Conflict resolution

**Garment double-sold conflict:**
1. Device A sells garment G offline (status: queued).
2. Device B (or online order) sells garment G online (status: committed).
3. Device A reconnects and syncs.
4. Server detects G is already sold.
5. Sale from Device A is flagged as a **conflict** and escalated to Main Branch Admin.
6. Admin reviews, decides: reverse the later sale (refund + restore stock), or treat as a discrepancy.
7. Both sale records retained for audit; resolution is a separate correction transaction.

**Reservation conflict:**
1. Device A offline; garment G is available in cache.
2. Online staff creates a transfer request reserving G at the branch.
3. Device A sells G offline (queued).
4. On sync, server detects G was reserved by online transfer.
5. Conflict flagged; admin decides which transaction wins.

### 5.4 Cache storage

- **LocalStorage** for small configuration data (branch ID, user session, prices).
- **IndexedDB** for larger data (product catalog, customer phone index, offline sale queue).
- Cache is **per-device, per-branch** — a device assigned to Branch A only caches Branch A's data.

### 5.5 Sync protocol

```
POST /api/sync/push
  - Array of offline sales (with local UUID, timestamp, garment IDs, payment details)
  - Server processes each, returns per-sale status (committed / conflict)

GET /api/sync/pull
  - Server returns updated catalog, prices, stock snapshot, customer index
  - Client replaces local cache with fresh data
```

Sync is **idempotent** — each offline sale has a client-generated UUID; the server deduplicates by UUID.

---

## 6. Concurrency and Transaction Strategy

### 6.1 Database transactions

All multi-step business operations are wrapped in database transactions:

| Operation | Transaction scope |
|---|---|
| POS sale completion | Reserve garments → create invoice → create payments → deduct stock → commit |
| Online order confirmation | Create order → create transfer requests → reserve garments → commit |
| Transfer dispatch | Update garment statuses → create movement records → commit |
| Transfer receiving | Update garment statuses → create movement records → flag discrepancies → commit |
| Return processing | Verify original sale → create return → refund payment → restore/reject stock → commit |
| Cash handover | Debit salesperson cash → credit branch cash → dual confirmation → commit |
| Stock count adjustment | Create adjustment → update balances → audit log → commit |

### 6.2 Row-level locking

Garment-level locking on critical operations:

```php
// POS checkout — lock the garment row for the duration of the transaction
$garment = Garment::lockForUpdate()->find($id);
if ($garment->status !== GarmentStatus::Available) {
    throw new GarmentNotAvailableException();
}
// Proceed with reservation/sale
```

This prevents two concurrent checkouts from claiming the same garment.

### 6.3 Optimistic concurrency for offline sync

Offline sales use a version/UUID approach:
- Each offline sale carries a client UUID.
- Server processes with `INSERT ... ON CONFLICT DO NOTHING` on the UUID column.
- If the UUID already exists, the sale was already synced (idempotent).
- Garment availability is checked server-side at commit time, not trusted from the client.

---

## 7. Current-Stock Projection

### 7.1 Problem

The inventory movement ledger is append-only and grows to millions of rows. Routine stock queries (POS garment lookup, stock availability, transfer checks) must not scan the full ledger.

### 7.2 Solution: denormalized current-stock projection

**`garment_current_stock` table** — one row per garment, maintained as a projection of the movement ledger:

| Column | Description |
|---|---|
| garment_id | FK to garment |
| current_branch_id | Current physical location |
| current_status | Available / Reserved / In Transit / Sold / etc. |
| reservation_reference | Transfer request ID or online order ID if reserved |
| last_movement_id | FK to last movement record |
| updated_at | Last projection update |

### 7.3 Projection maintenance

Two strategies, used together:

**Strategy A — Transactional update (primary):**
Every service that changes garment status updates `garment_current_stock` within the same database transaction. This guarantees consistency for online operations.

**Strategy B — Reconciliation job (periodic):**
A background job runs hourly/daily, recomputes `garment_current_stock` from the movement ledger, and flags any drift. Drift indicates a bug or a bypassed code path.

### 7.4 Read model for reporting

For aggregate reports (stock by branch, sales by day, etc.), a separate set of **summary tables** is maintained:

- `daily_sales_summary` — per branch, per day, per sales type.
- `stock_summary` — per product, per size, per branch, current available count.
- `cash_summary` — per branch, per day, opening/closing/collections.

These are updated by event listeners or scheduled jobs, not computed on-the-fly for every report request.

---

## 8. Event-Driven Communication

### 8.1 Cross-module events

Modules communicate through Laravel events. The emitting module does not know which modules listen.

| Event | Emitted by | Listeners |
|---|---|---|
| `PurchaseReceived` | Purchasing | Inventory (create garment records), Audit |
| `GarmentReserved` | Inventory | Sales (block POS), Online Orders (confirm reservation) |
| `SaleCompleted` | Sales | Inventory (mark sold), Cash (record payment), Customers (link purchase), Audit |
| `TransferRequested` | Transfers | Inventory (reserve garments) |
| `TransferDispatched` | Transfers | Inventory (mark in-transit) |
| `TransferReceived` | Transfers | Inventory (update location), Audit |
| `ReturnProcessed` | Returns | Inventory (restore/reject), Cash (refund), Audit |
| `CashHandoverConfirmed` | Cash | Audit |
| `CashClosingSubmitted` | Cash | Notifications (email admin), Audit |
| `StockCountDiscrepancySubmitted` | Stock Count | Notifications (alert admin), Audit |
| `DamageReported` | Damaged Stock | Inventory (block sale), Audit |
| `CourierSettlementReconciled` | Courier Recon | Cash (record receipt), Audit |
| `ExpenseApproved` | Expenses | Cash (deduct), Audit |
| `SupplierPaymentConfirmed` | Supplier Payments | Audit |
| `ReservationManuallyReleased` | Online Orders | Inventory (release), Audit |

### 8.2 Synchronous vs asynchronous

- **Synchronous events** (Laravel events with listeners in the same request): inventory movements, reservation, sale completion. These must complete within the transaction.
- **Asynchronous events** (queued listeners): notifications, email reports, alert detection, summary table updates. These are pushed to the queue and processed by workers.

---

## 9. Authentication and Session Architecture

### 9.1 Authentication

- Laravel's built-in authentication with a custom `User` model.
- Username + password login.
- Password hashing via Laravel's `Hash` facade (bcrypt/argon2).
- Five failed attempts lock the account; only admin unlocks.
- Login attempts logged to audit.

### 9.2 Session

- Database-backed sessions (allows multiple devices).
- **No automatic inactivity logout** (client decision; security review pending for shared devices).
- Manual logout required.
- Session stores: user ID, active role, active branch, login timestamp.

### 9.3 Role switching

A user with multiple roles selects an active role at login or via a role-switcher in the UI. The active role is stored in the session and checked by policies.

```
Login → User has roles [Salesperson, Supervisor]
       → Select active role: [Salesperson] or [Supervisor]
       → Session stores active_role_id
       → All policy checks use active_role, not the union of all roles
```

### 9.4 Branch scoping

Every authenticated session has an active branch (the user's assigned branch). All queries are automatically scoped:

```php
// In a base query scope
public function scopeForBranch($query, $branchId) {
    return $query->where('branch_id', $branchId);
}
```

Policies verify that the user's active branch matches the resource's branch before allowing write operations.

---

## 10. API Architecture

### 10.1 Internal API

The primary interface is Livewire + Blade (server-rendered). The "API" layer exists for:

- Offline POS sync (`/api/sync/*`).
- Future mobile app or third-party integration.
- Courier/payment gateway webhooks (future).

### 10.2 API design

- RESTful routes under `/api/v1/`.
- API Resources for consistent response shaping.
- Sanctum token authentication for API clients.
- All API endpoints enforce the same authorization policies as the Livewire interface.

### 10.3 API versioning

- URL-based versioning (`/api/v1/`, `/api/v2/`).
- Backward compatibility maintained for one major version.

---

## 11. Background Jobs and Scheduling

### 11.1 Laravel Scheduler

| Schedule | Job | Purpose |
|---|---|---|
| Every minute | `ProcessQueue` | Process queued jobs |
| Hourly | `ReconcileStockProjection` | Verify current-stock projection against ledger |
| Daily (configurable) | `SendDailyCashReport` | Email daily cash report to configured recipients |
| Daily | `ReleaseStaleReservations` | (Disabled by default — reservations are manual release; job exists for future use) |
| Daily | `GenerateDailySalesSummary` | Populate daily_sales_summary |
| Weekly | `WeeklyBackup` | Database backup (client decision; daily offsite recommended) |
| Monthly | `SendMonthlyLedgerReport` | Email monthly customer ledger report |
| Configurable | `DetectInventoryAlerts` | Check alert thresholds, notify recipients |

### 11.2 Queue

- Database driver initially (no Redis dependency on day one).
- Switch to Redis when queue volume grows.
- Jobs: email sending, report generation, summary table updates, alert detection.

---

## 12. Deployment Architecture

### 12.1 Single-server deployment (initial)

```
┌─────────────────────────────────────────┐
│  Production Server                      │
│                                         │
│  ┌─────────┐    ┌──────────────────┐   │
│  │ Nginx   │───►│ PHP-FPM          │   │
│  │ :80/:443│    │ (Laravel app)    │   │
│  └─────────┘    └──────────────────┘   │
│       │                    │            │
│       │            ┌───────▼────────┐   │
│       │            │ PostgreSQL 15+ │   │
│       │            └────────────────┘   │
│       │                    │            │
│       │            ┌───────▼────────┐   │
│       │            │ Backup storage │   │
│       │            └────────────────┘   │
│       │                                  │
│  ┌────▼────────┐                        │
│  │ SSL/TLS     │                        │
│  │ (Let's      │                        │
│  │  Encrypt)   │                        │
│  └─────────────┘                        │
└─────────────────────────────────────────┘
```

### 12.2 Scaling path (future)

When the business grows:
1. **Add Redis** for cache and queue (separate process or server).
2. **Separate PostgreSQL** to its own server.
3. **Add a read replica** for PostgreSQL to handle reporting queries.
4. **Add a queue worker server** if job volume grows.
5. **CDN** for static assets.

None of these require code changes — only infrastructure configuration.

### 12.3 Branch device setup

Each branch has:
- 1+ POS devices (laptops/tablets with thermal printer attached).
- Internet connection (primary).
- Local cache (browser IndexedDB) for offline operation.

No branch-local server. All data is centralized on the production server. Offline mode is a browser-level cache, not a branch-local application.

---

## 13. Error Handling and Logging

### 13.1 Error handling

- Laravel's exception handler centralizes error responses.
- Business exceptions (e.g., `GarmentNotAvailableException`, `InsufficientPaymentException`) return structured error responses.
- System exceptions are logged and return a generic error to the user.
- No sensitive data in error messages exposed to users.

### 13.2 Logging

- Application logs via Laravel's `Log` facade (Monolog).
- Audit logs via the Audit module (separate from application logs).
- Logs rotated daily.
- Error notifications to admin for critical failures (e.g., sync conflicts, projection drift).

---

## 14. Testing Architecture

### 14.1 Test pyramid

```
        ┌───────────┐
        │  E2E /    │  Few — critical user journeys (POS sale, transfer, return)
        │  Acceptance│
        ├───────────┤
        │ Integration│  Moderate — service + database, multi-module flows
        ├───────────┤
        │   Unit     │  Many — service methods, model scopes, policies
        └───────────┘
```

### 14.2 Critical path tests

Every release must pass:
- POS sale (online and offline mode).
- Transfer request → approval → dispatch → receiving.
- Online order → transfer → dispatch → courier → settlement.
- Return processing (physical and online, both refund paths).
- Cash handover and closing.
- Stock count with discrepancy.
- Concurrency: two concurrent sales of the same garment (one must fail).
- Offline sync with conflict detection.
- Permission enforcement per role.

### 14.3 Test data

- Factories for all models.
- Seeders for development and testing.
- Dedicated test database (separate from development).

---

## 15. Security Architecture

(Detailed in `05-security-design.md`)

### 15.1 Summary

- Server-side authorization on every operation (policies).
- Branch-scoped access enforcement.
- Sensitive-data restrictions independent of role combinations.
- Secure password hashing.
- Login lockout.
- Audit logging of all meaningful business actions.
- No shared accounts.
- No direct database manipulation bypassing business rules.

---

## 16. Observability

### 16.1 Monitoring

- Laravel Telescope (development/debugging).
- Application logs for production monitoring.
- Health check endpoint (`/api/health`).
- Alerting on: sync conflicts, projection drift, failed jobs, login lockouts.

### 16.2 Metrics

- Sales per hour/day (per branch).
- Sync queue depth.
- Offline device count.
- Conflict count.
- Job failure rate.

---

## 17. Directory Structure (Laravel Application)

```
nondon/
├── app/
│   ├── Modules/
│   │   ├── Identity/
│   │   ├── Organization/
│   │   ├── Catalog/
│   │   ├── Purchasing/
│   │   ├── Inventory/
│   │   ├── Transfers/
│   │   ├── Sales/
│   │   ├── OnlineOrders/
│   │   ├── Returns/
│   │   ├── Cash/
│   │   ├── CourierReconciliation/
│   │   ├── StockCount/
│   │   ├── DamagedStock/
│   │   ├── Expenses/
│   │   ├── SupplierPayments/
│   │   ├── Customers/
│   │   ├── Reporting/
│   │   ├── Audit/
│   │   ├── Notifications/
│   │   └── SystemSettings/
│   ├── Shared/
│   │   ├── Concerns/
│   │   ├── Exceptions/
│   │   ├── Enums/
│   │   └── Helpers/
│   └── Providers/
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── resources/
│   └── views/
│       ├── layouts/
│       └── livewire/
├── routes/
│   ├── web.php
│   └── api.php
├── tests/
├── config/
├── docs/
│   ├── 01-business-requirements.md
│   ├── 02-architecture.md
│   ├── 03-database-design.md
│   └── ...
└── ...
```

---

## 18. Open Architectural Decisions

To be finalized during design review:

1. **PDF generation library** for A4 invoices and reports (options: DomPDF, Snappy, mPDF).
2. **Barcode generation library** for label printing (options: bacon/bacon-qr-code, picqer/php-barcode).
3. **Email service** for scheduled reports (Laravel Mail + SMTP, or a service like Sendgrid/Mailgun).
4. **Offline sync protocol** details (conflict window size, retry strategy).
5. ** Whether to use Livewire Volt or classic Livewire components**.
6. **Queue driver migration timing** (database → Redis).

These do not block the design; they are implementation-level choices.

---

*End of Architecture Document*
