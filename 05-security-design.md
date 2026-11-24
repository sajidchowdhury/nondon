# Security Design Document
## Nondon — Multi-Branch Women's Dress Retail & Wholesale ERP

**Project:** Nondon
**Version:** 1.0
**Date:** 24 November 2026
**Status:** Design document — pending approval

---

## 1. Security Overview

### 1.1 Security objectives

- **Confidentiality:** Sensitive data (purchase costs, supplier information, profit, all-branch financial reports) is visible only to authorized roles.
- **Integrity:** Business transactions are immutable once completed. Corrections go through controlled workflows. No silent deletion or overwrite.
- **Availability:** System remains operational during internet outages (offline POS). Backup and recovery in place.
- **Accountability:** Every meaningful business action is attributed to a user, timestamped, and auditable.
- **Least privilege:** Users access only the information and actions required for their responsibilities.
- **Branch isolation:** Users operate only within their assigned branch. Cross-branch visibility is read-only for stock; no cross-branch transactions.

### 1.2 Threat model

| Threat | Mitigation |
|---|---|
| Unauthorized access to sensitive financial data | Role-based access control, server-side enforcement, audit logging |
| Tax officer demanding reports from a salesman | Salesman UI has no sensitive reports; underlying records exist for legal compliance but are not visible to salesperson role |
| Duplicate sale of the same garment | Row-level locking, atomic transactions, concurrency protection |
| Offline device selling a garment already sold online | Conflict detection on sync, admin resolution |
| Fraudulent stock adjustment | Only Main Branch Admin approves adjustments; full audit trail |
| Cash theft or discrepancy | Dual-confirmation handovers, daily closing with expected vs counted, admin investigation |
| Password guessing | 5-attempt lockout, secure hashing |
| Shared account usage | Prohibited by policy; unique usernames; login attempt logging |
| Session hijacking | HTTPS, secure cookies, session regeneration on login |
| SQL injection | Eloquent ORM with parameterized queries; no raw SQL with user input |
| CSRF | Laravel's built-in CSRF tokens on all forms |
| XSS | Blade's automatic escaping; Content Security Policy headers |

---

## 2. Authentication

### 2.1 Login process

```
User enters username + password
    │
    ▼
System validates credentials
    │
    ├── Invalid → Increment failed_login_attempts
    │              If attempts >= 5 → Lock account, log lockout
    │              Return "Invalid credentials"
    │
    └── Valid → Reset failed_login_attempts to 0
                 Check account is active and not locked
                 Check force_password_change flag
                 Create session
                 Log login attempt (success)
                 Regenerate session ID
                 Redirect to role selection (if multiple roles) or dashboard
```

### 2.2 Password policy

- Minimum length: 8 characters.
- Hashing: bcrypt (Laravel default) or argon2.
- Passwords never stored in plaintext.
- Passwords never logged.
- Password reset: admin generates reset, user forced to change at next login.
- Users can change their own password (requires current password).

### 2.3 Account lockout

- 5 consecutive failed attempts → account locked.
- `locked_at` timestamp recorded.
- Only Main Branch Admin can unlock.
- Successful login resets the counter.
- All lockout and unlock events are audited.

### 2.4 Session management

- Database-backed sessions (allows multiple device tracking).
- **No automatic inactivity logout** (client decision; pending security review for shared devices).
- Manual logout required.
- Session stores: user_id, active_role_id, active_branch_id, login timestamp, IP, user agent.
- Session ID regenerated on login (prevents session fixation).
- Secure cookie flags: `HttpOnly`, `Secure` (HTTPS only), `SameSite=Lax`.

### 2.5 Shared device concern

The no-auto-logout decision creates a risk on shared POS devices:
- User A logs in, walks away without logging out.
- User B uses the system under User A's identity.

**Mitigation (recommended for security review):**
- Physical POS devices should be assigned to individuals where possible.
- A "switch user" button should be prominent in the UI.
- Cash handovers and closing require password re-entry (step-up authentication).
- Consider a per-transaction PIN for sensitive operations.

---

## 3. Role-Based Access Control (RBAC)

### 3.1 Role definitions

| Role | Code | Description |
|---|---|---|
| Main Branch Admin | `main_branch_admin` | Full business authority. All modules, all branches, all dates. Also sells. |
| Branch Supervisor | `branch_supervisor` | Own branch operations, approvals, cash management, today-only aggregate reports. Also sells. |
| Salesperson | `salesperson` | Base selling, customer lookup, own cash, own today-only activity. |
| Online Sales Team | `online_team` | Online order management, refund initiation, courier operational tracking. |
| Online Sales Supervisor | `online_supervisor` | Full online-channel authority (branch-equivalent). Online refunds, courier settlement, cash closing. Also sells (online). |

### 3.2 Role assignment

- An employee can hold multiple roles (e.g., Salesperson + Branch Supervisor).
- Multi-role employees **switch between roles** in the UI.
- The active role determines the permission scope for the current session.
- Authorization checks use the active role, not the union of all assigned roles.
- Branch scope and sensitive-data restrictions always apply regardless of active role.

### 3.3 Permission model

```
Effective Permission = (Role Permissions UNION User Grant Permissions)
                       MINUS User Revoke Permissions
                       WITH Branch Scope Enforcement
                       WITH Sensitive Data Restrictions (always applied)
```

- **Role permissions:** Defined per role in `role_permissions` table.
- **User grant permissions:** Custom grants in `user_permissions` (granted = true).
- **User revoke permissions:** Custom revokes in `user_permissions` (granted = false).
- **Branch scope:** All queries filtered by the user's active branch.
- **Sensitive data restrictions:** Purchase cost, supplier info, profit — always hidden for non-admin roles, regardless of other permissions.

### 3.4 Permission naming convention

Permissions follow a `{module}.{action}.{scope}` pattern:

| Permission | Description |
|---|---|
| `pos.sale.create` | Create a new POS sale |
| `pos.sale.complete` | Complete and commit a POS sale |
| `pos.discount.apply` | Apply a discount to a sale item |
| `transfer.request.create` | Create a transfer request |
| `transfer.approve` | Approve a transfer request |
| `transfer.dispatch` | Dispatch a transfer |
| `transfer.receive` | Receive a transfer |
| `return.initiate` | Initiate a return |
| `return.approve.limited` | Approve return within configured limit |
| `return.approve.unlimited` | Approve return above limit (admin) |
| `cash.handover.initiate` | Initiate cash handover |
| `cash.handover.receive` | Receive/confirm cash handover |
| `cash.closing.submit` | Submit daily cash closing |
| `cash.discrepancy.resolve` | Resolve cash discrepancy (admin) |
| `inventory.view.all_branches` | View stock at all branches |
| `inventory.adjust.approve` | Approve stock adjustment (admin) |
| `customer.create` | Create customer |
| `customer.edit` | Edit customer (admin) |
| `customer.merge` | Merge duplicate customers (admin) |
| `product.create` | Create product/design (admin) |
| `product.price.update` | Update prices (admin) |
| `purchase.create` | Create purchase (admin) |
| `purchase.receive` | Receive goods |
| `supplier.manage` | Manage suppliers (admin) |
| `supplier.payment.record` | Record supplier payment (admin) |
| `expense.submit` | Submit expense |
| `expense.approve` | Approve expense (admin) |
| `report.view.own_today` | View own today's activity |
| `report.view.branch_today` | View own branch today's reports |
| `report.view.all_today` | View all branches today's reports |
| `report.view.historical` | View historical date-range reports (admin) |
| `report.view.purchase_cost` | View purchase cost (admin) |
| `report.view.profit` | View profit reports (admin) |
| `report.view.audit` | View audit logs |
| `employee.manage` | Manage employee accounts (admin) |
| `courier.settlement.reconcile` | Reconcile courier settlement (online supervisor) |
| `online.refund.approve.limited` | Approve online refund within limit |
| `reservation.release.manual` | Manually release a reservation |
| `stock_count.initiate` | Initiate stock count |
| `stock_count.freeze` | Apply stock count freeze |
| `fiscal_year.manage` | Manage fiscal years (admin) |
| `system_settings.manage` | Manage system settings (admin) |

---

## 4. Permission Matrix

### 4.1 Role × Action matrix

| Action / Permission | Main Admin | Branch Supervisor | Salesperson | Online Team | Online Supervisor |
|---|---|---|---|---|---|
| **POS Sales** | | | | | |
| Create POS sale | Yes | Yes | Yes | No | No |
| Complete POS sale | Yes | Yes | Yes | No | No |
| Apply discount (above minimum) | Yes | Yes | Yes | No | No |
| Offline POS mode | Yes | Yes | Yes | No | No |
| **Transfers** | | | | | |
| Create transfer request | Yes | Yes (own branch) | No | No | Yes (to online) |
| Approve transfer (from own branch) | Yes | Yes (own branch) | No | No | Yes (from online) |
| Dispatch transfer | Yes | Yes (own branch) | No | No | Yes |
| Receive transfer | Yes | Yes (own branch) | No | No | Yes |
| **Online Orders** | | | | | |
| Create online order | Yes | No | No | Yes | Yes |
| Confirm online order | Yes | No | No | Yes | Yes |
| Cancel online order | Yes | No | No | Yes | Yes |
| Dispatch to customer | Yes | No | No | Yes | Yes |
| Release reservation manually | Yes | No | No | No | Yes |
| **Returns** | | | | | |
| Initiate physical return | Yes | Yes | Yes | No | No |
| Approve return (within limit) | Yes | Yes (own branch) | No | No | No |
| Approve return (above limit) | Yes | No | No | No | No |
| Initiate online return | Yes | No | No | Yes | Yes |
| Approve online return (within limit) | Yes | No | No | No | Yes |
| Approve online return (above limit) | Yes | No | No | No | No |
| **Cash Management** | | | | | |
| Individual cash collection | Yes | Yes | Yes | No | No |
| Initiate cash handover | Yes | Yes | Yes | No | No |
| Receive cash handover | Yes | Yes | No | No | Yes |
| Submit daily cash closing | Yes | Yes | No | No | Yes |
| Resolve cash discrepancy | Yes | No | No | No | No |
| **Courier Reconciliation** | | | | | |
| Enter courier tracking (operational) | Yes | No | No | Yes | Yes |
| Reconcile COD settlement | Yes | No | No | No | Yes |
| **Inventory** | | | | | |
| View own branch stock | Yes | Yes | Yes | Yes | Yes |
| View all branches stock | Yes | Yes | Yes | Yes | Yes |
| View garment movement history | Yes | Yes | Yes | Yes | Yes |
| Approve stock adjustment | Yes | No | No | No | No |
| Initiate stock count | Yes | Yes (own branch) | No | No | Yes |
| Apply stock count freeze | Yes | Yes (own branch) | No | No | Yes |
| Report damaged garment | Yes | Yes | Yes | Yes | Yes |
| Approve damage disposition | Yes | No | No | No | No |
| **Catalog & Pricing** | | | | | |
| Create/edit products | Yes | No | No | No | No |
| Update prices | Yes | No | No | No | No |
| **Purchasing** | | | | | |
| Create purchase | Yes | No | No | No | No |
| Receive goods | Yes | Yes (receiving staff) | No | No | No |
| View purchase cost | Yes | No | No | No | No |
| View supplier info | Yes | No | No | No | No |
| **Supplier Payments** | | | | | |
| Record supplier payment | Yes | No | No | No | No |
| **Expenses** | | | | | |
| Submit expense | Yes | Yes (own branch) | No | No | Yes |
| Approve expense | Yes | No | No | No | No |
| **Customers** | | | | | |
| Create customer | Yes | Yes | Yes | Yes | Yes |
| Edit customer | Yes | No | No | No | No |
| Merge customers | Yes | No | No | No | No |
| **Reporting** | | | | | |
| View own today's activity | Yes | Yes | Yes | Yes | Yes |
| View own branch today's reports | Yes | Yes | No | No | No |
| View online channel today's reports | Yes | No | No | No | Yes |
| View all branches today's reports | Yes | No | No | No | No |
| View historical date-range reports | Yes | No | No | No | No |
| View purchase cost reports | Yes | No | No | No | No |
| View profit reports | Yes | No | No | No | No |
| View audit logs | Yes | Yes (own branch, today) | No | No | No |
| **Administration** | | | | | |
| Manage employee accounts | Yes | No | No | No | No |
| Assign roles | Yes | No | No | No | No |
| Reset passwords | Yes | No | No | No | No |
| Unlock accounts | Yes | No | No | No | No |
| Manage fiscal years | Yes | No | No | No | No |
| Manage system settings | Yes | No | No | No | No |
| Configure scheduled reports | Yes | No | No | No | No |

### 4.2 Data scope enforcement

| Data type | Main Admin | Branch Supervisor | Salesperson | Online Team | Online Supervisor |
|---|---|---|---|---|---|
| Own branch sales | All dates | Today only | Own today | N/A | N/A |
| Other branches' sales | All dates | No | No | No | No |
| Online channel sales | All dates | No | No | Own today | Today |
| Purchase cost | Yes | No | No | No | No |
| Supplier info | Yes | No | No | No | No |
| Profit/margin | Yes | No | No | No | No |
| Customer PII | All | Own branch + search | Own branch + search | Online customers + search | Online customers + search |
| Audit logs | All, all dates | Own branch, today | No | No | Online channel, today |
| Cash accounts | All | Own branch | Own account | N/A | Online channel |

### 4.3 Sensitive data restrictions (always enforced)

Regardless of role combination or active role, the following data is **never visible** to non-admin users:

- Purchase cost per garment.
- Purchase cost per purchase batch.
- Supplier identity and contact information.
- Supplier payment history.
- Profit/margin calculations.
- All-branch historical financial reports.
- Salary and payroll information.

**Implementation:** These restrictions are enforced at:
- **Query level:** Eloquent global scopes exclude restricted columns.
- **Response level:** API Resources strip restricted fields.
- **View level:** Blade templates conditionally render based on permission.
- **Audit level:** Any access to restricted data is logged.

**Critical principle:** Role-based access restrictions must not be used to falsify, conceal, or delete legally required business records. The underlying records always exist and are retrievable for legal/tax audit, even if no salesman can see them in the UI.

---

## 5. Authorization Implementation

### 5.1 Policy classes

Every sensitive action has a Laravel Policy:

```php
// Example: SaleInvoicePolicy
class SaleInvoicePolicy
{
    public function create(User $user): bool
    {
        return $user->can('pos.sale.create')
            && $user->activeBranch !== null;
    }

    public function complete(User $user, SaleInvoice $invoice): bool
    {
        return $user->can('pos.sale.complete')
            && $invoice->branch_id === $user->active_branch_id;
    }

    public function view(User $user, SaleInvoice $invoice): bool
    {
        // Admin: all
        if ($user->hasRole('main_branch_admin')) return true;

        // Supervisor: own branch, today only
        if ($user->hasRole('branch_supervisor')
            && $invoice->branch_id === $user->active_branch_id
            && $invoice->completed_at->isToday()) return true;

        // Salesperson: own sales, today only
        if ($user->id === $invoice->salesperson_id
            && $invoice->completed_at->isToday()) return true;

        return false;
    }
}
```

### 5.2 Branch scoping

Global scope on all branch-sensitive models:

```php
// BranchScope global scope
class BranchScope implements Scope
{
    public function apply(Builder $builder, Model $model)
    {
        $user = Auth::user();
        if (!$user || $user->hasRole('main_branch_admin')) return;

        $builder->where('branch_id', $user->active_branch_id);
    }
}
```

This automatically filters all queries to the user's active branch unless the user is Main Branch Admin.

### 5.3 Sensitive column exclusion

```php
// Garment model
class Garment extends Model
{
    protected $hidden = ['purchase_cost'];

    public function withPurchaseCost(): static
    {
        $this->makeVisible('purchase_cost');
        return $this;
    }
}

// Only called in admin-authorized contexts
if ($user->can('report.view.purchase_cost')) {
    $garment->withPurchaseCost();
}
```

### 5.4 Server-side enforcement

Authorization is **always enforced server-side**:
- UI hiding is a convenience, not a security measure.
- Every API endpoint and controller method calls a policy.
- Every Livewire component checks permissions before acting.
- Direct database access is prohibited — all access goes through Eloquent models with scopes and policies.

---

## 6. Audit Logging

### 6.1 What is audited

Every meaningful business action writes to `audit_logs`:

| Category | Audited actions |
|---|---|
| **Inventory** | goods receipt, barcode generation, stock movement, transfer approval/dispatch/receive, stock count, freeze, adjustment, missing report, recovery, damage report, disposition |
| **Sales** | POS sale completion, offline sale sync, invoice correction, discount application |
| **Online** | order confirmation, reservation creation/release, courier dispatch, cancellation |
| **Returns** | return initiation, approval, refund processing |
| **Cash** | cash collection, handover, closing, discrepancy resolution |
| **Finance** | expense submission/approval, supplier payment, courier settlement |
| **Admin** | price update, product create/edit, customer edit/merge, employee create/edit, role assignment, password reset, account unlock |
| **Security** | login success/failure, lockout, unlock, role switch, sensitive data access |
| **System** | fiscal year management, settings change, scheduled report send |

### 6.2 Audit log structure

Each audit entry records:
- `user_id` — who performed the action.
- `branch_id` — in which branch context.
- `action` — what action (e.g., `sale.complete`).
- `category` — module category.
- `reference_type` and `reference_id` — what entity was affected.
- `previous_values` — JSON of changed field values before (for updates).
- `new_values` — JSON of changed field values after.
- `reason` — user-provided reason (for corrections, adjustments).
- `ip_address` — requester IP.
- `user_agent` — browser/device.
- `performed_at` — timestamp.

### 6.3 Audit log properties

- **Append-only:** No UPDATE or DELETE operations allowed.
- **Protected:** Database-level protection (no direct table access; only through Audit service).
- **Immutable:** Once written, an audit entry cannot be modified.
- **Tamper-evident:** (Future enhancement) chain entries with hash of previous entry.

### 6.4 Audit retention

- **Business audit logs:** Retained for the full multi-year period (no auto-deletion). Required for tax compliance and investigation.
- **Login attempt logs:** Retained for 90 days.
- **Only meaningful events logged:** UI reads, page views, and non-business actions are not logged to avoid noise.

### 6.5 Audit visibility

| Role | Audit access |
|---|---|
| Main Branch Admin | All branches, all dates, all categories |
| Branch Supervisor | Own branch only, current business day only, cannot edit/delete |
| Online Sales Supervisor | Online channel only, current business day only |
| All other employees | No direct audit-log access |

---

## 7. Data Protection

### 7.1 Data in transit

- **HTTPS/TLS** for all communication (Nginx with Let's Encrypt SSL).
- No plaintext HTTP; all HTTP redirected to HTTPS.
- HSTS header enforced.
- Internal API calls also over HTTPS.

### 7.2 Data at rest

- Database: PostgreSQL data files on the server.
- Passwords: hashed (bcrypt/argon2), never stored plaintext.
- Sensitive columns: access-controlled via policies and scopes (not encrypted at rest in initial release).
- Backups: weekly (client decision); stored on server (offsite copy recommended).

### 7.3 Data in cache (offline POS)

- Local cache (IndexedDB/LocalStorage) contains: product catalog, prices, stock snapshot, customer phone index.
- **Risk:** If a POS device is compromised, cached data is accessible.
- **Mitigation:**
  - Cache is per-device, per-branch.
  - Cache is cleared on logout.
  - No purchase cost, supplier info, or profit data is ever cached locally.
  - Customer phone index is minimal (phone + name only).
  - Devices should be physically secured.

### 7.4 PII handling

- Customer phone numbers are collected (mandatory for POS).
- Customer names are optional.
- Delivery addresses are collected for online customers.
- PII is accessible to:
  - Salesperson: own branch customers.
  - Online Team/Supervisor: online customers.
  - Branch Supervisor: own branch customers.
  - Main Admin: all customers.
- PII is never exposed in reports visible to unauthorized roles.
- Customer data is not shared with third parties (no integration in initial release).

---

## 8. Input Validation

### 8.1 Form Requests

Every HTTP request and Livewire action is validated through a Form Request class:

```php
class CompleteSaleRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'customer_phone' => 'required|string|max:50',
            'customer_name' => 'nullable|string|max:255',
            'items' => 'required|array|min:1',
            'items.*.garment_id' => 'required|exists:garments,id',
            'items.*.discount' => 'nullable|numeric|min:0',
            'payments' => 'required|array|min:1',
            'payments.*.method' => 'required|exists:payment_methods,name',
            'payments.*.amount' => 'required|numeric|min:0.01',
            'payments.*.reference' => 'nullable|string|max:100',
        ];
    }
}
```

### 8.2 Business rule validation

Beyond format validation, business rules are validated in services:

- Garment must be `available` before adding to sale.
- Final price must be >= minimum price.
- Total payment must be >= total amount for POS sales.
- Return must be within 7-day period.
- Transfer request must be from a valid source branch.
- Cash handover amounts must be positive.

### 8.3 SQL injection prevention

- Eloquent ORM uses parameterized queries.
- No raw SQL with user input.
- Any raw query uses bindings: `DB::select('SELECT * FROM garments WHERE barcode = ?', [$barcode])`.

---

## 9. CSRF and XSS Protection

### 9.1 CSRF

- Laravel's CSRF middleware enabled on all web routes.
- CSRF token included in all forms via `@csrf` Blade directive.
- API routes use Sanctum tokens (not CSRF, but token-based auth).

### 9.2 XSS

- Blade's `{{ }}` escaping by default (converts to HTML entities).
- `{!! !!}` used only for trusted content (never user input).
- Content Security Policy header to restrict script sources.
- User input never rendered as HTML without sanitization.

---

## 10. Security Review Items

The following decisions require security review before go-live:

### 10.1 No auto-logout (HIGH priority)

**Risk:** Shared POS devices allow identity confusion.
**Review needed:** Decide whether to implement:
- Auto-logout after configurable inactivity period.
- Step-up authentication for sensitive operations (cash handover, closing, returns).
- Per-transaction PIN.
- Physical device assignment to individuals.

### 10.2 Weekly on-server backup (MEDIUM priority)

**Risk:** Up to 6 days data loss; backup lost if server fails.
**Review needed:** Decide whether to implement:
- Daily backup.
- Offsite backup copy.
- Continuous WAL archiving for point-in-time recovery.

### 10.3 No tax on invoice (COMPLIANCE priority)

**Risk:** Bangladesh VAT law may require tax breakdown on invoices.
**Review needed:** Confirm with client's tax adviser whether no-tax invoice is compliant.

### 10.4 Offline POS cache (MEDIUM priority)

**Risk:** Cached data on compromised device.
**Review needed:** Confirm:
- Devices are physically secured.
- Cache clearing on logout is enforced.
- No sensitive data in cache.

---

## 11. Security Checklist (Pre-Launch)

- [ ] HTTPS enforced with valid SSL certificate.
- [ ] HSTS header configured.
- [ ] Secure cookie flags set (HttpOnly, Secure, SameSite).
- [ ] CSRF middleware enabled on all web routes.
- [ ] All Form Request validations in place.
- [ ] All Policy classes implemented and tested.
- [ ] Branch scoping global scope applied to all branch-sensitive models.
- [ ] Sensitive columns (purchase_cost) hidden from non-admin queries.
- [ ] Audit logging implemented for all meaningful business actions.
- [ ] Audit log is append-only (no UPDATE/DELETE).
- [ ] Password hashing (bcrypt) configured.
- [ ] Account lockout after 5 failed attempts.
- [ ] Login attempt logging.
- [ ] No shared accounts (enforced by unique username).
- [ ] Session ID regeneration on login.
- [ ] Offline cache clears on logout.
- [ ] No sensitive data in offline cache.
- [ ] Backup schedule configured and tested.
- [ ] Recovery procedure tested.
- [ ] Security review of no-auto-logout decision completed.
- [ ] Tax compliance review completed.
- [ ] Input validation on all endpoints.
- [ ] SQL injection prevention verified (no raw SQL with user input).
- [ ] XSS prevention verified (Blade escaping, CSP header).
- [ ] Error messages do not expose sensitive data.
- [ ] Production environment has `APP_DEBUG=false`.
- [ ] Database credentials not in code (environment variables).
- [ ] API tokens stored securely (not in code).

---

*End of Security Design Document*
