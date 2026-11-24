# Performance Strategy Document
## Nondon — Multi-Branch Women's Dress Retail & Wholesale ERP

**Project:** Nondon
**Version:** 1.0
**Date:** 24 November 2026
**Status:** Design document — pending approval

---

## 1. Performance Goals

### 1.1 Response time targets

| Operation | Target | Notes |
|---|---|---|
| POS barcode lookup | < 100ms | Critical for fast checkout |
| POS sale completion | < 500ms | Atomic transaction |
| Stock availability query (single branch) | < 200ms | For POS and transfer UI |
| Stock availability query (all branches) | < 500ms | For cross-branch visibility |
| Garment movement history | < 300ms | Full lifecycle trace |
| Daily sales report | < 2s | Per branch, per day |
| Monthly report | < 5s | May use summary tables |
| Dashboard load | < 1s | Initial page render |
| Offline sync (per 100 sales) | < 5s | Push + pull |

### 1.2 Throughput targets

| Scenario | Target |
|---|---|
| Concurrent POS sales (per branch) | 5 simultaneous checkouts |
| Total concurrent users | 10 (initial), 50+ (future) |
| Garment records | 100,000+ (multi-year) |
| Inventory movements | Millions over years |
| Daily sales volume | 500+ per branch |

### 1.3 Future scale (design target)

- 100+ branches.
- 10,000+ products/designs.
- Millions of garment movements and sales lines.
- Multi-year historical records.

---

## 2. Database Performance Strategy

### 2.1 Current-stock projection table

The core performance optimization is the `garment_current_stock` projection table.

**Problem:** The `inventory_movements` ledger grows to millions of rows. Routine stock queries must not scan this ledger.

**Solution:** A denormalized projection table with one row per garment, containing the current status, branch, and location.

```sql
-- Fast stock query using projection
SELECT g.barcode, g.fiscal_year_serial, p.name, ps.size_label, gcs.current_status
FROM garments g
JOIN garment_current_stock gcs ON gcs.garment_id = g.id
JOIN products p ON p.id = g.product_id
LEFT JOIN product_sizes ps ON ps.id = g.product_size_id
WHERE gcs.current_branch_id = ?
  AND gcs.current_status = 'available'
  AND g.product_id = ?;
```

**Maintenance:**
- Updated transactionally within the same DB transaction as the movement.
- Periodic reconciliation job verifies projection against the ledger.
- Drift indicates a bug; admin is alerted.

### 2.2 Summary tables for reporting

Aggregate reports use pre-computed summary tables rather than scanning raw transactions:

#### `daily_sales_summary`
```sql
CREATE TABLE daily_sales_summary (
    id BIGSERIAL PRIMARY KEY,
    branch_id BIGINT NOT NULL REFERENCES branches(id),
    summary_date DATE NOT NULL,
    salesperson_id BIGINT REFERENCES users(id),
    sale_type VARCHAR(20), -- retail, wholesale
    total_invoices INT DEFAULT 0,
    total_garments_sold INT DEFAULT 0,
    total_sales_amount NUMERIC(12,2) DEFAULT 0,
    total_discount NUMERIC(12,2) DEFAULT 0,
    cash_collected NUMERIC(12,2) DEFAULT 0,
    digital_collected NUMERIC(12,2) DEFAULT 0,
    returns_count INT DEFAULT 0,
    returns_amount NUMERIC(12,2) DEFAULT 0,
    generated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(branch_id, summary_date, salesperson_id, sale_type)
);
CREATE INDEX idx_dss_branch_date ON daily_sales_summary(branch_id, summary_date);
```

**Maintenance:** Updated by event listener on `SaleCompleted` and `ReturnProcessed`, or by scheduled job at end of day.

#### `stock_summary`
```sql
CREATE TABLE stock_summary (
    id BIGSERIAL PRIMARY KEY,
    branch_id BIGINT NOT NULL REFERENCES branches(id),
    product_id BIGINT NOT NULL REFERENCES products(id),
    product_size_id BIGINT REFERENCES product_sizes(id),
    available_count INT DEFAULT 0,
    reserved_count INT DEFAULT 0,
    in_transit_count INT DEFAULT 0,
    damaged_count INT DEFAULT 0,
    updated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(branch_id, product_id, product_size_id)
);
```

**Maintenance:** Updated transactionally on garment status change, or by periodic reconciliation job.

#### `cash_summary`
```sql
CREATE TABLE cash_summary (
    id BIGSERIAL PRIMARY KEY,
    branch_id BIGINT NOT NULL REFERENCES branches(id),
    summary_date DATE NOT NULL,
    opening_cash NUMERIC(12,2),
    closing_cash NUMERIC(12,2),
    total_collections NUMERIC(12,2),
    total_refunds NUMERIC(12,2),
    total_expenses NUMERIC(12,2),
    total_handovers NUMERIC(12,2),
    discrepancy NUMERIC(12,2),
    generated_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(branch_id, summary_date)
);
```

### 2.3 Indexing strategy

(Detailed in `03-database-design.md` §4)

Key indexes for performance:

| Index | Purpose |
|---|---|
| `garments(barcode)` | POS barcode lookup |
| `garments(current_status, current_branch_id)` | Available stock per branch |
| `garment_current_stock(current_status, current_branch_id)` | Fast stock queries (projection) |
| `inventory_movements(garment_id, performed_at DESC)` | Garment movement history |
| `sale_invoices(branch_id, completed_at)` | Daily sales reports |
| `sale_invoices(salesperson_id, completed_at)` | Salesperson performance |
| `audit_logs(branch_id, performed_at DESC)` | Supervisor today-only audit |
| `cash_transactions(cash_account_id, performed_at DESC)` | Cash account ledger |

### 2.4 Partial indexes

For frequently-filtered subsets:

```sql
-- Only available garments (the common POS query)
CREATE INDEX idx_garments_available
    ON garments(product_id, product_size_id, current_branch_id)
    WHERE current_status = 'available';

-- Only completed invoices (the common report query)
CREATE INDEX idx_invoices_completed
    ON sale_invoices(branch_id, completed_at)
    WHERE status = 'completed';
```

### 2.5 Partitioning

For large append-only tables:

| Table | Partition strategy |
|---|---|
| `inventory_movements` | Monthly range partitions by `performed_at` |
| `audit_logs` | Monthly range partitions by `performed_at` |
| `cash_transactions` | Monthly range partitions (when volume grows) |

**Implementation:** A scheduled job creates next month's partition before the month begins.

**Query optimization:** Queries with date filters automatically hit only the relevant partitions.

### 2.6 Query optimization patterns

#### Avoid N+1 queries
```php
// Bad: N+1 query
foreach ($saleInvoices as $invoice) {
    echo $invoice->customer->name; // Query per invoice
}

// Good: Eager loading
$saleInvoices = SaleInvoice::with('customer', 'items.garment', 'payments')
    ->where('branch_id', $branchId)
    ->whereDate('completed_at', today())
    ->get();
```

#### Use DB::raw for complex aggregations
```php
// Daily sales summary in one query
$dailySummary = SaleInvoice::selectRaw('
        COUNT(*) as total_invoices,
        SUM(total_amount) as total_sales,
        SUM(total_discount) as total_discount,
        SUM(CASE WHEN EXISTS (
            SELECT 1 FROM sale_payments sp
            WHERE sp.sale_invoice_id = sale_invoices.id
            AND sp.payment_method = ?
        ) THEN 1 ELSE 0 END) as cash_sales_count
    ', ['cash'])
    ->where('branch_id', $branchId)
    ->whereDate('completed_at', $date)
    ->first();
```

#### Chunk large exports
```php
// Export 100k garments without memory overflow
Garment::with('product', 'productSize')
    ->where('fiscal_year_id', $fiscalYearId)
    ->chunk(1000, function ($garments) use ($writer) {
        foreach ($garments as $garment) {
            $writer->writeRow($garment->toArray());
        }
    });
```

---

## 3. Caching Strategy

### 3.1 Cache layers

| Layer | Technology | Purpose | TTL |
|---|---|---|---|
| Application cache | Laravel Cache (file/database initially, Redis later) | Configuration, lookups | Minutes to hours |
| Database cache | PostgreSQL query cache | Repeated queries | Automatic |
| Browser cache | HTTP cache headers | Static assets | Days |
| Offline cache | IndexedDB / LocalStorage | POS offline mode | Until sync |

### 3.2 Cached data

| Data | Cache key | TTL | Invalidation |
|---|---|---|---|
| Active branches list | `branches:active` | 1 hour | On branch create/update |
| Payment methods | `payment_methods:active` | 1 hour | On method create/update |
| Current fiscal year | `fiscal_year:current` | 1 hour | On fiscal year change |
| Product catalog (for POS) | `catalog:branch:{id}` | 15 minutes | On product/price change |
| Return policies | `return_policies:active` | 1 hour | On policy change |
| System settings | `settings:{key}` | 1 hour | On setting change |
| User permissions | `permissions:user:{id}` | Session | On role/permission change |

### 3.3 Cache invalidation

- **Event-driven:** When data changes, the service dispatches an event; a listener clears the relevant cache key.
- **Tag-based:** (With Redis) Cache entries tagged by domain; clearing a tag clears all entries in that domain.
- **TTL fallback:** All cache entries have a TTL as a safety net.

### 3.4 When NOT to cache

- Real-time stock availability (always query the projection table).
- Cash account balances (always query the latest transaction).
- Audit logs (never cache — always direct query).
- Any data visible to a salesman that could expose sensitive info if stale.

---

## 4. Offline POS Performance

### 4.1 Cache size management

| Data | Estimated size per branch |
|---|---|
| Product catalog (10k products) | ~2 MB |
| Prices | ~0.5 MB |
| Stock snapshot (20k garments) | ~3 MB |
| Customer phone index (5k customers) | ~0.5 MB |
| Offline sale queue (per sale) | ~2 KB |
| **Total cache** | ~6 MB per branch |

IndexedDB handles this comfortably (browsers allow 50MB+ for web apps).

### 4.2 Cache refresh strategy

- **On login:** Full cache refresh (catalog, prices, stock, customers).
- **On sync:** Push offline sales first, then pull fresh data.
- **Periodic refresh (online):** Every 15 minutes, refresh stock snapshot (prices and catalog change less frequently).
- **Manual refresh:** "Sync Now" button in UI.

### 4.3 Search performance in offline mode

- Product search: IndexedDB index on product name and code.
- Barcode lookup: IndexedDB index on barcode.
- Customer search: IndexedDB index on phone number.
- Stock lookup: IndexedDB index on garment barcode.

All searches return in < 50ms from local cache.

---

## 5. Background Job Performance

### 5.1 Job queue

- Database driver initially (no Redis dependency).
- Switch to Redis when queue volume grows or when multiple workers are needed.
- Jobs are processed sequentially initially; multiple workers can be added.

### 5.2 Job priorities

| Priority | Jobs |
|---|---|
| High | Offline sync processing, conflict resolution |
| Medium | Email report sending, summary table updates |
| Low | Alert detection, projection reconciliation, backup |

### 5.3 Long-running job handling

- Reports that scan large date ranges: chunked processing, queued.
- Exports: queued, user notified when complete.
- Stock projection reconciliation: runs hourly, processes in chunks.

### 5.4 Scheduled jobs

| Schedule | Job | Estimated duration |
|---|---|---|
| Every minute | Process queue | < 1s |
| Hourly | Reconcile stock projection | < 30s |
| Daily (configurable) | Send daily cash report | < 10s per branch |
| Daily | Generate daily sales summary | < 1 minute |
| Daily | Detect inventory alerts | < 30s |
| Weekly | Weekly backup | < 30 minutes |
| Monthly | Send monthly ledger report | < 5 minutes |
| Monthly | Create next month's partition | < 1s |

---

## 6. Frontend Performance

### 6.1 Livewire optimization

- Use lazy loading for components not immediately visible.
- Paginate large lists (sales history, stock lists).
- Use `wire:loading` indicators for async operations.
- Minimize payload: only send necessary data in Livewire updates.

### 6.2 Asset optimization

- Tailwind CSS: purge unused styles (production build).
- JavaScript: minify and combine (Laravel Mix/Vite).
- Images: optimized and served with cache headers.
- Fonts: use system fonts where possible; web fonts with `font-display: swap`.

### 6.3 Pagination

All list views use pagination:
- Sales history: 25 per page.
- Stock list: 50 per page.
- Customer list: 25 per page.
- Audit log: 50 per page.
- Transfer list: 25 per page.

### 6.4 Deferred loading

- Dashboard widgets load data asynchronously.
- Reports render progressively (summary first, details later).
- Large tables use virtual scrolling (if needed in future).

---

## 7. Monitoring and Alerting

### 7.1 Performance metrics

| Metric | Target | Alert threshold |
|---|---|---|
| POS sale completion time | < 500ms | > 2s |
| Barcode lookup time | < 100ms | > 500ms |
| Sync queue depth | < 10 | > 50 |
| Sync conflict count | 0 | > 5 per day |
| Projection drift count | 0 | > 0 |
| Job failure rate | < 1% | > 5% |
| Database query time (p95) | < 100ms | > 500ms |
| Disk usage | < 70% | > 85% |

### 7.2 Monitoring tools

- **Laravel Telescope** (development): query logs, job tracking, exception tracking.
- **Application logs** (production): structured logs for monitoring.
- **Health check endpoint** (`/api/health`): returns system status.
- **Manual review:** admin reviews sync conflicts, projection drift, and alert notifications daily.

### 7.3 Alert delivery

- In-app notifications for admin.
- Email alerts for critical issues (projection drift, sync conflicts, backup failures).
- Dashboard indicators for queue depth and job failures.

---

## 8. Capacity Planning

### 8.1 Initial capacity (3-5 branches)

| Resource | Estimate |
|---|---|
| Users | 10-15 |
| Products | 500-2,000 |
| Garments per year | 3,000-10,000 |
| Daily sales | 100-500 per branch |
| Daily movements | 500-2,000 |
| Database size (year 1) | ~500 MB - 1 GB |
| Database size (year 3) | ~2-5 GB |

### 8.2 Future capacity (100+ branches)

| Resource | Estimate |
|---|---|
| Users | 500+ |
| Products | 10,000+ |
| Garments (cumulative) | 500,000+ |
| Daily sales | 10,000+ |
| Daily movements | 50,000+ |
| Database size | 50+ GB |

### 8.3 Scaling actions

| Trigger | Action |
|---|---|
| Database > 5 GB | Add read replica for reporting |
| Queue depth consistently high | Add Redis, multiple workers |
| Concurrent users > 50 | Add load balancer, multiple app servers |
| Branches > 20 | Consider CDN for static assets |
| Reports slow | Move reporting to read replica |

None of these require code changes — only infrastructure configuration.

---

## 9. Optimization Checklist

- [ ] `garment_current_stock` projection table implemented and maintained.
- [ ] Summary tables (`daily_sales_summary`, `stock_summary`, `cash_summary`) implemented.
- [ ] All critical indexes created (per `03-database-design.md` §4).
- [ ] Partial indexes on `available` garments and `completed` invoices.
- [ ] Partitioning on `inventory_movements` and `audit_logs` (monthly).
- [ ] Eager loading on all list queries (no N+1).
- [ ] Chunked processing for exports and large reports.
- [ ] Cache layer configured with appropriate TTLs.
- [ ] Cache invalidation on data changes.
- [ ] Offline POS cache optimized (IndexedDB indexes).
- [ ] Background jobs for reports and reconciliation.
- [ ] Pagination on all list views.
- [ ] Asset optimization (Tailwind purge, JS minify).
- [ ] Health check endpoint.
- [ ] Performance metrics monitoring.

---

*End of Performance Strategy Document*
