# Nondon
## Multi-Branch Women's Dress Retail & Wholesale ERP

A comprehensive ERP system for managing a multi-branch women's dress retail and wholesale business with garment-level inventory tracking, branch transfers, POS sales, online order management, cash management, and management reporting.

---

## Project Overview

**Client:** Mr. X's multi-branch women's dress retail and wholesale business
**Business type:** Retail & Wholesale
**Branches:** Main Branch (head office + central warehouse + retail) + 3 physical branches (B1, B2, B3) + Online Sales channel
**Architecture:** Laravel Modular Monolith
**Database:** PostgreSQL 15+
**Fiscal Year:** July 1 to June 30 (notation: FY 25_26)

---

## Documentation

This repository contains the complete project documentation. All documents should be reviewed and approved before development begins.

| Document | Description | Status |
|---|---|---|
| [01-business-requirements.md](01-business-requirements.md) | Consolidated Business Requirements Document (BRD v1.6) covering all confirmed business decisions from discovery rounds 1-5 | Draft — pending client approval |
| [02-architecture.md](02-architecture.md) | Laravel modular monolith architecture, module decomposition, offline POS architecture, event-driven communication | Design document |
| [03-database-design.md](03-database-design.md) | PostgreSQL schema, ER relationships, constraints, indexes, partitioning strategy, concurrency control | Design document |
| [04-inventory-workflow.md](04-inventory-workflow.md) | 27 detailed operational workflows covering POS, transfers, online orders, returns, cash, stock counts, damaged stock | Design document |
| [05-security-design.md](05-security-design.md) | RBAC, 5-role permission matrix, audit logging, authentication, data protection, security checklist | Design document |
| [06-performance-strategy.md](06-performance-strategy.md) | Indexing, current-stock projection, summary tables, caching, offline cache, monitoring, capacity planning | Design document |
| [07-api-documentation.md](07-api-documentation.md) | REST API endpoints for all modules, sync API, authentication, response formats | Design document |
| [08-testing-strategy.md](08-testing-strategy.md) | Test pyramid, unit/integration/concurrency tests, 50 acceptance criteria, test data management | Design document |
| [09-deployment-guide.md](09-deployment-guide.md) | Nginx + PHP-FPM + PostgreSQL setup, SSL, backup, queue workers, security hardening | Design document |
| [10-architecture-decisions.md](10-architecture-decisions.md) | 18 Architecture Decision Records (ADRs) documenting key design choices and rationale | Design document |
| [11-development-roadmap.md](11-development-roadmap.md) | 10-phase development plan with milestones, dependencies, and team requirements | Design document |

---

## Key Business Decisions

### Garment Identity
- Every physical garment is individually tracked with three identifiers:
  - **Internal garment ID** (UUID) — immutable, never reused
  - **Permanent barcode** — globally unique, never reused
  - **Fiscal-year serial** — format `YY-NNNNN` (e.g., `25-00001`), global sequence per fiscal year, restarts each July

### Roles (5 roles)
1. **Main Branch Admin** — full authority, also sells
2. **Branch Supervisor** — own branch operations, also sells
3. **Salesperson** — base selling, customer lookup, individual cash
4. **Online Sales Team** — online order management
5. **Online Sales Supervisor** — full online-channel authority (branch-equivalent)

### Critical Design Principles
- All roles can sell (supervision is additional permissions, not a replacement)
- Transfer requests implicitly reserve garments at the source branch
- Online channel is a transient stock-holding branch-equivalent
- No sell-here-pick-there (demand-request transfer required)
- Returns only at original selling branch (7-day period)
- No automatic reservation expiry (manual release only)
- POS operates offline with sync-based conflict resolution
- "Today only" rule applies to aggregate reports; transactional search allowed any date
- No tax line on invoice (pending legal review)
- Meaningful business audit logs retained long-term

---

## Technology Stack

| Component | Technology |
|---|---|
| Backend | Laravel (latest stable) |
| Language | PHP 8.3+ |
| Database | PostgreSQL 15+ |
| Frontend | Blade + Livewire |
| UI Framework | Tailwind CSS + Alpine.js |
| Auth | Laravel built-in |
| RBAC | Spatie Laravel Permission |
| Cache | File/database (Redis later) |
| Queue | Database driver (Redis later) |
| Testing | Pest PHP |
| Deployment | Nginx + PHP-FPM |

---

## Development Phases

| Phase | Duration | Modules |
|---|---|---|
| Phase 0 | 1-2 weeks | Project setup |
| Phase 1 | 3-4 weeks | Foundation (Identity, Catalog, Inventory) |
| Phase 2 | 3-4 weeks | Purchasing & Barcode |
| Phase 3 | 4-5 weeks | POS Sales & Transfers |
| Phase 4 | 3-4 weeks | Online Orders & Courier |
| Phase 5 | 3-4 weeks | Returns & Damaged Stock |
| Phase 6 | 3-4 weeks | Cash & Finance |
| Phase 7 | 2-3 weeks | Stock Control |
| Phase 8 | 2-3 weeks | Offline POS |
| Phase 9 | 2-3 weeks | Reporting & Notifications |
| Phase 10 | 2-3 weeks | Hardening & Deployment |

**Total estimated duration:** 28-39 weeks (7-10 months)

See [11-development-roadmap.md](11-development-roadmap.md) for full details.

---

## Repository Structure

```
nondon/
├── README.md                          (this file)
├── 01-business-requirements.md        (BRD v1.6)
├── 02-architecture.md                 (Architecture design)
├── 03-database-design.md              (PostgreSQL schema)
├── 04-inventory-workflow.md           (27 operational workflows)
├── 05-security-design.md              (Security & RBAC)
├── 06-performance-strategy.md         (Performance & caching)
├── 07-api-documentation.md            (REST API docs)
├── 08-testing-strategy.md             (Testing & acceptance criteria)
├── 09-deployment-guide.md             (Deployment & ops)
├── 10-architecture-decisions.md       (18 ADRs)
└── 11-development-roadmap.md          (Phased roadmap)
```

The Laravel application code will be added to this repository once documentation is approved and development begins.

---

## Approval Status

**Current status: Documentation complete — pending client review and approval.**

Before development begins:
1. Client reviews and approves all 11 documents.
2. Security review of no-auto-logout and backup decisions.
3. Legal review of no-tax-invoice decision.
4. Finalize any remaining open items (see BRD §26).
5. Begin Phase 0 (project setup).

---

## Contact

**Prepared for:** Client (Mr. X)
**Prepared by:** Software Development Team
**Repository:** https://github.com/sajidchowdhury/nondon
