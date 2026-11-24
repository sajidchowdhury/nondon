# Database Design Document
## Nondon — Multi-Branch Women's Dress Retail & Wholesale ERP

**Project:** Nondon
**Version:** 1.0
**Date:** 24 November 2026
**Database:** PostgreSQL 15+
**Status:** Design document — pending approval

---

## 1. Design Principles

### 1.1 Core principles

- **Garment-level granularity.** Every physical garment is a first-class entity with immutable identity.
- **Append-only movement ledger.** Inventory movements are never deleted or overwritten; corrections are new movement records.
- **Denormalized current-stock projection.** A projection table maintains the current state of each garment, updated transactionally alongside the ledger.
- **Referential integrity.** All foreign keys enforced. No orphaned records.
- **Uniqueness at the database level.** Unique constraints on garment IDs, barcodes, fiscal-year serials, and other identifiers.
- **Atomic transactions.** Multi-step operations wrapped in database transactions with row-level locking.
- **Auditability.** Every meaningful business action writes to the audit log.

### 1.2 Naming conventions

- Tables: `snake_case`, plural (e.g., `garments`, `sale_invoices`).
- Columns: `snake_case`.
- Foreign keys: `{singular_table}_id` (e.g., `branch_id`, `garment_id`).
- Timestamps: `created_at`, `updated_at` on all tables; `deleted_at` for soft deletes where applicable.
- Enums: stored as `VARCHAR` with check constraints or PostgreSQL enum types.
- UUIDs: used for client-generated IDs (offline sync), stored as `UUID` type.

### 1.3 Data types

| Purpose | PostgreSQL type |
|---|---|
| Primary keys | `BIGSERIAL` (internal) |
| External/client IDs | `UUID` |
| Monetary values | `NUMERIC(12,2)` — never FLOAT |
| Timestamps | `TIMESTAMPTZ` |
| Status fields | `VARCHAR(50)` with CHECK constraint |
| Short text | `VARCHAR(255)` |
| Long text | `TEXT` |
| Booleans | `BOOLEAN` |
| JSON data | `JSONB` (for flexible config) |

---

## 2. Entity Relationship Overview

```
+-----------+     +-----------+     +-----------+
|  users    |     |  branches |     | fiscal_   |
|           |     |           |     | years     |
+-----+-----+     +-----+-----+     +-----------+
      |                 |
      | (assigned to)   | (holds stock)
      |                 |
+-----+-----+     +-----+-----+     +-----------+
|  roles    |     |  garments |---->| products  |
|           |     |           |     |  designs  |
+-----------+     +-----+-----+     +-----------+
                        |
                        | (movements)
                        |
                  +-----+-----+     +-----------+
                  | inventory_|---->| purchases |
                  | movements|     | suppliers |
                  +-----------+     +-----------+
                        |
          +-------------+-------------+
          |             |             |
    +-----+-----+ +-----+-----+ +-----+-----+
    | sale_     | | transfers | | online_   |
    | invoices  | |           | | orders    |
    +-----------+ +-----------+ +-----------+
          |
    +-----+-----+
    | returns   |
    | cash_     |
    | accounts  |
    | expenses  |
    | audit_    |
    | logs      |
    +-----------+
```

---

## 3. Schema Definition

### 3.1 Identity & Access Module

#### `users`
```sql
CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255),
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    assigned_branch_id BIGINT REFERENCES branches(id),
    is_online_channel BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    failed_login_attempts INT DEFAULT 0,
    locked_at TIMESTAMPTZ,
    locked_by BIGINT REFERENCES users(id),
    password_changed_at TIMESTAMPTZ,
    force_password_change BOOLEAN DEFAULT FALSE,
    last_login_at TIMESTAMPTZ,
    last_login_ip VARCHAR(45),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `roles`
```sql
CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL, -- main_branch_admin, branch_supervisor, salesperson, online_team, online_supervisor
    display_name VARCHAR(100) NOT NULL,
    description TEXT,
    is_system_role BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `user_roles`
```sql
CREATE TABLE user_roles (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    assigned_by BIGINT REFERENCES users(id),
    assigned_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, role_id)
);
```

#### `permissions`
```sql
CREATE TABLE permissions (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) UNIQUE NOT NULL, -- e.g., 'pos.sale.complete', 'transfer.approve', 'report.view.all_branches'
    display_name VARCHAR(255) NOT NULL,
    module VARCHAR(50) NOT NULL,
    description TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `role_permissions`
```sql
CREATE TABLE role_permissions (
    role_id BIGINT NOT NULL REFERENCES roles(id) ON DELETE CASCADE,
    permission_id BIGINT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    PRIMARY KEY (role_id, permission_id)
);
```

#### `user_permissions`
Custom permissions granted to or revoked from a specific user (overrides role permissions).

```sql
CREATE TABLE user_permissions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    permission_id BIGINT NOT NULL REFERENCES permissions(id) ON DELETE CASCADE,
    granted BOOLEAN NOT NULL, -- TRUE = grant, FALSE = revoke
    granted_by BIGINT REFERENCES users(id),
    granted_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(user_id, permission_id)
);
```

#### `login_attempts`
```sql
CREATE TABLE login_attempts (
    id BIGSERIAL PRIMARY KEY,
    username VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    success BOOLEAN NOT NULL,
    attempted_at TIMESTAMPTZ DEFAULT NOW(),
    failure_reason VARCHAR(255)
);
CREATE INDEX idx_login_attempts_username ON login_attempts(username, attempted_at DESC);
```

#### `sessions`
```sql
CREATE TABLE sessions (
    id VARCHAR(255) PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    active_role_id BIGINT REFERENCES roles(id),
    active_branch_id BIGINT REFERENCES branches(id),
    ip_address VARCHAR(45),
    user_agent TEXT,
    payload TEXT,
    last_activity TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_sessions_user ON sessions(user_id);
CREATE INDEX idx_sessions_last_activity ON sessions(last_activity);
```

---

### 3.2 Organization Module

#### `branches`
```sql
CREATE TABLE branches (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(20) UNIQUE NOT NULL, -- M, B1, B2, B3, ONLINE
    name VARCHAR(100) NOT NULL,
    type VARCHAR(20) NOT NULL CHECK (type IN ('main', 'physical', 'online')),
    address TEXT,
    phone VARCHAR(50),
    is_active BOOLEAN DEFAULT TRUE,
    is_online_channel BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `stock_locations`
Supports multiple stock locations per branch in the future. Initially, one location per branch.

```sql
CREATE TABLE stock_locations (
    id BIGSERIAL PRIMARY KEY,
    branch_id BIGINT NOT NULL REFERENCES branches(id),
    code VARCHAR(20) NOT NULL,
    name VARCHAR(100) NOT NULL,
    type VARCHAR(20) NOT NULL CHECK (type IN ('retail', 'warehouse', 'damaged', 'inspection', 'online_transient')),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(branch_id, code)
);
```

#### `fiscal_years`
```sql
CREATE TABLE fiscal_years (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(20) UNIQUE NOT NULL, -- FY 25_26
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    serial_prefix VARCHAR(2) NOT NULL, -- 25
    current_serial_sequence INT DEFAULT 0, -- next serial counter
    is_active BOOLEAN DEFAULT TRUE,
    is_closed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(start_date)
);
```

---

### 3.3 Catalog Module

#### `products`
```sql
CREATE TABLE products (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    category VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    deactivated_at TIMESTAMPTZ,
    deactivated_by BIGINT REFERENCES users(id),
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `product_sizes`
```sql
CREATE TABLE product_sizes (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES products(id) ON DELETE CASCADE,
    size_label VARCHAR(20) NOT NULL, -- 32, 34, 36, etc.
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(product_id, size_label)
);
```

#### `product_prices`
Price history per product (or product-size). Price is effective-dated so historical invoices retain the price at sale time.

```sql
CREATE TABLE product_prices (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT NOT NULL REFERENCES products(id),
    product_size_id BIGINT REFERENCES product_sizes(id), -- NULL = applies to all sizes
    standard_price NUMERIC(12,2) NOT NULL,
    minimum_price NUMERIC(12,2) NOT NULL,
    effective_from TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    effective_to TIMESTAMPTZ,
    set_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_product_prices_effective ON product_prices(product_id, effective_from, effective_to);
```

---

### 3.4 Purchasing Module

#### `suppliers`
```sql
CREATE TABLE suppliers (
    id BIGSERIAL PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    contact_person VARCHAR(255),
    phone VARCHAR(50),
    email VARCHAR(255),
    address TEXT,
    outstanding_balance NUMERIC(12,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `purchases`
```sql
CREATE TABLE purchases (
    id BIGSERIAL PRIMARY KEY,
    purchase_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id BIGINT NOT NULL REFERENCES suppliers(id),
    fiscal_year_id BIGINT NOT NULL REFERENCES fiscal_years(id),
    purchase_date DATE NOT NULL,
    expected_delivery_date DATE,
    status VARCHAR(20) NOT NULL CHECK (status IN ('draft', 'confirmed', 'received', 'partially_received', 'cancelled')),
    total_amount NUMERIC(12,2),
    total_cost NUMERIC(12,2),
    notes TEXT,
    created_by BIGINT NOT NULL REFERENCES users(id),
    confirmed_at TIMESTAMPTZ,
    confirmed_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_purchases_supplier ON purchases(supplier_id, purchase_date);
CREATE INDEX idx_purchases_status ON purchases(status);
```

#### `purchase_items`
```sql
CREATE TABLE purchase_items (
    id BIGSERIAL PRIMARY KEY,
    purchase_id BIGINT NOT NULL REFERENCES purchases(id) ON DELETE CASCADE,
    product_id BIGINT NOT NULL REFERENCES products(id),
    product_size_id BIGINT REFERENCES product_sizes(id),
    ordered_quantity INT NOT NULL,
    received_quantity INT DEFAULT 0,
    unit_cost NUMERIC(12,2) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `goods_receipts`
```sql
CREATE TABLE goods_receipts (
    id BIGSERIAL PRIMARY KEY,
    purchase_id BIGINT NOT NULL REFERENCES purchases(id),
    receipt_number VARCHAR(50) UNIQUE NOT NULL,
    received_date DATE NOT NULL,
    received_by BIGINT NOT NULL REFERENCES users(id),
    total_received_quantity INT NOT NULL,
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

---

### 3.5 Inventory Module (Core)

#### `garments`
The central entity — one row per physical garment.

```sql
CREATE TABLE garments (
    id BIGSERIAL PRIMARY KEY,
    internal_id UUID UNIQUE NOT NULL, -- globally unique, immutable
    barcode VARCHAR(100) UNIQUE NOT NULL, -- permanent barcode, globally unique, never reused
    fiscal_year_id BIGINT NOT NULL REFERENCES fiscal_years(id),
    fiscal_year_serial VARCHAR(20) NOT NULL, -- e.g., 25-00001
    product_id BIGINT NOT NULL REFERENCES products(id),
    product_size_id BIGINT REFERENCES product_sizes(id),
    purchase_id BIGINT REFERENCES purchases(id),
    purchase_item_id BIGINT REFERENCES purchase_items(id),
    goods_receipt_id BIGINT REFERENCES goods_receipts(id),
    purchase_cost NUMERIC(12,2), -- restricted visibility, only admin
    current_status VARCHAR(30) NOT NULL DEFAULT 'available' CHECK (
        current_status IN ('available', 'reserved', 'in_transit', 'sold',
                          'damaged', 'under_inspection', 'missing',
                          'frozen', 'clearance', 'disposed', 'returned_to_supplier')
    ),
    current_branch_id BIGINT REFERENCES branches(id),
    current_location_id BIGINT REFERENCES stock_locations(id),
    is_clearance BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(fiscal_year_id, fiscal_year_serial)
);
CREATE INDEX idx_garments_barcode ON garments(barcode);
CREATE INDEX idx_garments_product ON garments(product_id, product_size_id);
CREATE INDEX idx_garments_status_branch ON garments(current_status, current_branch_id);
CREATE INDEX idx_garments_serial ON garments(fiscal_year_id, fiscal_year_serial);
```

#### `garment_current_stock`
Denormalized projection — maintained alongside the movement ledger for fast reads.

```sql
CREATE TABLE garment_current_stock (
    garment_id BIGINT PRIMARY KEY REFERENCES garments(id),
    current_status VARCHAR(30) NOT NULL,
    current_branch_id BIGINT REFERENCES branches(id),
    current_location_id BIGINT REFERENCES stock_locations(id),
    reservation_reference_type VARCHAR(30), -- 'transfer_request', 'online_order'
    reservation_reference_id BIGINT,
    last_movement_id BIGINT,
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_gcs_status_branch ON garment_current_stock(current_status, current_branch_id);
CREATE INDEX idx_gcs_product ON garment_current_stock(garment_id); -- join via garment for product filter
```

#### `inventory_movements`
The append-only ledger. Every stock change creates a new row. Never UPDATE or DELETE.

```sql
CREATE TABLE inventory_movements (
    id BIGSERIAL PRIMARY KEY,
    garment_id BIGINT NOT NULL REFERENCES garments(id),
    movement_type VARCHAR(30) NOT NULL CHECK (
        movement_type IN ('goods_receipt', 'transfer_dispatch', 'transfer_receive',
                         'reservation', 'reservation_release', 'sale', 'sale_offline',
                         'return_from_customer', 'return_to_supplier', 'damage_report',
                         'inspection_start', 'inspection_complete', 'stock_adjustment',
                         'stock_count_freeze', 'stock_count_release', 'missing_report',
                         'recovery', 'clearance_mark', 'disposal')
    ),
    from_branch_id BIGINT REFERENCES branches(id),
    to_branch_id BIGINT REFERENCES branches(id),
    from_location_id BIGINT REFERENCES stock_locations(id),
    to_location_id BIGINT REFERENCES stock_locations(id),
    from_status VARCHAR(30),
    to_status VARCHAR(30),
    reference_type VARCHAR(30), -- 'purchase', 'transfer', 'sale_invoice', 'online_order', 'return', etc.
    reference_id BIGINT,
    performed_by BIGINT NOT NULL REFERENCES users(id),
    performed_at TIMESTAMPTZ DEFAULT NOW(),
    notes TEXT
);
CREATE INDEX idx_movements_garment ON inventory_movements(garment_id, performed_at DESC);
CREATE INDEX idx_movements_type ON inventory_movements(movement_type, performed_at);
CREATE INDEX idx_movements_reference ON inventory_movements(reference_type, reference_id);
```

**Partitioning strategy:** Partition `inventory_movements` by `performed_at` (monthly partitions) once the table grows beyond ~10M rows. This keeps old data queryable without slowing recent queries.

---

### 3.6 Transfers Module

#### `transfer_requests`
```sql
CREATE TABLE transfer_requests (
    id BIGSERIAL PRIMARY KEY,
    request_number VARCHAR(50) UNIQUE NOT NULL,
    from_branch_id BIGINT NOT NULL REFERENCES branches(id),
    to_branch_id BIGINT NOT NULL REFERENCES branches(id),
    status VARCHAR(20) NOT NULL CHECK (
        status IN ('pending', 'approved', 'rejected', 'dispatched', 'received',
                   'partially_received', 'cancelled', 'discrepancy')
    ),
    requested_by BIGINT NOT NULL REFERENCES users(id),
    requested_at TIMESTAMPTZ DEFAULT NOW(),
    approved_by BIGINT REFERENCES users(id),
    approved_at TIMESTAMPTZ,
    rejected_by BIGINT REFERENCES users(id),
    rejected_at TIMESTAMPTZ,
    rejection_reason TEXT,
    dispatched_by BIGINT REFERENCES users(id),
    dispatched_at TIMESTAMPTZ,
    received_by BIGINT REFERENCES users(id),
    received_at TIMESTAMPTZ,
    online_order_id BIGINT, -- FK to online_orders if this transfer is for an online order
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_transfers_status ON transfer_requests(status);
CREATE INDEX idx_transfers_branches ON transfer_requests(from_branch_id, to_branch_id);
```

#### `transfer_request_items`
```sql
CREATE TABLE transfer_request_items (
    id BIGSERIAL PRIMARY KEY,
    transfer_request_id BIGINT NOT NULL REFERENCES transfer_requests(id) ON DELETE CASCADE,
    garment_id BIGINT NOT NULL REFERENCES garments(id),
    product_id BIGINT NOT NULL REFERENCES products(id),
    product_size_id BIGINT REFERENCES product_sizes(id),
    dispatched BOOLEAN DEFAULT FALSE,
    received BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `transfer_discrepancies`
```sql
CREATE TABLE transfer_discrepancies (
    id BIGSERIAL PRIMARY KEY,
    transfer_request_id BIGINT NOT NULL REFERENCES transfer_requests(id),
    expected_quantity INT NOT NULL,
    received_quantity INT NOT NULL,
    discrepancy_type VARCHAR(20) CHECK (discrepancy_type IN ('shortage', 'excess', 'wrong_item')),
    missing_garment_ids BIGINT[], -- array of garment IDs not received
    reason VARCHAR(255),
    investigated_by BIGINT REFERENCES users(id),
    resolved_by BIGINT REFERENCES users(id),
    resolution_notes TEXT,
    status VARCHAR(20) DEFAULT 'open' CHECK (status IN ('open', 'investigating', 'resolved')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

---

### 3.7 Sales (POS) Module

#### `sale_invoices`
```sql
CREATE TABLE sale_invoices (
    id BIGSERIAL PRIMARY KEY,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    branch_id BIGINT NOT NULL REFERENCES branches(id),
    salesperson_id BIGINT NOT NULL REFERENCES users(id),
    customer_id BIGINT REFERENCES customers(id),
    sale_type VARCHAR(20) NOT NULL CHECK (sale_type IN ('retail', 'wholesale')),
    subtotal NUMERIC(12,2) NOT NULL,
    total_discount NUMERIC(12,2) DEFAULT 0,
    total_amount NUMERIC(12,2) NOT NULL,
    total_paid NUMERIC(12,2) NOT NULL,
    status VARCHAR(20) NOT NULL CHECK (status IN ('draft', 'completed', 'void')),
    is_offline_sale BOOLEAN DEFAULT FALSE,
    offline_uuid UUID, -- for dedup on sync
    completed_at TIMESTAMPTZ,
    fiscal_year_id BIGINT REFERENCES fiscal_years(id),
    return_policy_snapshot JSONB, -- return policy at time of sale
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_invoices_branch_date ON sale_invoices(branch_id, completed_at);
CREATE INDEX idx_invoices_salesperson ON sale_invoices(salesperson_id, completed_at);
CREATE INDEX idx_invoices_customer ON sale_invoices(customer_id);
CREATE INDEX idx_invoices_offline_uuid ON sale_invoices(offline_uuid);
```

#### `sale_invoice_items`
```sql
CREATE TABLE sale_invoice_items (
    id BIGSERIAL PRIMARY KEY,
    sale_invoice_id BIGINT NOT NULL REFERENCES sale_invoices(id) ON DELETE CASCADE,
    garment_id BIGINT NOT NULL REFERENCES garments(id),
    product_id BIGINT NOT NULL REFERENCES products(id),
    product_size_id BIGINT REFERENCES product_sizes(id),
    standard_price NUMERIC(12,2) NOT NULL,
    applied_discount NUMERIC(12,2) DEFAULT 0,
    final_price NUMERIC(12,2) NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `sale_payments`
```sql
CREATE TABLE sale_payments (
    id BIGSERIAL PRIMARY KEY,
    sale_invoice_id BIGINT NOT NULL REFERENCES sale_invoices(id) ON DELETE CASCADE,
    payment_method VARCHAR(30) NOT NULL, -- cash, bkash, nagad, bank_transfer, card
    amount NUMERIC(12,2) NOT NULL,
    reference_number VARCHAR(100),
    recorded_by BIGINT NOT NULL REFERENCES users(id),
    recorded_at TIMESTAMPTZ DEFAULT NOW()
);
```

---

### 3.8 Online Orders Module

#### `online_orders`
```sql
CREATE TABLE online_orders (
    id BIGSERIAL PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    customer_id BIGINT REFERENCES customers(id),
    online_staff_id BIGINT NOT NULL REFERENCES users(id),
    status VARCHAR(30) NOT NULL CHECK (
        status IN ('pending_confirmation', 'confirmed', 'transfers_pending',
                   'transfers_dispatched', 'transfers_received', 'courier_dispatched',
                   'delivered', 'returned', 'cancelled', 'refund_pending')
    ),
    payment_status VARCHAR(20) NOT NULL CHECK (
        payment_status IN ('unpaid', 'advance_received', 'cod_pending', 'cod_settled',
                          'refunded', 'partially_refunded')
    ),
    payment_method VARCHAR(20) CHECK (payment_method IN ('advance', 'cod')),
    advance_amount NUMERIC(12,2) DEFAULT 0,
    total_amount NUMERIC(12,2) NOT NULL,
    courier_name VARCHAR(100), -- free-text notes
    courier_tracking_number VARCHAR(100), -- free-text notes
    courier_dispatched_at TIMESTAMPTZ,
    delivered_at TIMESTAMPTZ,
    cancellation_reason TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_online_orders_status ON online_orders(status);
CREATE INDEX idx_online_orders_customer ON online_orders(customer_id);
```

#### `online_order_items`
```sql
CREATE TABLE online_order_items (
    id BIGSERIAL PRIMARY KEY,
    online_order_id BIGINT NOT NULL REFERENCES online_orders(id) ON DELETE CASCADE,
    garment_id BIGINT REFERENCES garments(id),
    product_id BIGINT NOT NULL REFERENCES products(id),
    product_size_id BIGINT REFERENCES product_sizes(id),
    source_branch_id BIGINT NOT NULL REFERENCES branches(id),
    transfer_request_id BIGINT REFERENCES transfer_requests(id),
    final_price NUMERIC(12,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (
        status IN ('pending', 'transfer_requested', 'transfer_dispatched',
                   'received_at_online', 'courier_dispatched', 'delivered',
                   'returned', 'refunded')
    ),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

---

### 3.9 Returns Module

#### `returns`
```sql
CREATE TABLE returns (
    id BIGSERIAL PRIMARY KEY,
    return_number VARCHAR(50) UNIQUE NOT NULL,
    return_type VARCHAR(20) NOT NULL CHECK (return_type IN ('physical_pos', 'online')),
    original_sale_invoice_id BIGINT REFERENCES sale_invoices(id),
    original_online_order_id BIGINT REFERENCES online_orders(id),
    branch_id BIGINT NOT NULL REFERENCES branches(id), -- where return is processed
    processed_by BIGINT NOT NULL REFERENCES users(id),
    customer_id BIGINT REFERENCES customers(id),
    total_refund_amount NUMERIC(12,2) NOT NULL,
    refund_method VARCHAR(30),
    refund_status VARCHAR(20) CHECK (refund_status IN ('pending', 'approved', 'rejected', 'completed')),
    approved_by BIGINT REFERENCES users(id),
    approved_at TIMESTAMPTZ,
    return_reason VARCHAR(255),
    is_defective BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `return_items`
```sql
CREATE TABLE return_items (
    id BIGSERIAL PRIMARY KEY,
    return_id BIGINT NOT NULL REFERENCES returns(id) ON DELETE CASCADE,
    garment_id BIGINT NOT NULL REFERENCES garments(id),
    sale_invoice_item_id BIGINT REFERENCES sale_invoice_items(id),
    online_order_item_id BIGINT REFERENCES online_order_items(id),
    refund_amount NUMERIC(12,2) NOT NULL,
    disposition VARCHAR(30) CHECK (
        disposition IN ('restock', 'damaged', 'return_to_main', 'return_to_supplier', 'clearance')
    ),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

---

### 3.10 Cash & Payment Module

#### `cash_accounts`
```sql
CREATE TABLE cash_accounts (
    id BIGSERIAL PRIMARY KEY,
    account_type VARCHAR(20) NOT NULL CHECK (account_type IN ('salesperson', 'branch', 'online_channel')),
    user_id BIGINT REFERENCES users(id), -- for salesperson accounts
    branch_id BIGINT REFERENCES branches(id), -- for branch/online accounts
    current_balance NUMERIC(12,2) DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(account_type, user_id),
    UNIQUE(account_type, branch_id)
);
```

#### `cash_transactions`
Append-only ledger of all cash movements.

```sql
CREATE TABLE cash_transactions (
    id BIGSERIAL PRIMARY KEY,
    cash_account_id BIGINT NOT NULL REFERENCES cash_accounts(id),
    transaction_type VARCHAR(30) NOT NULL CHECK (
        transaction_type IN ('sale_collection', 'refund_disbursement', 'handover_out',
                           'handover_in', 'expense', 'bank_deposit', 'adjustment',
                           'opening_balance', 'courier_settlement', 'supplier_payment',
                           'advance_receipt')
    ),
    amount NUMERIC(12,2) NOT NULL, -- positive = credit, negative = debit
    balance_after NUMERIC(12,2) NOT NULL,
    reference_type VARCHAR(30),
    reference_id BIGINT,
    performed_by BIGINT NOT NULL REFERENCES users(id),
    performed_at TIMESTAMPTZ DEFAULT NOW(),
    notes TEXT
);
CREATE INDEX idx_cash_tx_account ON cash_transactions(cash_account_id, performed_at DESC);
```

#### `cash_handovers`
```sql
CREATE TABLE cash_handovers (
    id BIGSERIAL PRIMARY KEY,
    from_user_id BIGINT NOT NULL REFERENCES users(id),
    to_user_id BIGINT NOT NULL REFERENCES users(id), -- supervisor
    branch_id BIGINT NOT NULL REFERENCES branches(id),
    salesperson_entered_amount NUMERIC(12,2) NOT NULL,
    supervisor_entered_amount NUMERIC(12,2) NOT NULL,
    status VARCHAR(20) CHECK (status IN ('pending', 'matched', 'mismatched', 'resolved')),
    discrepancy_amount NUMERIC(12,2) GENERATED ALWAYS AS (salesperson_entered_amount - supervisor_entered_amount) STORED,
    salesperson_confirmed_at TIMESTAMPTZ,
    supervisor_confirmed_at TIMESTAMPTZ,
    resolution_notes TEXT,
    resolved_by BIGINT REFERENCES users(id),
    resolved_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `cash_closings`
```sql
CREATE TABLE cash_closings (
    id BIGSERIAL PRIMARY KEY,
    branch_id BIGINT NOT NULL REFERENCES branches(id),
    closing_date DATE NOT NULL,
    opening_cash NUMERIC(12,2) NOT NULL,
    expected_closing_cash NUMERIC(12,2) NOT NULL,
    counted_cash NUMERIC(12,2) NOT NULL,
    discrepancy NUMERIC(12,2) GENERATED ALWAYS AS (counted_cash - expected_closing_cash) STORED,
    submitted_by BIGINT NOT NULL REFERENCES users(id),
    submitted_at TIMESTAMPTZ DEFAULT NOW(),
    investigated_by BIGINT REFERENCES users(id),
    resolution_notes TEXT,
    resolved_at TIMESTAMPTZ,
    status VARCHAR(20) DEFAULT 'submitted' CHECK (status IN ('submitted', 'investigating', 'resolved')),
    UNIQUE(branch_id, closing_date)
);
```

#### `payment_methods`
```sql
CREATE TABLE payment_methods (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL, -- cash, bkash, nagad, bank_transfer, card
    display_name VARCHAR(100) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    requires_reference BOOLEAN DEFAULT FALSE,
    is_cash_method BOOLEAN DEFAULT FALSE, -- for cash closing calculation
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

---

### 3.11 Courier Reconciliation Module

#### `courier_settlements`
```sql
CREATE TABLE courier_settlements (
    id BIGSERIAL PRIMARY KEY,
    settlement_number VARCHAR(50) UNIQUE NOT NULL,
    online_channel_account_id BIGINT NOT NULL REFERENCES cash_accounts(id),
    settlement_date DATE NOT NULL,
    total_cod_collected NUMERIC(12,2) NOT NULL,
    total_courier_charges NUMERIC(12,2) DEFAULT 0,
    total_cod_fees NUMERIC(12,2) DEFAULT 0,
    total_deductions NUMERIC(12,2) DEFAULT 0,
    net_amount_received NUMERIC(12,2) NOT NULL,
    reconciled_by BIGINT NOT NULL REFERENCES users(id), -- online supervisor
    reconciled_at TIMESTAMPTZ DEFAULT NOW(),
    status VARCHAR(20) DEFAULT 'reconciled' CHECK (status IN ('reconciled', 'disputed', 'adjusted')),
    notes TEXT
);
```

#### `courier_settlement_lines`
```sql
CREATE TABLE courier_settlement_lines (
    id BIGSERIAL PRIMARY KEY,
    courier_settlement_id BIGINT NOT NULL REFERENCES courier_settlements(id) ON DELETE CASCADE,
    online_order_id BIGINT REFERENCES online_orders(id),
    courier_tracking_ref VARCHAR(100),
    cod_collected NUMERIC(12,2),
    courier_charge NUMERIC(12,2),
    cod_fee NUMERIC(12,2),
    net_amount NUMERIC(12,2),
    match_status VARCHAR(20) CHECK (match_status IN ('matched', 'unmatched', 'short', 'excess', 'duplicate'))
);
```

---

### 3.12 Stock Count Module

#### `stock_counts`
```sql
CREATE TABLE stock_counts (
    id BIGSERIAL PRIMARY KEY,
    count_number VARCHAR(50) UNIQUE NOT NULL,
    branch_id BIGINT NOT NULL REFERENCES branches(id),
    scope_type VARCHAR(20) CHECK (scope_type IN ('full', 'selective')),
    scope_filter JSONB, -- {products: [], sizes: [], garment_ids: []}
    freeze_type VARCHAR(20) CHECK (freeze_type IN ('none', 'full_branch', 'selective')),
    freeze_started_at TIMESTAMPTZ,
    freeze_released_at TIMESTAMPTZ,
    expected_count INT,
    physical_count INT,
    discrepancy_count INT,
    status VARCHAR(20) CHECK (status IN ('planned', 'in_progress', 'counted', 'submitted', 'approved', 'rejected')),
    initiated_by BIGINT REFERENCES users(id),
    counted_by BIGINT REFERENCES users(id),
    verified_by BIGINT REFERENCES users(id),
    approved_by BIGINT REFERENCES users(id),
    started_at TIMESTAMPTZ,
    completed_at TIMESTAMPTZ,
    submitted_at TIMESTAMPTZ,
    approved_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `stock_count_lines`
```sql
CREATE TABLE stock_count_lines (
    id BIGSERIAL PRIMARY KEY,
    stock_count_id BIGINT NOT NULL REFERENCES stock_counts(id) ON DELETE CASCADE,
    garment_id BIGINT REFERENCES garments(id),
    expected_status VARCHAR(30),
    expected_branch_id BIGINT REFERENCES branches(id),
    physical_status VARCHAR(30),
    physical_location_id BIGINT REFERENCES stock_locations(id),
    was_found BOOLEAN DEFAULT FALSE,
    discrepancy_type VARCHAR(20) CHECK (discrepancy_type IN ('none', 'missing', 'unexpected', 'wrong_location', 'wrong_status')),
    reason VARCHAR(255),
    evidence_path TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `stock_adjustments`
```sql
CREATE TABLE stock_adjustments (
    id BIGSERIAL PRIMARY KEY,
    stock_count_id BIGINT REFERENCES stock_counts(id),
    garment_id BIGINT NOT NULL REFERENCES garments(id),
    adjustment_type VARCHAR(30) CHECK (adjustment_type IN ('status_change', 'location_change', 'write_off', 'restore')),
    from_status VARCHAR(30),
    to_status VARCHAR(30),
    from_branch_id BIGINT REFERENCES branches(id),
    to_branch_id BIGINT REFERENCES branches(id),
    reason VARCHAR(255) NOT NULL,
    submitted_by BIGINT REFERENCES users(id),
    approved_by BIGINT NOT NULL REFERENCES users(id),
    approved_at TIMESTAMPTZ DEFAULT NOW(),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

---

### 3.13 Damaged Stock Module

#### `damage_reports`
```sql
CREATE TABLE damage_reports (
    id BIGSERIAL PRIMARY KEY,
    report_number VARCHAR(50) UNIQUE NOT NULL,
    garment_id BIGINT NOT NULL REFERENCES garments(id),
    branch_id BIGINT NOT NULL REFERENCES branches(id),
    reported_by BIGINT NOT NULL REFERENCES users(id),
    reported_at TIMESTAMPTZ DEFAULT NOW(),
    damage_description TEXT NOT NULL,
    evidence_path TEXT,
    verified_by BIGINT REFERENCES users(id), -- supervisor
    verified_at TIMESTAMPTZ,
    disposition VARCHAR(30) CHECK (
        disposition IN ('pending', 'send_to_main', 'repair', 'return_to_supplier',
                       'clearance', 'keep_damaged', 'dispose', 'rejected_by_supplier')
    ),
    disposition_by BIGINT REFERENCES users(id), -- admin
    disposition_at TIMESTAMPTZ,
    disposition_notes TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'verified', 'inspected', 'disposed'))
);
```

---

### 3.14 Expenses Module

#### `expenses`
```sql
CREATE TABLE expenses (
    id BIGSERIAL PRIMARY KEY,
    expense_number VARCHAR(50) UNIQUE NOT NULL,
    branch_id BIGINT NOT NULL REFERENCES branches(id),
    category VARCHAR(100) NOT NULL,
    amount NUMERIC(12,2) NOT NULL,
    expense_date DATE NOT NULL,
    description TEXT,
    evidence_path TEXT,
    submitted_by BIGINT NOT NULL REFERENCES users(id),
    submitted_at TIMESTAMPTZ DEFAULT NOW(),
    approved_by BIGINT REFERENCES users(id),
    approved_at TIMESTAMPTZ,
    rejected_by BIGINT REFERENCES users(id),
    rejected_at TIMESTAMPTZ,
    rejection_reason TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_expenses_branch_date ON expenses(branch_id, expense_date);
```

---

### 3.15 Supplier Payments Module

#### `supplier_payments`
```sql
CREATE TABLE supplier_payments (
    id BIGSERIAL PRIMARY KEY,
    payment_number VARCHAR(50) UNIQUE NOT NULL,
    supplier_id BIGINT NOT NULL REFERENCES suppliers(id),
    purchase_id BIGINT REFERENCES purchases(id),
    amount NUMERIC(12,2) NOT NULL,
    payment_method VARCHAR(30) NOT NULL,
    reference_number VARCHAR(100),
    payment_date DATE NOT NULL,
    recorded_by BIGINT NOT NULL REFERENCES users(id),
    confirmed_by BIGINT REFERENCES users(id),
    confirmed_at TIMESTAMPTZ,
    status VARCHAR(20) DEFAULT 'draft' CHECK (status IN ('draft', 'confirmed', 'reversed')),
    notes TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

---

### 3.16 Customers Module

#### `customers`
```sql
CREATE TABLE customers (
    id BIGSERIAL PRIMARY KEY,
    phone VARCHAR(50) UNIQUE NOT NULL, -- primary identifier, single phone
    name VARCHAR(255),
    address TEXT,
    is_online_customer BOOLEAN DEFAULT FALSE,
    merged_into_id BIGINT REFERENCES customers(id), -- for merge tracking
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_customers_phone ON customers(phone);
```

---

### 3.17 Audit Module

#### `audit_logs`
Append-only. Never UPDATE or DELETE.

```sql
CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    branch_id BIGINT REFERENCES branches(id),
    action VARCHAR(100) NOT NULL, -- e.g., 'sale.complete', 'transfer.approve', 'price.update'
    category VARCHAR(50) NOT NULL, -- 'inventory', 'sales', 'finance', 'security', 'admin'
    reference_type VARCHAR(50),
    reference_id BIGINT,
    previous_values JSONB,
    new_values JSONB,
    reason VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT,
    performed_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_audit_user ON audit_logs(user_id, performed_at DESC);
CREATE INDEX idx_audit_branch_date ON audit_logs(branch_id, performed_at DESC);
CREATE INDEX idx_audit_reference ON audit_logs(reference_type, reference_id);
CREATE INDEX idx_audit_action ON audit_logs(action, performed_at);
```

**Partitioning:** Partition by `performed_at` (monthly) for long-term scalability.

---

### 3.18 Notifications Module

#### `notifications`
```sql
CREATE TABLE notifications (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    type VARCHAR(50) NOT NULL,
    title VARCHAR(255) NOT NULL,
    body TEXT,
    data JSONB,
    read_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW()
);
CREATE INDEX idx_notifications_user_unread ON notifications(user_id, read_at);
```

#### `scheduled_reports`
```sql
CREATE TABLE scheduled_reports (
    id BIGSERIAL PRIMARY KEY,
    report_type VARCHAR(50) NOT NULL, -- daily_cash, monthly_ledger, etc.
    recipient_email VARCHAR(255) NOT NULL,
    schedule_cron VARCHAR(100) NOT NULL, -- e.g., '0 8 * * *' for daily 8am
    branch_id BIGINT REFERENCES branches(id), -- NULL = all branches
    is_active BOOLEAN DEFAULT TRUE,
    last_run_at TIMESTAMPTZ,
    next_run_at TIMESTAMPTZ,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

---

### 3.19 System Settings Module

#### `system_settings`
```sql
CREATE TABLE system_settings (
    id BIGSERIAL PRIMARY KEY,
    key VARCHAR(100) UNIQUE NOT NULL,
    value JSONB NOT NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    updated_by BIGINT REFERENCES users(id),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `return_policies`
```sql
CREATE TABLE return_policies (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    product_category VARCHAR(100),
    return_period_days INT NOT NULL,
    defect_claim_period_days INT NOT NULL,
    requires_approval_limit NUMERIC(12,2),
    is_active BOOLEAN DEFAULT TRUE,
    effective_from TIMESTAMPTZ DEFAULT NOW(),
    effective_to TIMESTAMPTZ,
    created_by BIGINT REFERENCES users(id),
    created_at TIMESTAMPTZ DEFAULT NOW()
);
```

#### `reservation_counts`
A view or materialized view showing current reservation counts per branch.

```sql
CREATE VIEW v_reservation_counts AS
SELECT
    b.id AS branch_id,
    b.name AS branch_name,
    COUNT(g.id) AS reserved_count
FROM branches b
LEFT JOIN garment_current_stock gcs ON gcs.current_branch_id = b.id AND gcs.current_status = 'reserved'
LEFT JOIN garments g ON g.id = gcs.garment_id
GROUP BY b.id, b.name;
```

---

## 4. Indexing Strategy

### 4.1 Primary indexes (already defined in schema)

All primary keys, foreign keys, and unique constraints have indexes.

### 4.2 Performance-critical indexes

| Table | Index | Purpose |
|---|---|---|
| garments | barcode | POS barcode lookup |
| garments | product_id, product_size_id | Stock availability queries |
| garments | current_status, current_branch_id | Available stock per branch |
| garment_current_stock | current_status, current_branch_id | Fast stock queries |
| inventory_movements | garment_id, performed_at DESC | Garment movement history |
| sale_invoices | branch_id, completed_at | Daily sales reports |
| sale_invoices | salesperson_id, completed_at | Salesperson performance |
| audit_logs | branch_id, performed_at | Supervisor today-only audit |
| audit_logs | action, performed_at | Alert pattern detection |
| cash_transactions | cash_account_id, performed_at | Cash account ledger |

### 4.3 Composite indexes for common queries

```sql
-- Stock availability by product at a branch
CREATE INDEX idx_garments_branch_product_status
    ON garments(current_branch_id, product_id, current_status)
    WHERE current_status = 'available';

-- Today's sales for a salesperson
CREATE INDEX idx_invoices_today
    ON sale_invoices(salesperson_id, completed_at)
    WHERE status = 'completed';
```

### 4.4 Partial indexes

For frequently-filtered subsets:
```sql
CREATE INDEX idx_garments_available ON garments(product_id, product_size_id, current_branch_id)
    WHERE current_status = 'available';
```

---

## 5. Partitioning Strategy

### 5.1 Tables to partition

| Table | Partition key | Strategy |
|---|---|---|
| inventory_movements | performed_at | Monthly range partitions |
| audit_logs | performed_at | Monthly range partitions |
| cash_transactions | performed_at | Monthly range partitions (when volume grows) |

### 5.2 Partitioning implementation

```sql
-- Example: inventory_movements partitioned by month
CREATE TABLE inventory_movements (
    id BIGSERIAL,
    garment_id BIGINT NOT NULL,
    movement_type VARCHAR(30) NOT NULL,
    -- ... other columns ...
    performed_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
) PARTITION BY RANGE (performed_at);

-- Monthly partitions
CREATE TABLE inventory_movements_2026_07 PARTITION OF inventory_movements
    FOR VALUES FROM ('2026-07-01') TO ('2026-08-01');

CREATE TABLE inventory_movements_2026_08 PARTITION OF inventory_movements
    FOR VALUES FROM ('2026-08-01') TO ('2026-09-01');
-- ... etc
```

### 5.3 Partition management

A scheduled job creates new partitions ahead of time (e.g., creates next month's partition on the 25th of each month).

---

## 6. Concurrency Control

### 6.1 Row-level locking

Critical operations use `SELECT ... FOR UPDATE`:

```sql
-- During POS checkout
BEGIN;
SELECT * FROM garments WHERE id = $1 FOR UPDATE;
-- Check status = 'available'
-- Reserve garment, create invoice, create payments
-- Update garment status to 'sold'
COMMIT;
```

### 6.2 Advisory locks

For serial number generation (prevent duplicate serials under concurrent receipt):

```php
// In Purchasing service
DB::statement('SELECT pg_advisory_xact_lock(?)', [$lockKey]);
// Generate next serial
// Create garment record
// Commit (releases lock)
```

### 6.3 Optimistic concurrency (offline sync)

Offline sales use UUID for dedup:
```sql
INSERT INTO sale_invoices (..., offline_uuid, ...)
VALUES (..., $uuid, ...)
ON CONFLICT (offline_uuid) DO NOTHING;
```

---

## 7. Data Integrity Constraints

### 7.1 Check constraints

Already defined in schema:
- Status fields limited to allowed values.
- Monetary amounts non-negative where applicable.
- Account type/user/branch uniqueness.

### 7.2 Foreign key constraints

All foreign keys use `REFERENCES` with appropriate `ON DELETE` behavior:
- `ON DELETE CASCADE` for child records that should be removed (e.g., sale_invoice_items when invoice is deleted — though invoices are never deleted).
- `ON DELETE RESTRICT` (default) for references that must not be orphaned (e.g., garments reference products — products cannot be deleted if garments exist).

### 7.3 Generated columns

- `cash_handovers.discrepancy_amount` = `salesperson_entered_amount - supervisor_entered_amount`.
- `cash_closings.discrepancy` = `counted_cash - expected_closing_cash`.

### 7.4 Triggers (if needed)

For the current-stock projection, prefer transactional application-level updates over triggers. Use triggers only as a safety net:

```sql
-- Optional: trigger to sync garment_current_stock on garment status change
CREATE OR REPLACE FUNCTION sync_garment_current_stock()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO garment_current_stock (garment_id, current_status, current_branch_id, current_location_id, updated_at)
    VALUES (NEW.id, NEW.current_status, NEW.current_branch_id, NEW.current_location_id, NOW())
    ON CONFLICT (garment_id) DO UPDATE
    SET current_status = EXCLUDED.current_status,
        current_branch_id = EXCLUDED.current_branch_id,
        current_location_id = EXCLUDED.current_location_id,
        updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER trg_sync_garment_stock
    AFTER INSERT OR UPDATE ON garments
    FOR EACH ROW
    EXECUTE FUNCTION sync_garment_current_stock();
```

---

## 8. Reconciliation Strategy

### 8.1 Stock projection reconciliation

A scheduled job verifies `garment_current_stock` against the latest movement for each garment:

```sql
-- Find drift between projection and ledger
SELECT g.id, g.current_status AS garment_status, gcs.current_status AS projection_status
FROM garments g
JOIN garment_current_stock gcs ON gcs.garment_id = g.id
JOIN LATERAL (
    SELECT to_status FROM inventory_movements im
    WHERE im.garment_id = g.id
    ORDER BY im.performed_at DESC
    LIMIT 1
) latest ON true
WHERE g.current_status != latest.to_status OR gcs.current_status != latest.to_status;
```

Drift indicates a bug; the job alerts admin.

### 8.2 Cash reconciliation

Daily cash closing reconciles:
- Opening cash (from previous closing).
- + Cash sales collections.
- - Cash refunds.
- - Approved cash expenses.
- - Cash handovers to supervisor.
- = Expected closing cash.
- vs. Counted cash.

### 8.3 Audit reconciliation

Monthly job verifies audit log completeness — every business transaction should have corresponding audit entries.

---

## 9. Migration Strategy

### 9.1 Migration ordering

Migrations must be ordered to respect foreign key dependencies:

1. `branches`, `fiscal_years` (no dependencies)
2. `users` (depends on branches)
3. `roles`, `permissions`, `user_roles`, `role_permissions`, `user_permissions`
4. `products`, `product_sizes`, `product_prices`
5. `suppliers`, `purchases`, `purchase_items`, `goods_receipts`
6. `garments`, `garment_current_stock`, `inventory_movements`
7. `customers`
8. `transfer_requests`, `transfer_request_items`, `transfer_discrepancies`
9. `sale_invoices`, `sale_invoice_items`, `sale_payments`
10. `online_orders`, `online_order_items`
11. `returns`, `return_items`
12. `cash_accounts`, `cash_transactions`, `cash_handovers`, `cash_closings`, `payment_methods`
13. `courier_settlements`, `courier_settlement_lines`
14. `stock_counts`, `stock_count_lines`, `stock_adjustments`
15. `damage_reports`
16. `expenses`
17. `supplier_payments`
18. `audit_logs`
19. `notifications`, `scheduled_reports`
20. `system_settings`, `return_policies`

### 9.2 Seed data

Initial seed:
- Main Branch, B1, B2, B3, Online channel.
- Current fiscal year.
- 5 roles with default permissions.
- Default payment methods (cash, bKash, Nagad, bank transfer, card).
- Default return policy (7 days).
- Main Branch Admin user.

---

## 10. Backup and Recovery

### 10.1 Backup approach

- **Weekly full backup** (client decision) via `pg_dump`.
- **Recommendation:** Daily full backup + continuous WAL archiving for point-in-time recovery.
- **Offsite copy:** Backup file copied to a separate disk or remote server.

### 10.2 Recovery

- Full restore from latest backup.
- WAL replay for point-in-time recovery (if WAL archiving enabled).
- Recovery testing: monthly test restore to verify backup integrity.

### 10.3 Data retention

- Business audit logs: retained for the full multi-year period (no auto-deletion).
- Login attempt logs: 90 days.
- Inventory movements: retained permanently (append-only).
- Soft-deleted records: retained (never hard-deleted unless via admin purge workflow).

---

*End of Database Design Document*
