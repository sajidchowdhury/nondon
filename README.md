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
| [docs/01-business-requirements.md](docs/01-business-requirements.md) | Consolidated Business Requirements Document (BRD v1.6) covering all confirmed business decisions from discovery rounds 1-5 | Draft — pending client approval |
| [docs/02-architecture.md](docs/02-architecture.md) | Laravel modular monolith architecture, module decomposition, offline POS architecture, event-driven communication | Design document |
| [docs/03-database-design.md](docs/03-database-design.md) | PostgreSQL schema, ER relationships, constraints, indexes, partitioning strategy, concurrency control | Design document |
| [docs/04-inventory-workflow.md](docs/04-inventory-workflow.md) | 27 detailed operational workflows covering POS, transfers, online orders, returns, cash, stock counts, damaged stock | Design document |
| [docs/05-security-design.md](docs/05-security-design.md) | RBAC, 5-role permission matrix, audit logging, authentication, data protection, security checklist | Design document |
| [docs/06-performance-strategy.md](docs/06-performance-strategy.md) | Indexing, current-stock projection, summary tables, caching, offline cache, monitoring, capacity planning | Design document |
| [docs/07-api-documentation.md](docs/07-api-documentation.md) | REST API endpoints for all modules, sync API, authentication, response formats | Design document |
| [docs/08-testing-strategy.md](docs/08-testing-strategy.md) | Test pyramid, unit/integration/concurrency tests, 50 acceptance criteria, test data management | Design document |
| [docs/09-deployment-guide.md](docs/09-deployment-guide.md) | Nginx + PHP-FPM + PostgreSQL setup, SSL, backup, queue workers, security hardening | Design document |
| [docs/10-architecture-decisions.md](docs/10-architecture-decisions.md) | 18 Architecture Decision Records (ADRs) documenting key design choices and rationale | Design document |
| [docs/11-development-roadmap.md](docs/11-development-roadmap.md) | 10-phase development plan with milestones, dependencies, and team requirements | Design document |
| [docs/12-phase-1-session-breakdown.md](docs/12-phase-1-session-breakdown.md) | Phase 1 Foundation split into 12 focused development sessions with detailed deliverables and acceptance criteria | Planning document |

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
├── composer.json                      (PHP dependencies)
├── package.json                       (Frontend dependencies)
├── artisan                            (Laravel CLI)
├── phpunit.xml                        (Test configuration)
├── pint.json                          (Code style configuration)
├── vite.config.js                     (Frontend build config)
├── tailwind.config.js                 (Tailwind CSS config)
├── .env.example                       (Environment template)
│
├── app/
│   ├── Modules/                       (Modular monolith modules)
│   │   ├── Identity/                  (Users, roles, auth, login security)
│   │   │   ├── Models/                (User, LoginAttempt)
│   │   │   ├── Services/              (AuthService)
│   │   │   ├── Http/
│   │   │   │   ├── Livewire/Auth/     (Login, Dashboard, RoleSwitcher)
│   │   │   │   ├── Middleware/        (SetActiveBranchScope, EnsureActiveBranch)
│   │   │   │   └── Controllers/Api/   (AuthController)
│   │   │   └── Database/Migrations/   (users, login_attempts, sessions)
│   │   └── Organization/              (Branches, fiscal years, stock locations)
│   │       ├── Models/                (Branch, FiscalYear, StockLocation)
│   │       └── Database/Migrations/   (branches, fiscal_years, stock_locations)
│   └── Providers/                     (App, Auth service providers)
│
├── bootstrap/app.php                  (Laravel 11 bootstrap config)
├── config/                            (app, auth, database, session, etc.)
├── database/
│   ├── migrations/                    (cache, jobs tables)
│   ├── seeders/                       (Roles, Branches, FiscalYear, AdminUser)
│   └── factories/                     (User, Branch, FiscalYear factories)
├── routes/                            (web.php, api.php, console.php)
├── resources/
│   ├── views/
│   │   ├── layouts/                   (app.blade.php, auth.blade.php)
│   │   ├── livewire/auth/             (login, dashboard, role-switcher)
│   │   └── components/                (nav-link)
│   ├── css/app.css                    (Tailwind CSS entry)
│   └── js/app.js                      (Alpine.js + Livewire)
├── tests/                             (Pest PHP tests)
├── .github/workflows/                 (CI pipeline — requires workflow-scope token to push)
│
├── docs/                              (Project documentation)
│   ├── 01-business-requirements.md    (BRD v1.6)
│   ├── 02-architecture.md             (Architecture design)
│   ├── 03-database-design.md          (PostgreSQL schema)
│   ├── 04-inventory-workflow.md       (27 operational workflows)
│   ├── 05-security-design.md          (Security & RBAC)
│   ├── 06-performance-strategy.md     (Performance & caching)
│   ├── 07-api-documentation.md        (REST API docs)
│   ├── 08-testing-strategy.md         (Testing & acceptance criteria)
│   ├── 09-deployment-guide.md         (Deployment & ops)
│   ├── 10-architecture-decisions.md   (18 ADRs)
│   ├── 11-development-roadmap.md      (Phased roadmap)
│   └── 12-phase-1-session-breakdown.md (Phase 1 session plan)
```

---

## Development Setup

### Option A: Docker (Recommended for Windows)

This is the recommended setup for Windows. Docker provides PHP 8.3, PostgreSQL, Nginx, and Node.js without modifying your local PHP installation.

**Prerequisites:**
- Docker Desktop (installed and running)
- Git
- PowerShell

**Setup Steps:**

1. **Clone the repository:**
   ```powershell
   cd D:\DeveloperHub
   git clone https://github.com/sajidchowdhury/nondon.git
   cd nondon
   ```

2. **Run the automated setup script:**
   ```powershell
   .\setup.ps1
   ```
   This script will:
   - Build and start Docker containers (PHP-FPM, Nginx, PostgreSQL)
   - Install PHP dependencies (composer install)
   - Install Node dependencies and build assets (npm install && npm run build)
   - Generate application key
   - Publish Spatie permission migrations
   - Run database migrations and seeders
   - Optimize the application

3. **Access the application:**
   - Application: http://localhost:8080
   - PostgreSQL: localhost:5433 (database: nondon, user: nondon_app, password: secret)

4. **Run all tests:**
   ```powershell
   .\run-tests.ps1
   ```
   Or run tests manually:
   ```powershell
   docker compose exec app php artisan test
   ```

5. **Run a specific test file:**
   ```powershell
   docker compose exec app php artisan test tests/Feature/Phase1IntegrationTest.php
   ```

**Manual Docker commands (if setup.ps1 fails):**
```powershell
# Build and start containers (app, web, db only - not node)
docker compose up -d --build app web db

# Wait for PostgreSQL to be ready
Start-Sleep -Seconds 10

# Run the init script (composer install, key:generate, migrations, seeders)
docker compose exec -T app bash /var/www/html/docker/init.sh

# Build frontend assets (uses default command from docker-compose.yml)
docker compose run --rm --no-deps node

# Optimize
docker compose exec -T app php artisan config:cache
docker compose exec -T app php artisan route:cache
docker compose exec -T app php artisan view:cache

# Run tests
docker compose exec -T app php artisan test
```

**Docker container management:**
```powershell
docker compose up -d app web db  # Start containers (without node)
docker compose down              # Stop containers
docker compose down -v           # Stop and delete database data
docker compose logs -f app       # View PHP logs
docker compose logs -f web       # View Nginx logs
docker compose ps                # Show container status
```

**Default admin credentials:**
- Username: `admin`
- Password: `Nondon@2025`

---

### Option B: Local Installation (Without Docker)

**Prerequisites:**
- PHP 8.3+
- Composer 2.x
- PostgreSQL 15+
- Node.js 20+ and npm
- Git

1. **Clone the repository:**
   ```bash
   git clone https://github.com/sajidchowdhury/nondon.git
   cd nondon
   ```

2. **Install PHP dependencies:**
   ```bash
   composer install
   ```

3. **Install frontend dependencies:**
   ```bash
   npm install
   ```

4. **Configure environment:**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```
   Edit `.env` and set your PostgreSQL credentials:
   ```ini
   DB_CONNECTION=pgsql
   DB_HOST=127.0.0.1
   DB_PORT=5432
   DB_DATABASE=nondon
   DB_USERNAME=your_db_user
   DB_PASSWORD=your_db_password
   ```

5. **Create the database:**
   ```sql
   CREATE DATABASE nondon WITH ENCODING 'UTF8';
   CREATE USER nondon_app WITH ENCRYPTED PASSWORD 'your_password';
   GRANT ALL PRIVILEGES ON DATABASE nondon TO nondon_app;
   ```

6. **Publish Spatie permission migrations:**
   ```bash
   php artisan vendor:publish --tag="permission-migrations"
   ```

7. **Run migrations and seeders:**
   ```bash
   php artisan migrate --seed
   ```

8. **Build frontend assets:**
   ```bash
   npm run build    # production
   # OR
   npm run dev      # development with hot reload
   ```

9. **Start the development server:**
   ```bash
   php artisan serve
   ```
   The application will be available at `http://localhost:8000`

### Default Admin Credentials

After seeding, an admin user is created:
- **Username:** `admin`
- **Password:** `Nondon@2025`

**Change the password immediately after first login.** The admin account is configured with `force_password_change = true`.

### Running Tests

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific test file
php artisan test tests/Feature/Auth/LoginTest.php

# Run tests in parallel
php artisan test --parallel
```

### Code Style

```bash
# Check code style
./vendor/bin/pint --test

# Fix code style
./vendor/bin/pint
```

---

## Phase 0 Implementation Status

Phase 0 (Project Setup) is **complete**. The following has been implemented:

- [x] Laravel 11 project with modular monolith structure
- [x] Module registration system (Identity, Organization)
- [x] PostgreSQL database configuration
- [x] Spatie Laravel Permission for RBAC
- [x] Livewire 3 + Tailwind CSS + Alpine.js frontend
- [x] User model with login security (lockout, attempt tracking)
- [x] Branch, FiscalYear, StockLocation models
- [x] Fiscal year serial generation (`YY-NNNNN` format)
- [x] Authentication system (login, logout, role switching)
- [x] Login attempt logging
- [x] Base layout, navigation, dashboard
- [x] 5 roles with full permission matrix (main_branch_admin, branch_supervisor, salesperson, online_team, online_supervisor)
- [x] Database seeders (roles, branches, fiscal year, admin user)
- [x] Model factories for testing
- [x] Pest PHP test suite with auth tests
- [x] GitHub Actions CI pipeline
- [x] API authentication controller (Sanctum)

### Next: Phase 1 — Foundation (3-4 weeks)

Phase 1 will build:
- Full Identity module (user management, password reset, account unlock)
- Full Organization module (branch management, fiscal year management)
- Catalog module (products, sizes, prices)
- Inventory module (garments, movements, current stock projection)
- Audit module (audit log infrastructure)

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
