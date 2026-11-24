# Development Roadmap
## Nondon — Multi-Branch Women's Dress Retail & Wholesale ERP

**Project:** Nondon
**Version:** 1.0
**Date:** 24 November 2026
**Status:** Design document — pending approval

---

## 1. Roadmap Overview

### 1.1 Approach

The system is developed in **phased modules**, with each phase building on the previous one. The core inventory and identity foundation is built first, followed by operational modules (purchasing, transfers, sales), then financial modules (cash, expenses, payments), then reporting and optimization.

### 1.2 Phase summary

| Phase | Duration (estimated) | Modules | Goal |
|---|---|---|---|
| Phase 0 | 1-2 weeks | Project setup | Laravel project, modules, database, auth |
| Phase 1 | 3-4 weeks | Foundation | Identity, Organization, Catalog, Inventory core |
| Phase 2 | 3-4 weeks | Purchasing & Inventory | Purchasing, goods receiving, barcode generation |
| Phase 3 | 4-5 weeks | Sales & Transfers | POS, transfers, stock visibility |
| Phase 4 | 3-4 weeks | Online Operations | Online orders, multi-branch sourcing, courier |
| Phase 5 | 3-4 weeks | Returns & Damaged Stock | Returns, exchanges, defective handling |
| Phase 6 | 3-4 weeks | Cash & Finance | Cash management, expenses, supplier payments |
| Phase 7 | 2-3 weeks | Stock Control | Stock counts, discrepancies, adjustments |
| Phase 8 | 2-3 weeks | Offline POS | Offline mode, sync, conflict resolution |
| Phase 9 | 2-3 weeks | Reporting & Notifications | Reports, dashboards, scheduled emails, alerts |
| Phase 10 | 2-3 weeks | Hardening & Deploy | Testing, security review, deployment, training |

**Total estimated duration:** 28-39 weeks (7-10 months)

### 1.3 Dependencies

```
Phase 0 (Setup)
    │
    ▼
Phase 1 (Foundation: Identity, Catalog, Inventory)
    │
    ├──► Phase 2 (Purchasing & Barcode)
    │        │
    │        ▼
    │    Phase 3 (POS & Transfers)
    │        │
    │        ├──► Phase 4 (Online Orders)
    │        │
    │        ├──► Phase 5 (Returns & Damaged)
    │        │
    │        └──► Phase 6 (Cash & Finance)
    │                │
    │                ▼
    │            Phase 7 (Stock Control)
    │                │
    │                ▼
    │            Phase 8 (Offline POS)
    │                │
    │                ▼
    │            Phase 9 (Reporting)
    │                │
    │                ▼
    │            Phase 10 (Hardening)
    │
    └──► (All phases depend on Phase 1)
```

---

## 2. Phase Details

### Phase 0: Project Setup (1-2 weeks)

**Goal:** Initialize the Laravel project with modular structure, database, and authentication.

**Tasks:**
1. Create Laravel project.
2. Configure PostgreSQL database connection.
3. Set up module directory structure (`app/Modules/`).
4. Install and configure Spatie Laravel Permission.
5. Install and configure Livewire, Tailwind CSS, Alpine.js.
6. Set up base layout, navigation, theme.
7. Configure authentication (login, logout, password reset).
8. Set up testing framework (Pest PHP).
9. Configure CI/CD pipeline (GitHub Actions).
10. Set up development environment (Docker or local).

**Deliverables:**
- Working Laravel application with login.
- Module directory structure.
- Database connection configured.
- CI pipeline running tests.

**Exit criteria:**
- User can log in and see a dashboard.
- Tests pass.
- Database migrations run cleanly.

---

### Phase 1: Foundation (3-4 weeks)

**Goal:** Build the core identity, organization, catalog, and inventory foundation.

**Modules:**
- Identity & Access
- Organization (Branches, Fiscal Years, Stock Locations)
- Catalog (Products, Sizes, Prices)
- Inventory (Garments, Movements, Current Stock Projection)
- Audit

**Tasks:**
1. **Identity:**
   - User model with branch assignment.
   - Role and permission management (5 roles).
   - Login security (lockout, attempt logging).
   - Role switching UI.
   - Session management (no auto-logout).

2. **Organization:**
   - Branch management (Main, B1, B2, B3, Online).
   - Fiscal year management (create, close, carry-forward).
   - Stock location management.

3. **Catalog:**
   - Product CRUD (admin only).
   - Product sizes management.
   - Price management (standard + minimum, effective-dated).
   - Product activation/deactivation.

4. **Inventory:**
   - Garment model (internal ID, barcode, serial, status).
   - Inventory movements table (append-only).
   - Current stock projection table.
   - Movement history query.
   - Stock availability query (single branch and cross-branch).

5. **Audit:**
   - Audit log infrastructure.
   - Audit middleware/listeners.
   - Audit log query (admin, supervisor today-only).

**Deliverables:**
- Users, roles, branches, fiscal years, products, prices can be managed.
- Garment records can be created (manually for testing).
- Movement history is tracked.
- Audit logging works for all actions.
- Stock availability can be queried across branches.

**Exit criteria:**
- All Phase 1 tests pass.
- Admin can create users, branches, products, prices.
- Inventory queries return correct results.
- Audit log captures all actions.

---

### Phase 2: Purchasing & Barcode Generation (3-4 weeks)

**Goal:** Enable purchasing, goods receiving, and garment identity generation.

**Modules:**
- Purchasing (Suppliers, Purchases, Goods Receipts)
- Barcode generation and label printing

**Tasks:**
1. **Supplier management** (admin only):
   - CRUD for suppliers.
   - Supplier outstanding balance tracking.

2. **Purchase management** (admin only):
   - Create purchase orders.
   - Add purchase items (product, size, quantity, cost).
   - Confirm purchases.
   - Purchase list and detail views.

3. **Goods receiving:**
   - Receive confirmed purchase.
   - Enter received quantity.
   - System generates garment identities (UUID, barcode, fiscal-year serial).
   - Advisory locking for serial generation.
   - Create goods receipt record.
   - Update purchase status.

4. **Barcode label printing:**
   - Label generation (Code 128 barcode).
   - Label formats: hang tags (50x30mm, 75x50mm), stickers (1"x1.5", 1"x2.625").
   - Printer support: Xprinter XP-420B, G-Printer GP-3120TUC, Rongta RP400H.
   - Print via browser (CSS print media) or ESC/POS/TSCL bridge.

**Deliverables:**
- Suppliers can be managed.
- Purchases can be created, confirmed, received.
- Garments are auto-generated with correct serial format and barcode.
- Barcode labels can be printed.
- Purchase cost is restricted to admin.

**Exit criteria:**
- Full purchasing workflow tested.
- Serial format `YY-NNNNN` generated correctly.
- Barcode labels print on supported printers.
- Purchase cost hidden from non-admin users.

---

### Phase 3: Sales (POS) & Transfers (4-5 weeks)

**Goal:** Enable physical POS sales and inter-branch transfers.

**Modules:**
- Sales (POS)
- Transfers
- Customers

**Tasks:**
1. **Customer management:**
   - Customer creation (phone mandatory, name optional).
   - Customer search by phone.
   - Duplicate detection.
   - Customer history.

2. **POS sales (online mode):**
   - POS interface (Livewire).
   - Barcode scanning and manual search.
   - Cart management (add, remove, replace items).
   - Discount application (minimum price enforcement).
   - Payment collection (multiple methods).
   - Atomic sale completion (transaction + row locking).
   - Receipt printing (thermal 58mm/80mm, A4, no tax line).
   - Completed sale correction (via returns, not cancellation).

3. **Transfers:**
   - Transfer request creation (supervisor only).
   - Implicit garment reservation on request creation.
   - Approval workflow (source supervisor/admin).
   - Dispatch (mark in-transit).
   - Receiving (confirm quantity, record discrepancies).
   - Transfer history.

4. **Stock visibility:**
   - Cross-branch stock view (read-only, no supplier/cost).
   - Reservation count display per branch.

**Deliverables:**
- POS can complete sales with barcode scan, payment, receipt.
- Transfers can be requested, approved, dispatched, received.
- Cross-branch stock is visible.
- Customers are managed with phone-based lookup.

**Exit criteria:**
- POS sale workflow tested end-to-end.
- Concurrent sale test (two users, same garment — one fails).
- Transfer lifecycle tested.
- Receipt prints without tax line.
- Cross-branch stock view works (without supplier info).

---

### Phase 4: Online Orders & Courier (3-4 weeks)

**Goal:** Enable online order management with multi-branch sourcing.

**Modules:**
- Online Orders
- Courier Reconciliation

**Tasks:**
1. **Online order management:**
   - Order creation (online team).
   - Order confirmation (triggers transfer requests).
   - Multi-branch sourcing (one order, multiple source branches).
   - Transfer tracking per order item.
   - Courier dispatch (free-text courier name + tracking).
   - Delivery confirmation.
   - Order cancellation (before and after dispatch).
   - Manual reservation release.

2. **Online channel stock:**
   - Online channel as transient stock location.
   - Receive transfers from physical branches.
   - Dispatch to customer.

3. **Courier COD reconciliation:**
   - Settlement entry (online supervisor).
   - Line-by-line matching.
   - Discrepancy flags (unmatched, short, excess, duplicate).
   - COD settlement recognizes revenue.

4. **Online payment tracking:**
   - Advance payment recording.
   - COD pending status.
   - Payment status separate from order status.

**Deliverables:**
- Online orders can be created, confirmed, sourced from multiple branches.
- Transfers to online channel work.
- Courier dispatch recorded (free-text).
- COD settlement reconciliation works.
- Online channel cash account tracks settlements.

**Exit criteria:**
- Multi-branch online order tested (2+ source branches).
- COD settlement reconciliation tested.
- Order cancellation before and after dispatch tested.
- Manual reservation release tested.

---

### Phase 5: Returns & Damaged Stock (3-4 weeks)

**Goal:** Enable customer returns, exchanges, and defective garment handling.

**Modules:**
- Returns
- Damaged Stock

**Tasks:**
1. **Physical returns:**
   - Return initiation (salesperson/cashier).
   - Original sale lookup (by phone, invoice, barcode).
   - Return eligibility check (7-day period, original branch only).
   - Duplicate return prevention.
   - Refund approval (within/above limit).
   - Refund processing (all methods: cash, bKash, bank, Nagad).
   - Return receipt printing.

2. **Online returns (two paths):**
   - Path A: Online channel has cash — immediate refund, transfer garment back.
   - Path B: Online channel no cash — transfer garment, original branch refunds.
   - Garment routes back to original physical branch.

3. **Exchanges:**
   - Price difference calculation.
   - Replacement garment selection.
   - Linked to original sale.

4. **Defective garment handling:**
   - Damage report (immediate sale block).
   - Supervisor verification.
   - Transfer to Main Branch for inspection.
   - Disposition decision (admin): repair, supplier return, clearance, dispose.
   - Supplier rejection handling.

**Deliverables:**
- Physical returns work with 7-day check and original-branch enforcement.
- Online returns work with both refund paths.
- Defective garments are blocked immediately and routed to Main Branch.
- Disposition decisions are recorded.

**Exit criteria:**
- Return workflow tested (within period, cross-branch rejected, duplicate prevented).
- Online return Path A and Path B tested.
- Defective garment blocking tested (immediate block on report).
- Disposition workflow tested.

---

### Phase 6: Cash & Finance (3-4 weeks)

**Goal:** Enable cash management, expenses, and supplier payments.

**Modules:**
- Cash & Payment
- Expenses
- Supplier Payments

**Tasks:**
1. **Cash accounts:**
   - Salesperson cash account (per user).
   - Branch cash account (per branch).
   - Online channel cash account.
   - Cash transaction ledger (append-only).
   - Balance queries.

2. **Cash handovers:**
   - Salesperson initiates (enters amount).
   - Supervisor confirms (enters counted amount).
   - Match/mismatch handling.
   - Dual confirmation required.

3. **Daily cash closing:**
   - Expected cash calculation.
   - Counted cash entry.
   - Discrepancy recording (supervisor records factual count).
   - Admin investigation and resolution.

4. **Payment methods:**
   - Configurable methods (cash, bKash, Nagad, bank, card).
   - Multiple methods per sale.

5. **Expense management:**
   - Expense submission (supervisor).
   - Expense approval (admin).
   - Branch cash account debit on approval.

6. **Supplier payments:**
   - Payment recording (admin).
   - Link to purchase/supplier.
   - Duplicate detection.
   - Outstanding balance update.

**Deliverables:**
- Cash accounts track balances correctly.
- Handovers require dual confirmation.
- Daily closing calculates expected cash and records discrepancies.
- Expenses can be submitted and approved.
- Supplier payments update outstanding balances.

**Exit criteria:**
- Cash handover match and mismatch tested.
- Daily closing with zero discrepancy and with discrepancy tested.
- Discrepancy resolution by admin tested.
- Expense approval flow tested.
- Supplier payment with duplicate detection tested.

---

### Phase 7: Stock Control (2-3 weeks)

**Goal:** Enable physical stock counts, discrepancies, and adjustments.

**Modules:**
- Stock Count
- Damaged Stock (disposition refinement)

**Tasks:**
1. **Stock counts:**
   - Full branch count.
   - Selective count (by product/size/garment).
   - Ad hoc count.
   - Count lines (expected vs physical).
   - Discrepancy recording (mandatory reason).

2. **Stock count freeze:**
   - Full branch freeze.
   - Selective freeze.
   - Block stock-mutating operations during freeze.
   - In-progress transaction handling.

3. **Discrepancy and adjustment:**
   - Supervisor submits discrepancies.
   - Admin approves/rejects adjustments.
   - Only approved adjustments change official inventory.

4. **Missing garment handling:**
   - Missing report (from count or transfer discrepancy).
   - Recovery workflow (supervisor verifies, admin approves).
   - History preservation (original missing report linked to recovery).

5. **Misplaced/unidentified garment:**
   - Scan barcode at wrong branch.
   - Investigation status.
   - Admin approves location correction.

6. **Inventory alerts:**
   - Individual discrepancy alerts.
   - Repeated pattern alerts (same branch, same employee).
   - Configurable thresholds and recipients.

**Deliverables:**
- Stock counts can be initiated, counted, submitted, approved.
- Freeze blocks stock transactions correctly.
- Discrepancies require reasons and admin approval.
- Missing garment recovery preserves history.
- Alerts are generated for patterns.

**Exit criteria:**
- Full stock count workflow tested.
- Freeze enforcement tested (POS sale blocked during freeze).
- Discrepancy approval tested.
- Missing garment recovery tested.
- Alert pattern detection tested.

---

### Phase 8: Offline POS (2-3 weeks)

**Goal:** Enable POS operation during internet outages.

**Tasks:**
1. **Local cache:**
   - IndexedDB setup.
   - Cache product catalog, prices, stock snapshot, customer index.
   - Cache refresh on login and sync.

2. **Offline sale:**
   - Detect offline state.
   - Allow sale from cache.
   - Store sale in offline queue (with client UUID).
   - Print receipt from local data.

3. **Sync engine:**
   - Auto-sync on reconnection.
   - Manual sync button.
   - Push: send offline sales to server.
   - Pull: refresh cache with updated data.
   - Idempotent push (UUID deduplication).

4. **Conflict resolution:**
   - Detect garment already sold (server-side check).
   - Flag conflicts for admin.
   - Admin resolution queue.
   - Resolution as correction transaction.

5. **Cache security:**
   - Clear cache on logout.
   - No sensitive data in cache.
   - Per-device, per-branch cache.

**Deliverables:**
- POS works without internet.
- Offline sales sync correctly on reconnection.
- Conflicts are detected and escalated.
- Cache is cleared on logout.

**Exit criteria:**
- Offline sale and sync tested end-to-end.
- Conflict scenario tested (offline sale + online sale of same garment).
- Idempotent push tested (same UUID pushed twice).
- Cache clearing on logout tested.

---

### Phase 9: Reporting & Notifications (2-3 weeks)

**Goal:** Enable role-appropriate reporting and scheduled email reports.

**Modules:**
- Reporting
- Notifications

**Tasks:**
1. **Reports:**
   - Daily cash report (per branch).
   - Daily sales report (per branch, per salesperson).
   - Stock summary report.
   - Transfer history report.
   - Returns report.
   - Damaged stock report.
   - Courier settlement report.
   - Salesperson performance (today only for non-admin).
   - Audit log report (admin: all; supervisor: own branch today).
   - Monthly ledger report (admin).

2. **Dashboards:**
   - Admin dashboard (all-branch overview).
   - Supervisor dashboard (own branch today).
   - Salesperson dashboard (own today).
   - Online supervisor dashboard (online channel today).

3. **Scheduled email reports:**
   - Configure recipient, schedule, report type.
   - Daily cash report email.
   - Monthly ledger report email.
   - PDF/HTML email attachment.

4. **Inventory alerts:**
   - Alert configuration.
   - In-app notifications.
   - Email alerts for critical issues.

5. **Search vs. report boundary:**
   - Search endpoints (any date, single record).
   - Report endpoints (today only for non-admin).

**Deliverables:**
- All key reports generate correctly.
- Dashboards show role-appropriate data.
- Scheduled emails send at configured times.
- Alerts trigger for configured patterns.
- Search works any date; reports respect today-only restriction.

**Exit criteria:**
- Daily cash report email tested.
- Monthly ledger report tested.
- Role-based report access enforced (supervisor cannot see historical).
- Search vs. report boundary tested.
- Alert pattern detection tested.

---

### Phase 10: Hardening & Deployment (2-3 weeks)

**Goal:** Final testing, security review, deployment, and training.

**Tasks:**
1. **Testing:**
   - Complete test suite execution.
   - Concurrency tests.
   - Permission tests.
   - Acceptance criteria verification.
   - Performance testing.
   - User acceptance testing (UAT).

2. **Security review:**
   - No-auto-logout decision review.
   - Backup strategy review (weekly vs. daily).
   - Tax compliance review (no-tax invoice).
   - Penetration testing (basic).
   - Security checklist verification.

3. **Deployment:**
   - Production server setup.
   - Nginx, PHP-FPM, PostgreSQL configuration.
   - SSL certificate.
   - Backup configuration.
   - Queue worker setup.
   - Scheduler setup.

4. **Data migration (if applicable):**
   - Opening stock entry.
   - Customer data migration.
   - Product catalog migration.

5. **Training:**
   - Admin training (full system).
   - Supervisor training (branch operations).
   - Salesperson training (POS, customer, cash).
   - Online team training (online orders, courier).

6. **Go-live:**
   - Final smoke test.
   - Monitor for issues.
   - Support during first week.

**Deliverables:**
- All tests pass.
- Security review completed.
- Production environment deployed.
- Users trained.
- System goes live.

**Exit criteria:**
- All acceptance criteria met.
- Security checklist verified.
- Production deployment successful.
- Smoke test passes.
- Users trained and supported.

---

## 3. Module Development Order (Detailed)

### Priority 1 — Critical Path (must build first)
1. Identity & Access (Phase 0-1)
2. Organization (Phase 1)
3. Catalog (Phase 1)
4. Inventory core (Phase 1)
5. Audit (Phase 1)
6. Purchasing & Barcode (Phase 2)
7. Sales POS (Phase 3)
8. Transfers (Phase 3)
9. Customers (Phase 3)

### Priority 2 — Core Business Operations
10. Online Orders (Phase 4)
11. Courier Reconciliation (Phase 4)
12. Returns (Phase 5)
13. Damaged Stock (Phase 5)
14. Cash & Payment (Phase 6)
15. Expenses (Phase 6)
16. Supplier Payments (Phase 6)

### Priority 3 — Control & Optimization
17. Stock Count (Phase 7)
18. Offline POS (Phase 8)
19. Reporting (Phase 9)
20. Notifications (Phase 9)

### Priority 4 — Hardening
21. Performance optimization (Phase 10)
22. Security hardening (Phase 10)
23. Deployment (Phase 10)
24. Training (Phase 10)

---

## 4. Risk Management

### 4.1 Key risks

| Risk | Probability | Impact | Mitigation |
|---|---|---|---|
| Concurrency bugs in POS sale | Medium | High | Thorough concurrency testing; row-level locking |
| Offline sync conflicts | High | Medium | Conflict detection and admin resolution workflow |
| Serial number duplication | Low | Critical | Advisory locking; unique constraint |
| Performance degradation at scale | Medium | High | Current-stock projection; summary tables; partitioning |
| Permission bypass | Low | Critical | Server-side enforcement; policy tests; security review |
| Data loss (backup failure) | Medium | Critical | Backup testing; daily offsite recommendation |
| User adoption resistance | Medium | Medium | Training; simple UI for salesperson role |

### 4.2 Contingency plan

- If concurrency issues arise: add more tests, consider optimistic locking with retry.
- If offline sync is too complex: defer offline POS to a later release; require internet.
- If performance is an issue: add Redis cache, read replica, earlier than planned.
- If security review finds issues: address before go-live; delay go-live if necessary.

---

## 5. Milestone Summary

| Milestone | Target Week | Phase Complete |
|---|---|---|
| M1: Project setup complete | Week 2 | Phase 0 |
| M2: Foundation (users, products, inventory) working | Week 6 | Phase 1 |
| M3: Purchasing & barcode generation working | Week 10 | Phase 2 |
| M4: POS sales & transfers working | Week 15 | Phase 3 |
| M5: Online orders working | Week 19 | Phase 4 |
| M6: Returns & damaged stock working | Week 23 | Phase 5 |
| M7: Cash & finance working | Week 27 | Phase 6 |
| M8: Stock control working | Week 30 | Phase 7 |
| M9: Offline POS working | Week 33 | Phase 8 |
| M10: Reporting working | Week 36 | Phase 9 |
| M11: Go-live | Week 39 | Phase 10 |

**Note:** Duration estimates are for a small development team (1-2 developers). Adjust based on team size and experience.

---

## 6. Post-Launch Roadmap (Future Phases)

### Future Phase A: Mobile App
- Native mobile app for supervisors (dashboard, approvals).
- POS tablet optimization.

### Future Phase B: Advanced Reporting
- Custom report builder.
- Business intelligence dashboards.
- Trend analysis.

### Future Phase C: Integration
- Courier API integration (when courier is formalized).
- Payment gateway integration (bKash, Nagad APIs).
- E-commerce platform integration (Facebook, website).

### Future Phase D: Advanced Inventory
- Minimum stock thresholds and auto-replenishment alerts.
- Demand forecasting.
- Seasonal planning.

### Future Phase E: HR Module
- Attendance tracking (self check-in, GPS, biometric).
- Leave management.
- Payroll processing.
- Employee loans and advances.

### Future Phase F: Accounting
- Full double-entry accounting.
- General ledger.
- Balance sheet and profit & loss.
- Tax calculation and filing.

---

## 7. Development Conventions

### 7.1 Git workflow

- `main` branch: production-ready code.
- `develop` branch: integration branch.
- Feature branches: `feature/{module}-{description}`.
- Pull requests required for merge to `develop`.
- CI must pass before merge.

### 7.2 Code review

- All PRs reviewed by at least one other developer.
- Critical modules (Inventory, Sales, Cash) reviewed by lead developer.
- Security-sensitive changes reviewed by security-focused reviewer.

### 7.3 Testing requirements

- Every PR includes tests for new functionality.
- Tests must pass before merge.
- Coverage threshold: 80% for critical modules.
- Concurrency tests for inventory and sales modules.

### 7.4 Documentation

- Code comments for complex business logic.
- API endpoints documented (this document + OpenAPI spec in future).
- Module README files.
- Change log per release.

---

## 8. Team Requirements

### 8.1 Minimum team

| Role | Count | Responsibilities |
|---|---|---|
| Lead Developer | 1 | Architecture, critical modules, code review |
| Backend Developer | 1 | Module implementation, tests |
| Frontend/UI Developer | 1 (part-time or shared) | Livewire components, Blade views, CSS |

### 8.2 Recommended team (for faster delivery)

| Role | Count |
|---|---|
| Lead Developer / Architect | 1 |
| Backend Developers | 2 |
| Frontend Developer | 1 |
| QA / Tester | 1 (part-time) |
| DevOps (for deployment) | 1 (part-time or contracted) |

---

## 9. Success Criteria

### 9.1 Go-live criteria

- All Phase 1-10 modules implemented and tested.
- All acceptance criteria (AC-1 to AC-50) met.
- Security review completed.
- Production environment deployed and smoke-tested.
- Users trained.
- Backup tested.

### 9.2 Post-launch success metrics

- System uptime: 99%+ (excluding planned maintenance).
- POS sale completion time: < 500ms average.
- No data integrity issues (no duplicate sales, no missing garments).
- User satisfaction: salespersons find the UI simple and fast.
- Admin has all reports needed for management and tax compliance.

---

*End of Development Roadmap*
