# Architecture Decision Records
## Nondon — Multi-Branch Women's Dress Retail & Wholesale ERP

**Project:** Nondon
**Version:** 1.0
**Date:** 24 November 2026
**Status:** Design document — pending approval

---

## 1. Purpose

This document records the key architectural decisions made during the design of the Nondon ERP system. Each Architecture Decision Record (ADR) captures the context, decision, rationale, and consequences of a significant choice.

ADR format:
- **ID:** ADR-NNN
- **Title:** Brief description
- **Status:** Proposed / Accepted / Superseded
- **Date:** When decided
- **Context:** Why this decision was needed
- **Decision:** What was decided
- **Rationale:** Why this option was chosen
- **Consequences:** Impact of the decision

---

## ADR-001: Laravel Modular Monolith Architecture

**Status:** Accepted
**Date:** November 2026

### Context
The system is a multi-branch retail ERP with complex business logic (purchasing, inventory, sales, transfers, returns, cash management, reporting). The business operates 3-5 branches initially with 1-10 concurrent users, but must scale to 100+ branches and millions of transactions.

Options considered:
1. Microservices architecture.
2. Single monolithic Laravel application (no internal structure).
3. Laravel modular monolith with internal module boundaries.

### Decision
Adopt **Laravel modular monolith** architecture. A single deployable Laravel application with clear internal module boundaries (Identity, Catalog, Purchasing, Inventory, Sales, Transfers, Returns, Cash, etc.).

### Rationale
- Microservices add operational complexity (deployment, monitoring, distributed transactions) not justified at 1-10 concurrent users.
- A plain monolith without module boundaries leads to tangled code as the business logic grows.
- A modular monolith provides domain separation, testability, and clear ownership while remaining a single deployable unit.
- If a module later requires independent scaling (e.g., reporting), it can be extracted to a service without redesigning the core.
- The team is likely small initially; a modular monolith is easier to develop and maintain than distributed services.

### Consequences
- Single deployment artifact.
- Shared database (PostgreSQL) — no distributed transaction complexity.
- Module boundaries must be enforced by convention (service interfaces, not direct cross-module model access).
- Future extraction to microservices is possible but requires refactoring module communication.

---

## ADR-002: PostgreSQL as Primary Database

**Status:** Accepted
**Date:** November 2026

### Context
The system requires a relational database with strong consistency, transactional integrity, row-level locking, partitioning, and constraints for financial and inventory data.

Options considered:
1. MySQL 8.0+.
2. PostgreSQL 15+.
3. SQLite (for development only).

### Decision
Use **PostgreSQL 15+** as the primary database for both development and production.

### Rationale
- Superior concurrency handling with MVCC.
- Better partitioning support (declarative partitioning, partition pruning).
- Stronger constraint enforcement (CHECK constraints, exclusion constraints).
- JSONB support for flexible configuration data.
- Better performance for complex queries and aggregations.
- Generated columns support (for discrepancy calculations).
- Array types (for missing_garment_ids in transfer discrepancies).
- Client preference (specified in BRD).
- Open source, no licensing cost.

### Consequences
- All migrations must be PostgreSQL-compatible (no MySQL-specific syntax).
- Development environment must use PostgreSQL (not SQLite) for integration tests to match production.
- Backup tooling uses pg_dump.

---

## ADR-003: Garment-Level Inventory Tracking with Permanent Barcode + Fiscal-Year Serial

**Status:** Accepted
**Date:** November 2026

### Context
The client's business requires tracking every physical garment individually. Garments receive a fiscal-year serial (e.g., 25-00001) that restarts each July. Unsold stock carries forward to the next fiscal year with its original identity.

### Decision
Implement **three-tier garment identity**:
1. **Internal garment ID** (UUID) — immutable, globally unique, never reused.
2. **Permanent barcode** — system-generated, globally unique, never reused, independent of fiscal year.
3. **Fiscal-year serial** — format `YY-NNNNN` (e.g., 25-00001), global sequence per fiscal year, restarts each July.

### Rationale
- The fiscal-year serial provides human-readable tracking and indicates approximate receipt volume.
- The permanent barcode ensures uniqueness across fiscal years (since serials repeat).
- The internal UUID provides a stable system identifier for relationships and references.
- Global sequence per fiscal year (not per-product) matches the client's numbering convention.
- The serial format `YY-NNNNN` allows up to 99,999 garments per fiscal year (extendable to 6 digits if needed).

### Consequences
- Unique constraint on `(fiscal_year_id, fiscal_year_serial)`.
- Unique constraint on `barcode`.
- Serial generation requires advisory locking to prevent duplicates under concurrent receipt.
- Carry-forward to new fiscal year does NOT change garment identity.
- Barcode labels must be printed at goods receipt and physically attached.

---

## ADR-004: Append-Only Inventory Movement Ledger with Current-Stock Projection

**Status:** Accepted
**Date:** November 2026

### Context
Inventory movements grow to millions of rows over years. Routine stock queries (POS lookup, availability checks) must be fast (< 200ms). Scanning the full movement ledger for every query is not viable.

### Decision
Implement **dual-layer inventory data model**:
1. **`inventory_movements` table** — append-only ledger. Every stock change creates a new row. Never UPDATE or DELETE.
2. **`garment_current_stock` table** — denormalized projection. One row per garment with current status, branch, and location.

### Rationale
- Append-only ledger provides complete audit trail and history.
- Projection table enables fast queries without scanning the ledger.
- Projection is maintained transactionally alongside the ledger (same DB transaction).
- Periodic reconciliation job verifies projection against the ledger and flags drift.
- Corrects through new movement records, never overwrites.

### Consequences
- Every stock-changing service method must update both tables in one transaction.
- Reconciliation job must run periodically to detect drift.
- Additional storage for the projection table (one row per garment).
- Report queries use summary tables, not the raw ledger.

---

## ADR-005: Offline POS with Local Cache and Sync-Based Conflict Resolution

**Status:** Accepted
**Date:** November 2026

### Context
Branches may experience internet outages. POS must continue operating during outages. The system cannot require constant internet for sales.

### Decision
Implement **offline POS mode**:
- POS devices use browser IndexedDB/LocalStorage to cache product catalog, prices, stock snapshot, and customer phone index.
- Offline sales are stored locally with a client-generated UUID.
- On reconnection, sales are pushed to the server via sync API.
- Server processes each sale with garment availability check (server-side, not trusted from client).
- Conflicts (garment already sold) are flagged for admin resolution.
- Sync is idempotent (UUID deduplication).

### Rationale
- Offline mode ensures business continuity during internet outages.
- Client UUID ensures idempotency (no duplicate sales on retry).
- Server-side availability check prevents trusting stale client data.
- Conflict detection ensures data integrity; admin resolves conflicts case-by-case.

### Consequences
- Offline mode supports sales and customer lookup only (no transfers, returns, or adjustments offline).
- Stock snapshot may be stale; conflicts are possible and must be resolved.
- Browser storage limits (~50MB) are sufficient for per-branch cache.
- Cache must be cleared on logout for security.
- No sensitive data (purchase cost, supplier info) in local cache.

---

## ADR-006: Transfer-Based Online Order Fulfillment (No Reservation-at-Source)

**Status:** Accepted
**Date:** November 2026

### Context
Online orders need garments from physical branches. The original design considered reserving garments at the source branch and dispatching directly to the courier. The client clarified that the online channel receives garments via transfer, then dispatches to the customer.

### Decision
Online orders use **transfer-based fulfillment**:
1. Online staff confirms order.
2. System creates transfer request(s) from source branch(es) to the online channel.
3. Transfer request implicitly reserves garments at the source branch.
4. Source branch approves and dispatches.
5. Online channel receives garments.
6. Online staff dispatches to customer via courier.

### Rationale
- The online channel acts as a transient stock-holding branch-equivalent.
- Transfer-based flow is consistent with the physical branch transfer workflow.
- The online channel has its own cash account and stock location.
- Simplifies the model: online is "just another branch" for stock and cash purposes.
- Returns route back through the online channel to the original physical branch.

### Consequences
- Online channel has a stock location (transient).
- Multi-branch sourcing creates multiple transfer requests per order.
- Order status is composite (all items must be received before courier dispatch).
- Returns involve transferring garments back to the original physical branch.

---

## ADR-007: Five-Role RBAC with Role Switching

**Status:** Accepted
**Date:** November 2026

### Context
The system needs role-based access control with clear permission boundaries. Originally 8 roles were proposed. The client simplified to 5 roles and specified that all roles can sell (supervision is an additional layer, not a replacement).

### Decision
Implement **5 roles**:
1. Main Branch Admin
2. Branch Supervisor
3. Salesperson
4. Online Sales Team
5. Online Sales Supervisor

Multi-role employees **switch between roles** in the UI. The active role determines the permission scope.

### Rationale
- Eliminated Cashier (merged into Salesperson — same person does both).
- Eliminated Stock Manager (merged into Supervisor).
- Eliminated Inspection Staff (branch-level by Supervisor, supplier-return by Admin).
- All roles can sell — supervision/admin is an additional permission set, not a separate non-selling role.
- Role switching (not union) ensures the user operates in a clear scope at any time.

### Consequences
- UI needs a role-switcher component.
- Authorization checks use the active role, not the union of assigned roles.
- Branch scope and sensitive-data restrictions always apply regardless of active role.
- Simpler permission matrix and UI.

---

## ADR-008: No Automatic Reservation Expiry — Manual Release Only

**Status:** Accepted
**Date:** November 2026

### Context
Online order reservations were originally designed with automatic expiry durations. The client clarified that expiry is a manual decision based on customer commitment, not a fixed time window.

### Decision
**No automatic reservation expiry.** Reservations are released:
- When the transfer is received at the online channel (garment moves to online stock).
- When the transfer/order is cancelled.
- Manually by authorized staff (Online Supervisor, Main Admin).

The system displays current reservation counts per branch for management visibility.

### Rationale
- Expiry duration depends on the commitment made to each customer (variable).
- Automatic expiry could release a garment that a customer is expecting, causing service failures.
- Manual release gives staff control over customer commitments.
- Reservation count display provides visibility without automatic action.

### Consequences
- No scheduled job to auto-release reservations.
- Staff must actively manage reservations.
- Reservation count view is needed in the UI.
- Risk of forgotten reservations (mitigated by count display and supervisor oversight).

---

## ADR-009: Manual Cash Discrepancy Resolution by Admin Only

**Status:** Accepted
**Date:** November 2026

### Context
Cash discrepancies occur at daily closing (counted vs expected). The original design had supervisors investigate and propose findings. The client clarified that supervisors only record the factual count and difference; admin handles all investigation and resolution.

### Decision
**Supervisor records factual count/difference; admin investigates and resolves.**
- Supervisor performs daily closing, enters counted cash.
- System records the difference (generated column).
- Supervisor's responsibility ends at recording.
- Admin investigates all discrepancies and records resolution.
- No automatic salary deduction.

### Rationale
- Separation of duties: supervisor reports, admin resolves.
- Prevents supervisor from covering up their own discrepancies.
- Admin has full visibility and authority for investigation.
- No automatic salary deduction protects employee rights (findings may be counting errors, not theft).

### Consequences
- Admin must review discrepancies daily.
- Discrepancy queue is part of admin dashboard.
- Original records are immutable; resolution is a separate audit entry.

---

## ADR-010: No Tax Line on Invoice (Pending Legal Review)

**Status:** Accepted (pending legal/compliance review)
**Date:** November 2026

### Context
The client requested no tax/VAT line on customer-facing invoices. This may conflict with Bangladesh VAT law requirements.

### Decision
**No tax/VAT line on customer-facing invoice.** Subject to legal/compliance review before go-live.

### Rationale
- Client business decision.
- Simplifies invoice format.
- Client is responsible for tax compliance.

### Consequences
- Invoice template does not include tax breakdown.
- Legal review must confirm compliance with Bangladesh VAT and SD Act 2012.
- If non-compliant, invoice template must be updated before go-live.
- Does not affect internal financial tracking (which records all transactions regardless).

---

## ADR-011: Courier as Free-Text Notes (No Master Data or API Integration)

**Status:** Accepted
**Date:** November 2026

### Context
The system needs to track courier information for online orders. Originally, courier companies were to be pre-configured master data with potential API integration. The client decided not to formalize couriers initially.

### Decision
**Courier name and tracking number are free-text notes on the order.** No courier master data, no API integration in the initial release.

### Rationale
- Courier selection is flexible and may change frequently.
- No dependency on specific courier APIs.
- Simpler data model.
- COD reconciliation is done manually by the Online Supervisor against order records.

### Consequences
- No courier master table.
- `courier_name` and `courier_tracking_number` are free-text fields on `online_orders`.
- COD reconciliation matches by order ID or tracking text, not by courier reference.
- Future courier API integration would require adding a courier master table.

---

## ADR-012: Weekly On-Server Backup (with Daily Offsite Recommended)

**Status:** Accepted (with recommendation for review)
**Date:** November 2026

### Context
The system needs backup and disaster recovery. The client specified weekly backup stored on the server.

### Decision
**Weekly automatic backup stored on the server.** Recommendation: daily backup with offsite copy for retail business continuity.

### Rationale
- Client decision: weekly on-server backup.
- Recommendation: daily offsite backup for a retail business with daily cash transactions (up to 6 days data loss risk with weekly on-server backup).
- PostgreSQL WAL archiving recommended for point-in-time recovery.

### Consequences
- Weekly `pg_dump` backup to server storage.
- 4-week retention (4 backup files).
- Risk: up to 6 days data loss if server fails mid-week.
- Risk: backup lost if server fails (no offsite copy initially).
- Recommendation flagged for security review and client reconsideration.

---

## ADR-013: No Auto-Logout (Pending Security Review)

**Status:** Accepted (pending security review)
**Date:** November 2026

### Context
The client requested no automatic inactivity logout. Employees remain logged in until manual logout. This creates a risk on shared POS devices.

### Decision
**No automatic inactivity logout.** Subject to security review, especially for shared devices.

### Rationale
- Client decision: employees should not be logged out during active work.
- POS workflow may have gaps between sales; auto-logout would be disruptive.
- Manual logout is the user's responsibility.

### Consequences
- Session persists until manual logout.
- Risk on shared devices: User A walks away, User B uses the system under A's identity.
- Mitigation recommendations:
  - Step-up authentication for sensitive operations (cash handover, closing, returns).
  - Prominent "switch user" button.
  - Physical device assignment to individuals.
- Must be reviewed before go-live, especially for branches with shared POS devices.

---

## ADR-014: Single Selling Price for Retail and Wholesale (No Tiered Pricing)

**Status:** Accepted
**Date:** November 2026

### Context
The system supports both retail and wholesale sales. Originally, separate wholesale pricing or quantity-based tiers were considered.

### Decision
**Single standard selling price for both retail and wholesale.** Sale type (retail/wholesale) is recorded for reporting but does not affect pricing. No tiered or quantity-based pricing.

### Rationale
- Simplifies pricing management.
- Client's current business model uses the same price for both channels.
- Wholesale vs retail distinction is for reporting only.
- Can be extended in the future if the business needs separate pricing.

### Consequences
- One standard price and one minimum price per product (or product-size).
- No wholesale price list or quantity tiers.
- Sale type is a tag on the invoice, not a pricing determinant.
- Future extension would require adding a wholesale price field or price list.

---

## ADR-015: Blade + Livewire Frontend (Not SPA)

**Status:** Accepted
**Date:** November 2026

### Context
The system needs a responsive, interactive UI for POS, inventory management, and reporting. Options considered: Single Page Application (Vue/React), server-rendered Blade + Livewire, or Inertia.js with Vue.

### Decision
Use **Blade + Livewire** for the frontend. Server-rendered with Livewire for interactivity. Tailwind CSS for styling. Alpine.js for lightweight client-side interactions.

### Rationale
- Livewire provides reactive UI without a separate JavaScript SPA.
- Server-rendered pages are simpler to develop and maintain for a small team.
- No need for a separate API-first architecture for the internal UI.
- Tailwind CSS provides utility-first styling with small production footprint.
- Offline POS uses a dedicated sync API; the main UI does not need to work offline.
- Faster development cycle than a full SPA.

### Consequences
- All UI interactions go through Livewire (server round-trip for most actions).
- Offline POS is a separate concern (browser-based cache, not Livewire).
- No separate frontend build pipeline for a SPA.
- Future mobile app would use the REST API.

---

## ADR-016: Database Queue Driver Initially (Redis Later)

**Status:** Accepted
**Date:** November 2026

### Context
The system needs background job processing for scheduled reports, summary table updates, alert detection, and email sending. Redis is the recommended queue driver for Laravel but adds an infrastructure dependency.

### Decision
Use **database queue driver initially.** Switch to Redis when queue volume grows or multiple workers are needed.

### Rationale
- Reduces infrastructure dependencies on day one (no Redis server required).
- Database queue is sufficient for the initial 1-10 concurrent users.
- Migration to Redis is a configuration change, not a code change.
- Redis can be introduced when queue depth becomes a bottleneck.

### Consequences
- Jobs stored in the database (slightly slower than Redis).
- Single worker process initially (Supervisor).
- When switching to Redis: update `.env` `QUEUE_CONNECTION=redis`, install Redis, restart workers.

---

## ADR-017: Spatie Laravel Permission for RBAC

**Status:** Accepted
**Date:** November 2026

### Context
The system needs role-based access control with roles, permissions, and the ability to customize per-user permission overrides.

### Decision
Use **Spatie Laravel Permission** package for role and permission management, extended with custom logic for branch scoping and role switching.

### Rationale
- Well-maintained, widely-used package in the Laravel ecosystem.
- Provides roles, permissions, and role-permission assignment out of the box.
- Supports direct user-permission grants (for custom overrides).
- We extend it with:
  - Branch scoping (global scope on branch-sensitive models).
  - Active role concept (for multi-role users with role switching).
  - Sensitive data restrictions (independent of role permissions).

### Consequences
- Roles and permissions stored in dedicated tables (managed by the package).
- Custom middleware for active role enforcement.
- Custom global scope for branch filtering.
- Custom logic for sensitive column hiding.

---

## ADR-018: Search vs. Report Boundary for Date Restrictions

**Status:** Accepted
**Date:** November 2026

### Context
Non-admin roles are restricted to "today only" for aggregate reports. However, they need to look up historical transactions for operational purposes (e.g., verifying an original sale for a return processed days later).

### Decision
Implement a clear **search vs. report boundary**:
- **Transactional search** (single-record lookup by name, phone, invoice number, barcode, or serial): allowed any date for all roles.
- **Aggregate/date-wise reports** (daily totals, cashbook, salesperson performance, customer lists): today-only for non-admin roles.

### Rationale
- Salespersons and supervisors need to process returns for sales from prior days (within the 7-day return window).
- Searching for a specific customer or invoice is operational, not analytical.
- Aggregate reports reveal business performance patterns, which are restricted.
- The boundary is clear: single-record lookup = any date; aggregate analysis = today only.

### Consequences
- Search endpoints accept any date but return single records (or limited results).
- Report endpoints enforce today-only for non-admin roles.
- UI clearly separates search (always available) from reports (restricted).
- Audit logs track all searches (for visibility into who looked up what).

## ADR-019: Barcode Generation Library — picqer/php-barcode-generator

**Status:** Accepted
**Date:** November 2026

### Context
Phase 2 requires rendering Code 128 barcode images for garment labels (WF-2). The system generates barcodes during goods receiving (`NND-{serial}`, e.g. `NND-25-00001`) and must print them as physical labels on thermal printers (Xprinter XP-420B, G-Printer GP-3120TUC, Rongta RP400H) via the browser print dialog. A PHP library is needed to render the barcode symbol itself; the print transport (browser CSS `@page` directives) is a separate decision implemented in Session 2.7.

### Decision
Use `picqer/php-barcode-generator` (Composer package `picqer/php-barcode-generator`, MIT/LGPL-3.0) for Code 128 barcode rendering. Labels render as **base64-encoded SVG** — not PNG — and are embedded inline via `data:image/svg+xml;base64,` URIs. Quiet zones of 10 modules (Code 128 minimum: 10x the narrowest bar) are injected on both sides of every symbol.

*Note: the Phase 2 session plan referred to this library as "picqer/php-barcode"; the correct Composer package name is `picqer/php-barcode-generator`. Decision recorded as ADR-019 because ADR-018 (Search vs. Report Boundary) was already assigned.*

### Rationale
- Lightweight, no bloated dependencies; purpose-built for 1D barcodes including Code 128 with automatic code-set switching.
- SVG output is vector: labels scale crisply to any thermal-printer DPI through the browser print dialog without pixelation, and rendering requires no GD image extension on the server.
- Deterministic output (no timestamps/ids in the SVG) makes rendering testable.
- The library's `Barcode`/`BarcodeBar` value objects let us inject quiet-zone bars cleanly, satisfying scanner boundary requirements.

### Consequences
- `LabelPrintingService` wraps the library; symbology is configurable via `barcode.barcode_symbology` (default CODE128) but only Code 128 is implemented in Phase 2.
- Barcode images live only in generated HTML/print output — nothing is stored on disk, keeping the system stateless for labels.
- If a future printer requires raster output (ESC/POS direct transport), a PNG renderer from the same library can be added behind the same service interface.

---

## Summary of ADRs

| ADR | Title | Status |
|---|---|---|
| ADR-001 | Laravel Modular Monolith Architecture | Accepted |
| ADR-002 | PostgreSQL as Primary Database | Accepted |
| ADR-003 | Garment-Level Inventory with Permanent Barcode + Fiscal-Year Serial | Accepted |
| ADR-004 | Append-Only Movement Ledger with Current-Stock Projection | Accepted |
| ADR-005 | Offline POS with Local Cache and Sync-Based Conflict Resolution | Accepted |
| ADR-006 | Transfer-Based Online Order Fulfillment | Accepted |
| ADR-007 | Five-Role RBAC with Role Switching | Accepted |
| ADR-008 | No Automatic Reservation Expiry — Manual Release Only | Accepted |
| ADR-009 | Manual Cash Discrepancy Resolution by Admin Only | Accepted |
| ADR-010 | No Tax Line on Invoice (Pending Legal Review) | Accepted (pending review) |
| ADR-011 | Courier as Free-Text Notes | Accepted |
| ADR-012 | Weekly On-Server Backup (Daily Offsite Recommended) | Accepted (with recommendation) |
| ADR-013 | No Auto-Logout (Pending Security Review) | Accepted (pending review) |
| ADR-014 | Single Selling Price for Retail and Wholesale | Accepted |
| ADR-015 | Blade + Livewire Frontend | Accepted |
| ADR-016 | Database Queue Driver Initially | Accepted |
| ADR-017 | Spatie Laravel Permission for RBAC | Accepted |
| ADR-018 | Search vs. Report Boundary for Date Restrictions | Accepted |
| ADR-019 | Barcode Generation Library — picqer/php-barcode-generator | Accepted |

---

*End of Architecture Decision Records*
