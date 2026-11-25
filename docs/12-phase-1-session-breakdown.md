# Phase 1: Foundation — Session Breakdown
## Nondon ERP Development Plan

**Project:** Nondon
**Version:** 1.1
**Date:** 24 November 2026
**Status:** Implementation in progress
**Phase:** 1 of 10 (Foundation)
**Estimated duration:** 3-4 weeks (12 sessions)

---

## Progress Tracker

| Session | Status | Commit |
|---|---|---|
| 1.1 User Management Foundation | COMPLETE | f7eb492 |
| 1.2 Role & Permission Management | COMPLETE | ce2e6ce |
| 1.3 Auth Security & Password Management | COMPLETE | 73f4d4d |
| 1.4 Audit Log Infrastructure | COMPLETE | 808465a |
| 1.5 Branch Management | COMPLETE | 00e1b19 |
| 1.6 Fiscal Year Management | COMPLETE | 2d5a049 |
| 1.7 Product & Design Management | COMPLETE | 607ba60 |
| 1.8 Pricing Management | COMPLETE | ca28d10 |
| 1.9 Garment Identity & Records | COMPLETE | ec3f04f |
| 1.10 Movement Ledger & Stock Projection | COMPLETE | 4a30189 |
| 1.11 Stock Visibility & Queries | COMPLETE | Session 1.11 commit |
| 1.12 Audit Log UI & Phase 1 Integration | Pending | — |

---

## 1. Purpose

This document splits Phase 1 (Foundation) into 12 focused development sessions. Each session is a self-contained unit of work with clear deliverables, dependencies, and acceptance criteria.

**Principle: best solution, not quickest.** Each session is small enough to implement thoroughly — with proper architecture, complete tests, and careful review — without rushing to the next session.

Sessions are sequential. Each builds on the previous one. Do not start a session until the previous one is complete, tested, and committed.

---

## 2. Session Overview

| Session | Module | Focus | Estimated time | Depends on |
|---|---|---|---|---|
| 1.1 | Identity | User Management Foundation | 2-3 hours | Phase 0 |
| 1.2 | Identity | Role & Permission Management | 2-3 hours | 1.1 |
| 1.3 | Identity | Authentication Security & Password Management | 2-3 hours | 1.2 |
| 1.4 | Audit | Audit Log Infrastructure | 3-4 hours | 1.3 |
| 1.5 | Organization | Branch Management | 2-3 hours | 1.4 |
| 1.6 | Organization | Fiscal Year Management | 2-3 hours | 1.5 |
| 1.7 | Catalog | Product & Design Management | 2-3 hours | 1.6 |
| 1.8 | Catalog | Pricing Management | 2-3 hours | 1.7 |
| 1.9 | Inventory | Garment Identity & Records | 3-4 hours | 1.8 |
| 1.10 | Inventory | Movement Ledger & Stock Projection | 3-4 hours | 1.9 |
| 1.11 | Inventory | Stock Visibility & Queries | 2-3 hours | 1.10 |
| 1.12 | Audit + Integration | Audit Log UI & Phase 1 Integration | 2-3 hours | 1.11 |

**Total:** 12 sessions, approximately 30-40 hours of focused development.

### 2.1 Dependency graph

```
Phase 0 (complete)
    │
    ▼
1.1 User Management
    │
    ▼
1.2 Role & Permission Mgmt
    │
    ▼
1.3 Auth Security & Password
    │
    ▼
1.4 Audit Log Infrastructure ◄── needed by all subsequent modules
    │
    ├──► 1.5 Branch Management
    │        │
    │        ▼
    │    1.6 Fiscal Year Management
    │        │
    │        ▼
    │    1.7 Product & Design Mgmt
    │        │
    │        ▼
    │    1.8 Pricing Management
    │        │
    │        ▼
    │    1.9 Garment Identity & Records
    │        │
    │        ▼
    │    1.10 Movement Ledger & Projection
    │        │
    │        ▼
    │    1.11 Stock Visibility & Queries
    │        │
    │        ▼
    └──► 1.12 Audit UI & Phase 1 Integration
```

### 2.2 Why audit infrastructure comes early (Session 1.4)

Audit logging is a cross-cutting concern — every business module needs to write audit entries. Building the audit infrastructure before the business modules (Catalog, Inventory) ensures that every action from day one is auditable. If we built audit last, we would need to retrofit audit calls into every module, which is error-prone and incomplete.

### 2.3 Session completion criteria

A session is considered complete when:
1. All deliverables are implemented.
2. All acceptance criteria tests pass.
3. Code follows Pint style (no linting errors).
4. No existing tests are broken.
5. Changes are committed and pushed to GitHub.
6. The next session's dependencies are satisfied.

---

## Session 1.1: Identity — User Management Foundation

**Goal:** Build the full user management CRUD system with authorization, so admin can create, view, edit, activate/deactivate, and assign users to branches.

**Estimated time:** 2-3 hours

### Prerequisites
- Phase 0 complete (User model, migrations, AuthService exist)
- Roles and permissions seeded

### Deliverables

**1. User model refinements:**
- Add relationships: `assignedBranch()`, `lockedByUser()`, `activeRole()`, `loginAttempts()`
- Add scopes: `active()`, `inactive()`, `locked()`, `forBranch($branchId)`, `onlineChannel()`
- Add accessor: `display_name` (full_name or username)
- Add methods: `isLocked()`, `isActive()`, `canSell()`, `lock()`, `unlock()`, `incrementFailedAttempts()`, `resetFailedAttempts()`
- Ensure `password_hash` is hidden from serialization
- Ensure `purchase_cost` and sensitive fields are never on this model

**2. UserService (app/Modules/Identity/Services/UserService.php):**
- `create(array $data): User` — create user with hashed password, branch assignment
- `update(User $user, array $data): User` — update non-sensitive fields
- `activate(User $user): User` — set is_active = true
- `deactivate(User $user): User` — set is_active = false
- `assignToBranch(User $user, Branch $branch): User` — change branch assignment (admin only)
- `getUsersForBranch(Branch $branch): Collection` — list users for a branch
- `search(string $term): Collection` — search by username, full_name, email

**3. UserPolicy (app/Modules/Identity/Policies/UserPolicy.php):**
- `viewAny(User $user): bool` — admin only
- `view(User $user, User $target): bool` — admin sees all; supervisor sees own branch; salesperson sees self
- `create(User $user): bool` — admin only
- `update(User $user, User $target): bool` — admin only
- `activate(User $user, User $target): bool` — admin only
- `deactivate(User $user, User $target): bool` — admin only
- `assignBranch(User $user, User $target): bool` — admin only
- `unlock(User $user, User $target): bool` — admin only

**4. Livewire components:**
- `UserList` — paginated table with search, role filter, branch filter, status filter
- `UserCreate` — form with username, full_name, email, password, branch, role selection
- `UserEdit` — form to edit user details (not password), activate/deactivate, assign branch, assign roles
- `UserView` — read-only detail view with login history

**5. Views:**
- `resources/views/livewire/identity/users/list.blade.php`
- `resources/views/livewire/identity/users/create.blade.php`
- `resources/views/livewire/identity/users/edit.blade.php`
- `resources/views/livewire/identity/users/view.blade.php`

**6. Routes:**
- `GET /users` — UserList (admin only)
- `GET /users/create` — UserCreate (admin only)
- `GET /users/{user}` — UserView (admin, or supervisor for own branch)
- `GET /users/{user}/edit` — UserEdit (admin only)

**7. Tests (tests/Feature/Identity/UserManagementTest.php):**
- Admin can view user list
- Supervisor can view own branch users only
- Salesperson cannot view user list
- Admin can create user with valid data
- Cannot create user with duplicate username
- Admin can edit user details
- Admin can activate/deactivate user
- Admin can assign user to different branch
- Non-admin cannot perform any user management action
- User creation hashes password correctly
- User list is paginated and searchable

### Acceptance criteria — Session 1.1 (COMPLETE)
- [x] Admin can create, view, edit, activate/deactivate users through the UI
- [x] Branch assignment can be changed by admin only
- [x] Supervisors can view users in their own branch but cannot edit
- [x] Salespersons have no access to user management
- [x] All actions are protected by policies (server-side)
- [x] Passwords are never exposed in responses or views
- [x] 30 tests written (all acceptance criteria covered)

---

## Session 1.2: Identity — Role & Permission Management

**Goal:** Build the role assignment and permission override system so admin can assign multiple roles to a user, switch active roles, and grant/revoke custom permissions.

**Estimated time:** 2-3 hours

### Prerequisites
- Session 1.1 complete

### Deliverables

**1. RoleAssignmentService (app/Modules/Identity/Services/RoleAssignmentService.php):**
- `assignRole(User $user, Role $role): User` — assign a role to user
- `removeRole(User $user, Role $role): User` — remove a role from user
- `syncRoles(User $user, array $roleIds): User` — sync all roles for a user
- `getUserRoles(User $user): Collection` — get user's roles
- `hasMultipleRoles(User $user): bool` — check if user has multiple roles
- `setActiveRole(User $user, Role $role): User` — set the active role for session
- `getActiveRole(User $user): ?Role` — get the current active role

**2. PermissionOverrideService (app/Modules/Identity/Services/PermissionOverrideService.php):**
- `grantPermission(User $user, Permission $permission): User` — grant a custom permission
- `revokePermission(User $user, Permission $permission): User` — revoke a permission
- `getEffectivePermissions(User $user): Collection` — compute effective permissions (role + grants - revokes)
- `hasEffectivePermission(User $user, string $permission): bool` — check effective permission

**3. Livewire components:**
- `RoleAssignment` — component within UserEdit to assign/remove roles
- `PermissionMatrix` — admin view showing all roles x permissions matrix
- `PermissionOverride` — component to grant/revoke custom permissions per user

**4. Role switching refinement:**
- Update `RoleSwitcher` Livewire component to handle role switching properly
- After role switch, redirect to dashboard with confirmation toast
- Store active role in session
- Audit role switch events

**5. Views:**
- `resources/views/livewire/identity/roles/assignment.blade.php`
- `resources/views/livewire/identity/roles/permission-matrix.blade.php`
- `resources/views/livewire/identity/roles/override.blade.php`

**6. Routes:**
- `GET /roles/matrix` — PermissionMatrix view (admin only)
- Role assignment is within UserEdit component (no separate route)

**7. Tests (tests/Feature/Identity/RolePermissionTest.php):**
- Admin can assign role to user
- Admin can remove role from user
- Admin can sync multiple roles
- User with multiple roles can switch between them
- Active role determines permission scope
- Admin can grant custom permission to user
- Admin can revoke permission from user
- Effective permissions = role permissions + grants - revokes
- Branch scope restrictions apply regardless of role combination
- Sensitive data restrictions (purchase cost) apply regardless of role combination
- Non-admin cannot access role/permission management

### Acceptance criteria
### Acceptance criteria — Session 1.2 (COMPLETE)
- [x] Admin can assign multiple roles to a user
- [x] Multi-role users can switch between roles via the UI
- [x] Active role determines what the user can see and do
- [x] Admin can grant custom permissions to a user (overrides)
- [x] Admin can revoke permissions from a user
- [x] Branch scope always applies regardless of role
- [x] Sensitive data (purchase cost) is hidden from non-admin regardless of role combination
- [x] 31 tests written (all acceptance criteria covered)

---

## Session 1.3: Identity — Authentication Security & Password Management

**Goal:** Build the complete password management and account security workflows — admin password reset, force change on next login, account unlock, and self-service password change.

**Estimated time:** 2-3 hours

### Prerequisites
- Session 1.2 complete

### Deliverables

**1. AuthService refinements:**
- `resetPassword(User $user, string $newPassword): User` — admin resets password, sets force_password_change = true
- `changePassword(string $currentPassword, string $newPassword): User` — self-service password change (requires current password)
- `unlockAccount(User $user): User` — admin unlocks a locked account
- `forcePasswordChange(User $user): User` — sets force_password_change flag
- Login flow checks `force_password_change` and redirects to password change page if true

**2. Password validation rules:**
- Minimum 8 characters
- Must contain at least one letter and one number
- Cannot be the same as the current password
- Validate against common weak passwords list

**3. Livewire components:**
- `PasswordChange` — self-service password change form (requires current password)
- `ForcePasswordChange` — forced password change page (no current password required, shown after login when force_password_change = true)
- `PasswordReset` — admin-initiated password reset form (within UserEdit)
- `AccountUnlock` — admin unlock action (within UserEdit)

**4. Middleware:**
- `ForcePasswordChangeMiddleware` — redirects to password change page if force_password_change = true (except for password change routes and logout)

**5. Views:**
- `resources/views/livewire/identity/password/change.blade.php`
- `resources/views/livewire/identity/password/force-change.blade.php`
- `resources/views/livewire/identity/password/reset.blade.php` (modal/inline in UserEdit)

**6. Routes:**
- `GET /password/change` — PasswordChange (authenticated user)
- `GET /password/force-change` — ForcePasswordChange (authenticated, force flag set)
- Routes are protected by ForcePasswordChangeMiddleware

**7. Tests (tests/Feature/Identity/PasswordManagementTest.php):**
- Admin can reset user password
- Password reset sets force_password_change = true
- User with force_password_change is redirected to change page after login
- User can change own password with correct current password
- User cannot change password with wrong current password
- Password validation enforces minimum length
- Password validation rejects same-as-current password
- Admin can unlock a locked account
- Locked account cannot login even with correct password
- After unlock, user can login
- After forced password change, user can access normal pages
- Non-admin cannot reset passwords or unlock accounts

### Acceptance criteria — Session 1.3 (COMPLETE)
- [x] Admin can reset any user's password
- [x] Reset password forces change on next login
- [x] User can change own password (requires current password verification)
- [x] Locked accounts can be unlocked by admin only
- [x] Password validation enforces security rules
- [x] ForcePasswordChange middleware redirects correctly
- [x] 35 tests written (all acceptance criteria covered)

---

## Session 1.4: Audit — Audit Log Infrastructure

**Goal:** Build the audit log infrastructure that all subsequent modules will use to record meaningful business actions. This must be in place before any business module (Catalog, Inventory) is built.

**Estimated time:** 3-4 hours

### Prerequisites
- Session 1.3 complete

### Deliverables

**1. AuditLog model (app/Modules/Audit/Models/AuditLog.php):**
- Fields: user_id, branch_id, action, category, reference_type, reference_id, previous_values (JSONB), new_values (JSONB), reason, ip_address, user_agent, performed_at
- Append-only: no update or delete operations allowed (enforce at model level by overriding save/delete methods)
- Scopes: forUser(), forBranch(), forDateRange(), forCategory(), forAction()
- Casts: previous_values and new_values as array (JSONB), performed_at as datetime

**2. AuditLog migration:**
- Create `audit_logs` table with all fields
- Indexes: (user_id, performed_at DESC), (branch_id, performed_at DESC), (reference_type, reference_id), (action, performed_at)
- Note: Partitioning by month will be added in a later phase when volume grows

**3. AuditService (app/Modules/Audit/Services/AuditService.php):**
- `log(string $action, array $context = []): AuditLog` — write an audit entry
  - Context includes: reference_type, reference_id, previous_values, new_values, reason, category
  - Automatically captures: user_id, branch_id, ip_address, user_agent, performed_at
- `query(array $filters): Builder` — query audit logs with filters
- `forUser(User $user): Collection` — get audit entries for a user
- `forBranch(Branch $branch, Carbon $date): Collection` — get entries for a branch on a date
- `forReference(string $type, int $id): Collection` — get entries for a specific entity

**4. Audit event system:**
- `AuditableEvent` base event class — all audit-worthy events extend this
- `AuditListener` — listens to AuditableEvent and writes to audit log via AuditService
- Register listener in EventServiceProvider
- Events are dispatched by services after business actions (e.g., UserCreated, UserUpdated, RoleAssigned)

**5. Audit trait:**
- `Auditable` trait for models — provides `auditCreate()`, `auditUpdate()`, `auditDelete()` methods that dispatch events automatically
- Captures previous_values and new_values automatically on update
- Can be applied to any model that needs auditing

**6. AuditServiceProvider:**
- Register AuditService as singleton
- Load migrations from Audit module
- Register audit event listeners

**7. Sensitive data access logging:**
- `LogDataAccess` middleware — logs when a user views sensitive data (purchase cost, supplier info)
- Applies to routes that expose sensitive data
- Writes audit entry with action = 'sensitive_data.view'

**8. Tests (tests/Feature/Audit/AuditLogTest.php):**
- Audit log entry is created when a user is created
- Audit log entry is created when a user is updated (with previous and new values)
- Audit log entry is created when a role is assigned
- Audit log entry is created when a password is reset
- Audit log entry is created when an account is unlocked
- Audit log captures user_id, branch_id, ip_address, action, performed_at
- Audit log previous_values and new_values are correct JSON
- Audit log cannot be updated (append-only enforcement)
- Audit log cannot be deleted (append-only enforcement)
- Audit query filters by user correctly
- Audit query filters by branch and date correctly
- Sensitive data access is logged
- Non-admin cannot query audit logs directly

### Acceptance criteria — Session 1.4 (COMPLETE)
- [x] AuditLog model is append-only (no update/delete)
- [x] AuditService writes entries with all required fields
- [x] Auditable trait auto-dispatches events on model create/update
- [x] Previous and new values are captured on updates
- [x] Sensitive data access is logged
- [x] Audit query supports filtering by user, branch, date, action, category
- [x] All audit-worthy actions in the Identity module are audited
- [x] 25 tests written (all acceptance criteria covered)

---

## Session 1.5: Organization — Branch Management

**Goal:** Build the full branch management system — CRUD for branches and stock locations, with admin-only authorization and branch-scoped data access.

**Estimated time:** 2-3 hours

### Prerequisites
- Session 1.4 complete (audit infrastructure available)

### Deliverables

**1. Branch model refinements:**
- Already exists from Phase 0 — add any missing relationships or scopes
- Add `stockLocations()` relationship
- Add `users()` relationship
- Ensure `isMainBranch()`, `isOnlineChannel()` methods work correctly
- Add `displayName` accessor

**2. StockLocation model refinements:**
- Already exists from Phase 0 — add `branch()` relationship
- Add scopes: `active()`, `ofType()`
- Add `TYPES` constant for location types

**3. BranchService (app/Modules/Organization/Services/BranchService.php):**
- `create(array $data): Branch` — create a branch (admin only)
- `update(Branch $branch, array $data): Branch` — update branch details
- `activate(Branch $branch): Branch` — set is_active = true
- `deactivate(Branch $branch): Branch` — set is_active = false (cannot deactivate if users assigned)
- `getActiveBranches(): Collection` — list active branches
- `getPhysicalBranches(): Collection` — list physical branches (exclude online)
- `getOnlineChannel(): Branch` — get the online channel branch

**4. StockLocationService:**
- `create(Branch $branch, array $data): StockLocation`
- `update(StockLocation $location, array $data): StockLocation`
- `deactivate(StockLocation $location): StockLocation`
- `getForBranch(Branch $branch): Collection`

**5. BranchScope global scope:**
- Apply to all branch-sensitive models
- Automatically filters queries by the authenticated user's active branch
- Main Branch Admin sees all branches
- Supervisors see own branch only
- Online channel users see online channel only
- Implement via a trait `HasBranchScope` that models use

**6. BranchPolicy & StockLocationPolicy:**
- BranchPolicy: viewAny (admin, supervisors), view (admin, supervisor own branch), create/update (admin only)
- StockLocationPolicy: same pattern

**7. Livewire components:**
- `BranchList` — paginated table of branches with type filter, status filter
- `BranchCreate` — form with code, name, type, address, phone
- `BranchEdit` — form to edit branch details, manage stock locations
- `StockLocationManager` — manage stock locations within BranchEdit

**8. Views:**
- `resources/views/livewire/organization/branches/list.blade.php`
- `resources/views/livewire/organization/branches/create.blade.php`
- `resources/views/livewire/organization/branches/edit.blade.php`

**9. Routes:**
- `GET /branches` — BranchList (admin, supervisors)
- `GET /branches/create` — BranchCreate (admin only)
- `GET /branches/{branch}/edit` — BranchEdit (admin only)

**10. Audit integration:**
- All branch create/update/activate/deactivate actions write audit logs
- All stock location create/update actions write audit logs

**11. Tests (tests/Feature/Organization/BranchManagementTest.php):**
- Admin can create a branch
- Admin can edit a branch
- Admin can activate/deactivate a branch
- Cannot deactivate a branch with assigned users
- Supervisor can view branch list but cannot create/edit
- Salesperson cannot access branch management
- Branch code is unique
- Stock location can be created for a branch
- Stock location code is unique per branch
- Branch scope filters data for non-admin users
- Branch scope does not filter for admin
- All branch actions are audited

### Acceptance criteria — Session 1.5 (COMPLETE)
- [x] Admin can create, edit, activate/deactivate branches
- [x] Stock locations can be managed per branch
- [x] Branch scope automatically filters data for non-admin users
- [x] Supervisors can view but not modify branches
- [x] All branch actions are audited
- [x] 35 tests written (all acceptance criteria covered)

---

## Session 1.6: Organization — Fiscal Year Management

**Goal:** Build the fiscal year management system — create, close, carry-forward, with serial number generation using advisory locking for concurrency safety.

**Estimated time:** 2-3 hours

### Prerequisites
- Session 1.5 complete

### Deliverables

**1. FiscalYear model refinements:**
- Already exists from Phase 0 — verify `generateNextSerial()` and `incrementSequence()` work correctly
- Add `containsDate()` method
- Add `current()` static method to get the active fiscal year
- Add scopes: `active()`, `closed()`, `chronological()`

**2. FiscalYearService (app/Modules/Organization/Services/FiscalYearService.php):**
- `create(array $data): FiscalYear` — create a new fiscal year with validation:
  - Start date must be July 1
  - End date must be June 30 of the following year
  - Serial prefix is auto-derived from start year (e.g., 2025 → "25")
  - No date overlap with existing fiscal years
- `close(FiscalYear $fiscalYear): FiscalYear` — close a fiscal year:
  - Set is_closed = true, is_active = false
  - Cannot close if there are pending transactions (checked in later phases)
  - Unsold garments carry forward with their original identity (no change needed — just a status flag)
- `activate(FiscalYear $fiscalYear): FiscalYear` — set as the active fiscal year (deactivates others)
- `getCurrent(): ?FiscalYear` — get the currently active fiscal year
- `generateNextSerial(FiscalYear $fiscalYear): string` — generate the next serial number with advisory locking:
  - Uses `pg_advisory_xact_lock()` within a transaction
  - Increments `current_serial_sequence`
  - Returns formatted serial `YY-NNNNN`
- `reserveSerial(FiscalYear $fiscalYear): string` — alias for generateNextSerial, used by the purchasing module later

**3. FiscalYearPolicy:**
- All operations: admin only
- Supervisors can view the current fiscal year
- Salespersons have no direct access

**4. Livewire components:**
- `FiscalYearList` — table of fiscal years with status (active/closed), date range
- `FiscalYearCreate` — form with start year (auto-calculates start_date, end_date, prefix)
- `FiscalYearView` — detail view with serial sequence count, garment count (in later phases)

**5. Views:**
- `resources/views/livewire/organization/fiscal-years/list.blade.php`
- `resources/views/livewire/organization/fiscal-years/create.blade.php`
- `resources/views/livewire/organization/fiscal-years/view.blade.php`

**6. Routes:**
- `GET /fiscal-years` — FiscalYearList (admin only)
- `GET /fiscal-years/create` — FiscalYearCreate (admin only)
- `GET /fiscal-years/{fiscalYear}` — FiscalYearView (admin only)

**7. Audit integration:**
- Fiscal year create, close, activate actions are audited
- Serial generation is audited (per garment, in later phases)

**8. Tests (tests/Feature/Organization/FiscalYearTest.php):**
- Admin can create a fiscal year
- Start date must be July 1
- End date is auto-calculated as June 30 of next year
- Serial prefix is auto-derived from start year
- Cannot create overlapping fiscal years
- Admin can close a fiscal year
- Closing sets is_closed and is_active = false
- Admin can activate a fiscal year (deactivates others)
- Only one fiscal year can be active at a time
- generateNextSerial produces correct format (YY-NNNNN)
- generateNextSerial is concurrency-safe (two concurrent calls produce different serials)
- incrementSequence updates the counter correctly
- Supervisor can view but not manage fiscal years
- Salesperson has no access to fiscal year management
- All fiscal year actions are audited

### Acceptance criteria — Session 1.6 (COMPLETE)
- [x] Admin can create fiscal years with auto-calculated dates and prefix
- [x] Fiscal year close and activation work correctly
- [x] Only one active fiscal year at a time
- [x] Serial generation is concurrency-safe (advisory locked)
- [x] Serial format is YY-NNNNN (e.g., 25-00001)
- [x] All fiscal year actions are audited
- [x] 40 tests written (all acceptance criteria covered)

---

## Session 1.7: Catalog — Product & Design Management

**Goal:** Build the product and design management system — products with sizes, admin-only CRUD, with product activation/deactivation.

**Estimated time:** 2-3 hours

### Prerequisites
- Session 1.6 complete

### Deliverables

**1. Product model (app/Modules/Catalog/Models/Product.php):**
- Fields: code, name, description, category, is_active, deactivated_at, deactivated_by
- Relationships: `sizes()` (HasMany ProductSize), `prices()` (HasMany ProductPrice — added in Session 1.8)
- Scopes: `active()`, `inactive()`, `search($term)`, `byCategory($category)`
- Methods: `activate()`, `deactivate(User $user)`
- Deactivated products remain in historical transactions

**2. ProductSize model (app/Modules/Catalog/Models/ProductSize.php):**
- Fields: product_id, size_label, is_active
- Relationships: `product()` (BelongsTo)
- Unique constraint: (product_id, size_label)
- Scopes: `active()`, `forProduct($productId)`

**3. Product migrations:**
- `create_products_table` — code (unique), name, description, category, is_active, deactivated_at, deactivated_by, timestamps
- `create_product_sizes_table` — product_id (FK), size_label, is_active, timestamps, unique(product_id, size_label)

**4. ProductService (app/Modules/Catalog/Services/ProductService.php):**
- `create(array $data): Product` — create product with sizes (admin only)
- `update(Product $product, array $data): Product` — update product details and sizes
- `addSize(Product $product, string $sizeLabel): ProductSize` — add a size to a product
- `removeSize(Product $product, string $sizeLabel): void` — remove a size (if no garments use it)
- `activate(Product $product): Product`
- `deactivate(Product $product): Product` — deactivated products remain in history
- `search(string $term): Collection` — search by code or name
- `getWithSizes(): Collection` — products with their sizes eager-loaded

**5. ProductPolicy & ProductSizePolicy:**
- ProductPolicy: viewAny (admin, supervisors, salespersons — all can view catalog), create/update (admin only), activate/deactivate (admin only)
- Salespersons can view products (needed for POS in later phases) but cannot manage them

**6. Livewire components:**
- `ProductList` — paginated table with search, category filter, status filter
- `ProductCreate` — form with code, name, description, category, and dynamic size entry
- `ProductEdit` — form to edit product details, manage sizes (add/remove)
- `ProductView` — read-only detail with sizes and price history (prices added in 1.8)

**7. Views:**
- `resources/views/livewire/catalog/products/list.blade.php`
- `resources/views/livewire/catalog/products/create.blade.php`
- `resources/views/livewire/catalog/products/edit.blade.php`
- `resources/views/livewire/catalog/products/view.blade.php`

**8. Routes:**
- `GET /products` — ProductList (admin, supervisors, salespersons)
- `GET /products/create` — ProductCreate (admin only)
- `GET /products/{product}` — ProductView (all authenticated users)
- `GET /products/{product}/edit` — ProductEdit (admin only)

**9. Audit integration:**
- Product create, update, activate/deactivate, size add/remove are audited

**10. CatalogServiceProvider:**
- Register Catalog module
- Load migrations
- Register ProductService

**11. Tests (tests/Feature/Catalog/ProductManagementTest.php):**
- Admin can create a product with sizes
- Product code is unique
- Admin can add sizes to a product
- Admin can remove a size (if no garments use it — checked in later phases)
- Cannot remove a size that doesn't exist
- Admin can activate/deactivate a product
- Deactivated product is not in the active list but remains in database
- Supervisor can view products but cannot create/edit
- Salesperson can view products (for future POS)
- Product search works by code and name
- Product list is paginated
- All product actions are audited

### Acceptance criteria — Session 1.7 (COMPLETE)
- [x] Admin can create products with multiple sizes
- [x] Product code is unique
- [x] Sizes can be added and removed (with referential integrity check)
- [x] Products can be activated/deactivated
- [x] Deactivated products remain in database for historical reference
- [x] Supervisors and salespersons can view the product catalog
- [x] Only admin can manage products
- [x] All product actions are audited
- [x] 30 tests written (all acceptance criteria covered)

---

## Session 1.8: Catalog — Pricing Management

**Goal:** Build the pricing system — effective-dated standard and minimum prices per product (or product-size), with price history preservation for historical invoices.

**Estimated time:** 2-3 hours

### Prerequisites
- Session 1.7 complete

### Deliverables

**1. ProductPrice model (app/Modules/Catalog/Models/ProductPrice.php):**
- Fields: product_id, product_size_id (nullable — null means applies to all sizes), standard_price, minimum_price, effective_from, effective_to, set_by
- Relationships: `product()` (BelongsTo), `productSize()` (BelongsTo)
- Scopes: `active()` (where effective_to is null or in future), `forProduct($productId)`, `forSize($sizeId)`, `effectiveAt($date)`
- Methods: `getPriceAt(Carbon $date)` — get the price effective at a specific date (for historical invoices)

**2. ProductPrice migration:**
- `create_product_prices_table` — product_id (FK), product_size_id (nullable FK), standard_price (NUMERIC 12,2), minimum_price (NUMERIC 12,2), effective_from (TIMESTAMPTZ), effective_to (nullable TIMESTAMPTZ), set_by (FK users), timestamps
- Index: (product_id, effective_from, effective_to)

**3. PriceService (app/Modules/Catalog/Services/PriceService.php):**
- `setPrice(Product $product, ?ProductSize $size, float $standardPrice, float $minimumPrice): ProductPrice`
  - Creates a new price record with effective_from = now
  - Sets effective_to of the previous price record to now
  - Validates: minimum_price <= standard_price
  - Admin only
- `getCurrentPrice(Product $product, ?ProductSize $size = null): ?ProductPrice`
  - Gets the price effective right now
  - If size-specific price exists, returns it; otherwise returns product-level price
- `getPriceAt(Product $product, ?ProductSize $size, Carbon $date): ?ProductPrice`
  - Gets the price effective at a specific date (for historical invoice integrity)
- `getPriceHistory(Product $product, ?ProductSize $size = null): Collection`
  - Returns all price records for a product/size, ordered by effective_from

**4. PricePolicy:**
- viewAny: admin, supervisors, salespersons (all need to see prices)
- create/update: admin only
- view: all authenticated users

**5. Livewire components:**
- `PriceManager` — within ProductEdit, manage prices for a product
  - Shows current price (standard + minimum)
  - Shows price history
  - Form to set new price (validates minimum <= standard)
- `PriceHistory` — read-only list of price changes for a product/size

**6. Views:**
- `resources/views/livewire/catalog/prices/manager.blade.php`
- `resources/views/livewire/catalog/prices/history.blade.php`

**7. Validation rules:**
- standard_price: required, numeric, min 0
- minimum_price: required, numeric, min 0, must be <= standard_price
- Price cannot be set below 0

**8. Audit integration:**
- Price set/change is audited with previous and new values
- Audit captures: product, size (if applicable), old standard/minimum, new standard/minimum, set_by, effective_from

**9. Tests (tests/Feature/Catalog/PriceManagementTest.php):**
- Admin can set a price for a product
- Admin can set a price for a specific product-size
- Setting a new price closes the previous price (sets effective_to)
- Cannot set minimum_price > standard_price
- getCurrentPrice returns the active price
- getCurrentPrice returns size-specific price if it exists, otherwise product-level
- getPriceAt returns the correct historical price
- Price history is ordered by effective_from
- Supervisor can view prices but cannot set them
- Salesperson can view current prices
- Price change is audited with previous and new values
- Multiple price changes create multiple history records

### Acceptance criteria — Session 1.8 (COMPLETE)
- [x] Admin can set standard and minimum prices per product or per product-size
- [x] Price changes are effective-dated (new price from now, old price closed)
- [x] Historical prices are preserved and queryable by date
- [x] Minimum price cannot exceed standard price
- [x] Size-specific price overrides product-level price
- [x] All price changes are audited with old and new values
- [x] 31 tests written (all acceptance criteria covered)

---

## Session 1.9: Inventory — Garment Identity & Records

**Goal:** Build the garment model — the central entity of the system. Each physical garment gets an immutable internal ID, permanent barcode, and fiscal-year serial. This is the most critical model in the system.

**Estimated time:** 3-4 hours

### Prerequisites
- Session 1.8 complete (products and prices exist)

### Deliverables

**1. Garment model (app/Modules/Inventory/Models/Garment.php):**
- Fields: internal_id (UUID), barcode, fiscal_year_id, fiscal_year_serial, product_id, product_size_id, purchase_id (nullable, set in Phase 2), purchase_item_id (nullable), goods_receipt_id (nullable), purchase_cost (nullable, restricted visibility), current_status, current_branch_id, current_location_id, is_clearance
- Relationships: `fiscalYear()`, `product()`, `productSize()`, `currentBranch()`, `currentLocation()`, `movements()` (HasMany InventoryMovement — added in 1.10)
- Hidden fields: `purchase_cost` (only visible to admin)
- Scopes: `available()`, `reserved()`, `inTransit()`, `sold()`, `damaged()`, `underInspection()`, `missing()`, `frozen()`, `forBranch($branchId)`, `forProduct($productId)`, `forSize($sizeId)`
- Methods: `isAvailable()`, `isReserved()`, `isSold()`, `canBeSold()`, `reserve($referenceType, $referenceId)`, `releaseReservation()`, `markSold()`, `markDamaged()`, `markMissing()`
- Constants: `STATUS_AVAILABLE`, `STATUS_RESERVED`, `STATUS_IN_TRANSIT`, `STATUS_SOLD`, `STATUS_DAMAGED`, `STATUS_UNDER_INSPECTION`, `STATUS_MISSING`, `STATUS_FROZEN`, `STATUS_CLEARANCE`, `STATUS_DISPOSED`, `STATUS_RETURNED_TO_SUPPLIER`

**2. Garment migration:**
- `create_garments_table` — all fields per the database design document
- Unique constraints: internal_id (UUID), barcode, (fiscal_year_id, fiscal_year_serial)
- Indexes: barcode, (product_id, product_size_id), (current_status, current_branch_id), (fiscal_year_id, fiscal_year_serial)
- Partial index: (product_id, product_size_id, current_branch_id) WHERE current_status = 'available'

**3. GarmentIdentityService (app/Modules/Inventory/Services/GarmentIdentityService.php):**
- `createGarment(Product $product, ?ProductSize $size, FiscalYear $fiscalYear, Branch $branch, ?float $purchaseCost = null): Garment`
  - Generates internal_id (UUID)
  - Generates barcode (format: NND-{uuid_short}-{serial} or a configurable format)
  - Generates fiscal_year_serial via FiscalYearService::generateNextSerial() (advisory locked)
  - Sets current_status = 'available'
  - Sets current_branch_id and current_location_id
  - All within a database transaction
- `generateBarcode(FiscalYear $fiscalYear, string $serial): string`
  - Format: `NND-{serial}` or configurable
  - Must be globally unique (check before insert)
- `generateInternalId(): string`
  - Returns a UUID v4
- `lookupByBarcode(string $barcode): ?Garment`
- `lookupBySerial(string $serial, FiscalYear $fiscalYear): ?Garment`
- `lookupById(string $internalId): ?Garment`

**4. GarmentPolicy:**
- viewAny: all authenticated users (all need to see stock)
- view: all authenticated users (but purchase_cost is hidden for non-admin)
- create: admin only (in Phase 0 context — will be via purchasing module in Phase 2)
- update: admin only
- viewPurchaseCost: admin only (gate already defined in AuthServiceProvider)

**5. GarmentCurrentStock model (app/Modules/Inventory/Models/GarmentCurrentStock.php):**
- Projection table — one row per garment
- Fields: garment_id (PK), current_status, current_branch_id, current_location_id, reservation_reference_type, reservation_reference_id, last_movement_id, updated_at
- This is a read-optimization table — maintained alongside the movement ledger (which is built in Session 1.10)

**6. GarmentCurrentStock migration:**
- `create_garment_current_stock_table`
- PK: garment_id (FK to garments)
- Indexes: (current_status, current_branch_id)

**7. Livewire components:**
- `GarmentList` — paginated table with filters (product, size, status, branch)
- `GarmentView` — detail view showing identity, status, location, movement history (movements added in 1.10)
- `GarmentLookup` — barcode/serial search component (for use in POS later)

**8. Views:**
- `resources/views/livewire/inventory/garments/list.blade.php`
- `resources/views/livewire/inventory/garments/view.blade.php`
- `resources/views/livewire/inventory/garments/lookup.blade.php`

**9. Routes:**
- `GET /garments` — GarmentList (all authenticated users)
- `GET /garments/{garment}` — GarmentView (all authenticated users)
- `GET /garments/lookup` — GarmentLookup (all authenticated users)

**10. InventoryServiceProvider:**
- Register Inventory module
- Load migrations
- Register GarmentIdentityService

**11. Audit integration:**
- Garment creation is audited
- Status changes are audited (with previous and new status)
- Barcode generation is audited

**12. Sensitive data:**
- `purchase_cost` is hidden from non-admin users at model level (in $hidden array)
- Only revealed via explicit `withPurchaseCost()` method when admin gate passes
- API Resources strip the field for non-admin tokens

**13. Tests (tests/Feature/Inventory/GarmentIdentityTest.php):**
- Garment can be created with product, size, fiscal year, and branch
- Internal ID is a valid UUID
- Barcode is globally unique
- Fiscal-year serial is in YY-NNNNN format
- Two garments cannot have the same barcode
- Two garments cannot have the same (fiscal_year_id, fiscal_year_serial)
- Garment is created with status 'available'
- Garment current stock projection is created alongside
- Lookup by barcode works
- Lookup by serial works
- Lookup by internal ID works
- Purchase cost is hidden from non-admin users
- Purchase cost is visible to admin
- Garment can be reserved (status change)
- Garment can be marked as sold (status change)
- Garment can be marked as damaged (status change)
- Status changes are audited
- Non-admin cannot create garments directly (creation is via purchasing module in Phase 2)
- All authenticated users can view garments (without purchase cost)

### Acceptance criteria — Session 1.9 (COMPLETE)
- [x] Internal ID (UUID) is immutable and globally unique
- [x] Barcode is globally unique and never reused
- [x] Fiscal-year serial is in YY-NNNNN format and unique per fiscal year
- [x] Current stock projection is created alongside garment
- [x] Purchase cost is hidden from non-admin users at model level
- [x] Garment lookup works by barcode, serial, and internal ID
- [x] Status changes are audited
- [x] 35 tests written (all acceptance criteria covered)

---

## Session 1.10: Inventory — Movement Ledger & Stock Projection

**Goal:** Build the append-only inventory movement ledger and the current-stock projection maintenance system. This is the backbone of inventory integrity.

**Estimated time:** 3-4 hours

### Prerequisites
- Session 1.9 complete (garment model exists)

### Deliverables

**1. InventoryMovement model (app/Modules/Inventory/Models/InventoryMovement.php):**
- Fields: garment_id, movement_type, from_branch_id, to_branch_id, from_location_id, to_location_id, from_status, to_status, reference_type, reference_id, performed_by, performed_at, notes
- Movement types: goods_receipt, transfer_dispatch, transfer_receive, reservation, reservation_release, sale, sale_offline, return_from_customer, return_to_supplier, damage_report, inspection_start, inspection_complete, stock_adjustment, stock_count_freeze, stock_count_release, missing_report, recovery, clearance_mark, disposal
- Append-only: no update or delete (enforce at model level)
- Relationships: `garment()` (BelongsTo), `fromBranch()`, `toBranch()`, `performedBy()` (BelongsTo User)
- Scopes: `forGarment($garmentId)`, `forBranch($branchId)`, `forType($type)`, `forDateRange($start, $end)`, `forReference($type, $id)`, `latest()`
- The model overrides `save()` to prevent updates (only inserts allowed)
- The model overrides `delete()` to prevent deletion

**2. InventoryMovement migration:**
- `create_inventory_movements_table` — all fields per database design
- Indexes: (garment_id, performed_at DESC), (movement_type, performed_at), (reference_type, reference_id)
- Note: Monthly partitioning will be configured when volume grows (Phase 10)

**3. InventoryMovementService (app/Modules/Inventory/Services/InventoryMovementService.php):**
- `record(Garment $garment, string $movementType, array $context = []): InventoryMovement`
  - Creates a new movement record (append-only)
  - Captures from_status, to_status, from_branch, to_branch automatically
  - Sets performed_by to current user
  - Updates garment's current_status and current_branch_id
  - Updates GarmentCurrentStock projection
  - All within a database transaction
  - Dispatches InventoryMovementRecorded event (for audit)
- `getHistory(Garment $garment): Collection` — full movement history for a garment
- `getRecentMovements(Branch $branch, int $limit = 50): Collection` — recent movements for a branch

**4. StockProjectionService (app/Modules/Inventory/Services/StockProjectionService.php):**
- `update(Garment $garment): void` — update the GarmentCurrentStock projection for a garment
  - Called within the same transaction as the movement record
  - Updates: current_status, current_branch_id, current_location_id, reservation_reference_type/id, last_movement_id, updated_at
- `reconcile(): array` — reconciliation job
  - Compares garment_current_stock against the latest movement for each garment
  - Returns array of drift records (garments where projection doesn't match ledger)
  - Dispatches alert if drift is found
- `getAvailableStock(Branch $branch): Collection` — available garments at a branch (uses projection, not ledger scan)
- `getAvailableStockForProduct(Product $product, ?ProductSize $size = null): Collection` — available garments for a product across all branches

**5. Reconciliation command:**
- `php artisan nondon:reconcile-stock` — runs StockProjectionService::reconcile()
- Scheduled hourly (in bootstrap/app.php schedule)
- Alerts admin if drift is found

**6. Events:**
- `InventoryMovementRecorded` event — dispatched after a movement is recorded
- Audit listener writes audit entry

**7. Tests (tests/Feature/Inventory/MovementLedgerTest.php):**
- Movement record is created when garment status changes
- Movement captures from_status and to_status correctly
- Movement captures from_branch and to_branch correctly
- Movement records performed_by (current user)
- Movement records performed_at (timestamp)
- Movement is append-only (cannot update)
- Movement is append-only (cannot delete)
- Garment current stock projection is updated on movement
- Multiple movements for a garment are ordered by performed_at DESC
- getHistory returns full lifecycle
- Stock projection matches latest movement
- Reconciliation detects drift (manual projection change)
- Reconciliation returns no drift when projection is correct
- Non-admin users cannot record movements directly (movements are via business operations)
- Movement recording is atomic (transaction rolls back on failure)
- All movements are audited via event listener

### Acceptance criteria — Session 1.10 (COMPLETE)
- [x] InventoryMovement model is append-only (no update/delete)
- [x] Every status change creates a movement record
- [x] Movement captures from/to status and from/to branch
- [x] GarmentCurrentStock projection is updated transactionally
- [x] Reconciliation job detects projection drift
- [x] Reconciliation is scheduled hourly
- [x] Movement history is queryable per garment
- [x] All movements are audited
- [x] 25 tests written (all acceptance criteria covered)

---

## Session 1.11: Inventory — Stock Visibility & Queries

**Goal:** Build the stock visibility and query system — cross-branch stock views, garment movement history, and reservation count display. These are the read-side queries that POS, transfers, and online orders will use.

**Estimated time:** 2-3 hours

### Prerequisites
- Session 1.10 complete

### Deliverables

**1. StockQueryService (app/Modules/Inventory/Services/StockQueryService.php):**
- `getAvailableStockForBranch(Branch $branch): Collection`
  - Returns available garments at a branch
  - Uses garment_current_stock projection (fast, no ledger scan)
  - Includes: garment_id, barcode, serial, product name, size, status
  - Excludes: purchase_cost (always)
- `getAvailableStockForAllBranches(): Collection`
  - Returns available stock grouped by branch
  - For cross-branch visibility (all roles can view, no supplier/cost info)
- `getStockForProduct(Product $product, ?ProductSize $size = null): Collection`
  - Returns available garments for a specific product/size across all branches
  - Grouped by branch
- `getStockSummary(): Collection`
  - Returns summary: per product, per size, per branch, available count
  - Uses summary projection (not raw garment scan)
- `getReservationCounts(): Collection`
  - Returns count of reserved garments per branch
  - For the manual reservation management display
- `searchGarments(string $term): Collection`
  - Search by barcode, serial, or product name
  - Returns matching garments with current status and branch

**2. MovementHistoryService:**
- `getGarmentHistory(Garment $garment): Collection`
  - Returns full movement history for a garment
  - Ordered by performed_at DESC
  - Includes: movement type, from/to branch, from/to status, performed by, timestamp, reference
  - This is the "buy → distributed → sold/returned → back to supplier" traceability view

**3. Livewire components:**
- `StockOverview` — dashboard widget showing stock summary per branch
- `StockAvailability` — searchable, filterable table of available stock across branches
- `GarmentHistory` — movement history timeline for a specific garment
- `ReservationBoard` — shows current reservation counts per branch (for manual management)

**4. Views:**
- `resources/views/livewire/inventory/stock/overview.blade.php`
- `resources/views/livewire/inventory/stock/availability.blade.php`
- `resources/views/livewire/inventory/garments/history.blade.php`
- `resources/views/livewire/inventory/stock/reservation-board.blade.php`

**5. Routes:**
- `GET /stock` — StockAvailability (all authenticated users)
- `GET /stock/overview` — StockOverview (admin, supervisors)
- `GET /garments/{garment}/history` — GarmentHistory (all authenticated users)
- `GET /stock/reservations` — ReservationBoard (admin, supervisors, online supervisor)

**6. Search vs. report boundary:**
- Stock availability is a real-time view (not a date-wise report) — allowed for all roles
- Garment history lookup is transactional search — allowed for all roles any date
- Stock summary is an aggregate — supervisors see own branch only; admin sees all
- Reservation board is operational — supervisors see own branch; admin sees all

**7. Tests (tests/Feature/Inventory/StockVisibilityTest.php):**
- Available stock for a branch returns only available garments
- Available stock for all branches groups by branch
- Stock for a product returns matching garments across branches
- Stock summary returns per-product, per-branch counts
- Reservation counts returns correct counts per branch
- Garment search by barcode works
- Garment search by serial works
- Garment search by product name works
- Garment history returns movements in DESC order
- Garment history includes all movement types
- Supervisor can view own branch stock but not other branches' detailed stock (summary only)
- Supervisor can view all-branch stock availability (for cross-branch visibility)
- Salesperson can view stock availability (for POS and customer service)
- Purchase cost is never included in stock query results
- Stock queries use projection table (not ledger scan)

### Acceptance criteria — Session 1.11 (COMPLETE)
- [x] Cross-branch stock visibility works for all roles (without supplier/cost info)
- [x] Garment movement history shows full lifecycle
- [x] Reservation count display works per branch
- [x] Stock queries use the projection table (fast, no ledger scan)
- [x] Purchase cost is never exposed in stock queries
- [x] Search vs. report boundary is respected
- [x] 25 tests written (all acceptance criteria covered)

---

## Session 1.12: Audit — Audit Log UI & Phase 1 Integration

**Goal:** Build the audit log query UI and verify Phase 1 end-to-end integration. All Phase 1 modules should work together, and all acceptance criteria should be verifiable.

**Estimated time:** 2-3 hours

### Prerequisites
- Session 1.11 complete

### Deliverables

**1. Audit query UI:**
- `AuditLogList` Livewire component — paginated audit log table
  - Filters: user, branch, action, category, date range
  - Admin: all branches, all dates
  - Supervisor: own branch, today only
  - Online supervisor: online channel, today only
  - Salesperson: no access
- `AuditLogView` — detail view of a single audit entry
  - Shows: actor, branch, action, reference, previous/new values, reason, IP, timestamp

**2. Views:**
- `resources/views/livewire/audit/list.blade.php`
- `resources/views/livewire/audit/view.blade.php`

**3. Routes:**
- `GET /audit` — AuditLogList (admin, supervisors, online supervisor)
- `GET /audit/{log}` — AuditLogView (admin, supervisors — scoped)

**4. Audit visibility enforcement:**
- Admin sees all audit entries (all branches, all dates)
- Supervisor sees own branch entries for today only
- Online supervisor sees online channel entries for today only
- All other roles: no access to audit log
- Enforcement at query level (scope) and policy level

**5. Dashboard integration:**
- Admin dashboard: shows recent audit activity summary
- Supervisor dashboard: shows own-branch today activity
- Role-appropriate widgets on dashboard

**6. Navigation finalization:**
- Complete the navigation bar with all Phase 1 module links
- Role-appropriate menu items (salesperson sees minimal menu; admin sees full menu)
- Active state indicators

**7. Phase 1 integration tests (tests/Feature/Phase1IntegrationTest.php):**
- Full lifecycle test: create user → assign role → login → create product → set price → create garment → record movement → view stock → audit log captures all actions
- Permission matrix verification: each role can/cannot do each action per the permission matrix
- Branch scope verification: supervisor only sees own branch data
- Audit visibility: admin sees all; supervisor sees own branch today; salesperson sees nothing
- Sensitive data: purchase cost hidden from non-admin in all views and APIs
- Fiscal year serial generation: correct format, concurrency-safe
- Garment movement history: complete lifecycle traceable

**8. Phase 1 acceptance criteria checklist:**
- Run through all acceptance criteria from Sessions 1.1-1.11
- Verify all tests pass
- Verify no linting errors
- Verify all modules are registered and working

### Acceptance criteria
- [ ] Audit log UI works with role-appropriate filtering
- [ ] Admin sees all audit entries; supervisor sees own branch today only
- [ ] Navigation is complete and role-appropriate
- [ ] Dashboard shows relevant information per role
- [ ] Full lifecycle integration test passes
- [ ] Permission matrix is verified for all 5 roles
- [ ] Branch scope is enforced for all branch-sensitive data
- [ ] Purchase cost is never exposed to non-admin users
- [ ] All Phase 1 tests pass (Sessions 1.1-1.12)
- [ ] No linting errors
- [ ] Phase 1 is ready for Phase 2 (Purchasing & Barcode Generation)

---

## 3. Session Execution Guidelines

### 3.1 Before starting a session

1. Ensure the previous session is complete and all its tests pass.
2. Review the session deliverables and acceptance criteria.
3. Check that the dependencies are in place.
4. Create a feature branch: `git checkout -b feature/session-1.X-description`

### 3.2 During a session

1. Implement deliverables in the order listed.
2. Write tests alongside implementation (test-driven where practical).
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

### 3.4 Quality principles

- **No shortcuts.** Every deliverable is implemented properly, not stubbed.
- **Tests are mandatory.** Every session has tests that must pass.
- **Audit everything.** Every business action writes an audit log entry.
- **Server-side authorization.** Every action is protected by a policy.
- **Branch scope.** Every branch-sensitive query is scoped.
- **Sensitive data protection.** Purchase cost and supplier info are never exposed to non-admin.
- **Atomic transactions.** All multi-step operations use database transactions.
- **Append-only integrity.** Movements and audit logs are never updated or deleted.

---

## 4. Phase 1 Exit Criteria

Phase 1 is complete when:

1. All 12 sessions are implemented and their tests pass.
2. The full lifecycle integration test (Session 1.12) passes.
3. All acceptance criteria from all sessions are verified.
4. No linting errors.
5. Code is committed and pushed to GitHub.
6. The system can:
   - Manage users, roles, and permissions with full authorization.
   - Manage branches and fiscal years with serial generation.
   - Manage products, sizes, and prices with effective-dated history.
   - Create garment records with immutable identity (UUID, barcode, serial).
   - Record inventory movements in an append-only ledger.
   - Maintain current-stock projection alongside the ledger.
   - Query stock across branches (without sensitive data).
   - Trace garment movement history (full lifecycle).
   - Audit all meaningful business actions with role-appropriate visibility.
7. Phase 2 (Purchasing & Barcode Generation) can begin.

---

*End of Phase 1 Session Breakdown*
