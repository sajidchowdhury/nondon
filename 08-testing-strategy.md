# Testing Strategy Document
## Nondon — Multi-Branch Women's Dress Retail & Wholesale ERP

**Project:** Nondon
**Version:** 1.0
**Date:** 24 November 2026
**Status:** Design document — pending approval

---

## 1. Testing Overview

### 1.1 Testing principles

- **Test business-critical workflows first.** Inventory integrity, payment accuracy, and concurrency safety are non-negotiable.
- **Automated tests for every module.** No module ships without tests.
- **Test at multiple levels.** Unit, integration, and end-to-end.
- **Test the hard cases.** Concurrency, offline sync, conflicts, edge cases.
- **Tests are documentation.** Test names describe the business rule being verified.

### 1.2 Testing framework

- **Pest PHP** — modern PHP testing framework, expressive syntax.
- **Laravel testing helpers** — `RefreshDatabase`, `actingAs`, factory helpers.
- **Database:** Separate test database (SQLite in-memory for unit tests, PostgreSQL for integration tests).

---

## 2. Test Pyramid

```
        ┌───────────┐
        │  E2E /    │  10% — Critical user journeys
        │  Acceptance│  (Dusk browser tests for POS, transfers, returns)
        ├───────────┤
        │ Integration│  30% — Service + database, multi-module flows
        │            │  (Complete sale, transfer lifecycle, return processing)
        ├───────────┤
        │   Unit     │  60% — Service methods, model scopes, policies
        │            │  (Price validation, serial generation, permission checks)
        └───────────┘
```

---

## 3. Unit Tests

### 3.1 Service unit tests

Each service method has tests covering:
- Happy path (expected input → expected output).
- Edge cases (boundary values, empty input).
- Error cases (invalid input, business rule violations).
- Permission checks (unauthorized user → exception).

**Example: Serial number generation**
```php
test('generates next fiscal year serial correctly', function () {
    $fiscalYear = FiscalYear::factory()->create([
        'serial_prefix' => '25',
        'current_serial_sequence' => 1208,
    ]);

    $serial = app(PurchasingService::class)->generateNextSerial($fiscalYear);

    expect($serial)->toBe('25-01209');
});

test('serial generation is concurrency-safe', function () {
    $fiscalYear = FiscalYear::factory()->create(['serial_prefix' => '25']);

    // Simulate concurrent calls
    $results = collect();
    collect(range(1, 10))->each(function () use ($fiscalYear, &$results) {
        $results->push(app(PurchasingService::class)->generateNextSerial($fiscalYear));
    });

    expect($results->unique()->count())->toBe(10); // No duplicates
});
```

**Example: Minimum price enforcement**
```php
test('cannot sell below minimum price', function () {
    $product = Product::factory()->create();
    $price = ProductPrice::factory()->create([
        'product_id' => $product->id,
        'standard_price' => 1500,
        'minimum_price' => 1000,
    ]);
    $garment = Garment::factory()->create(['product_id' => $product->id]);

    expect(fn() => app(SalesService::class)->addToCart($garment, $user, discount: 600))
        ->toThrow(BelowMinimumPriceException::class);
});
```

### 3.2 Policy unit tests

Every policy method tested for each role:

```php
test('salesperson can only view own today sales', function () {
    $salesperson = User::factory()->salesperson()->create();
    $ownSaleToday = SaleInvoice::factory()->create([
        'salesperson_id' => $salesperson->id,
        'completed_at' => now(),
    ]);
    $ownSaleYesterday = SaleInvoice::factory()->create([
        'salesperson_id' => $salesperson->id,
        'completed_at' => now()->subDay(),
    ]);
    $otherSale = SaleInvoice::factory()->create();

    expect($salesperson->can('view', $ownSaleToday))->toBeTrue();
    expect($salesperson->can('view', $ownSaleYesterday))->toBeFalse();
    expect($salesperson->can('view', $otherSale))->toBeFalse();
});
```

### 3.3 Model scope tests

```php
test('branch scope filters to user branch', function () {
    $branchA = Branch::factory()->create();
    $branchB = Branch::factory()->create();
    $user = User::factory()->forBranch($branchA)->create();

    SaleInvoice::factory()->count(3)->forBranch($branchA)->create();
    SaleInvoice::factory()->count(2)->forBranch($branchB)->create();

    Auth::login($user);
    $sales = SaleInvoice::query()->forBranch()->get();

    expect($sales)->toHaveCount(3);
});
```

---

## 4. Integration Tests

### 4.1 Complete workflow tests

**POS sale completion (atomic):**
```php
test('pos sale completes atomically', function () {
    $user = User::factory()->salesperson()->create();
    $customer = Customer::factory()->create();
    $garment = Garment::factory()->available()->create();

    $invoice = app(SalesService::class)->completeSale(
        user: $user,
        customerPhone: $customer->phone,
        items: [['garment_id' => $garment->id, 'discount' => 0]],
        payments: [['method' => 'cash', 'amount' => $garment->standardPrice]],
    );

    expect($invoice)->toBeInstanceOf(SaleInvoice::class);
    expect($invoice->status)->toBe('completed');
    expect($garment->fresh()->current_status)->toBe('sold');

    // Movement recorded
    expect(InventoryMovement::where('garment_id', $garment->id)->count())->toBe(1);

    // Cash transaction recorded
    expect(CashTransaction::where('reference_id', $invoice->id)->exists())->toBeTrue();
});
```

**Transfer lifecycle:**
```php
test('transfer lifecycle reserves dispatches and receives', function () {
    [$mainBranch, $b1] = Branch::factory()->count(2)->create();
    $garment = Garment::factory()->available()->atBranch($mainBranch)->create();

    // Create transfer request
    $transfer = app(TransferService::class)->createRequest(
        from: $mainBranch,
        to: $b1,
        garments: [$garment],
        requestedBy: $supervisor,
    );

    // Garment should be reserved
    expect($garment->fresh()->current_status)->toBe('reserved');

    // Approve
    app(TransferService::class)->approve($transfer, $approver);

    // Dispatch
    app(TransferService::class)->dispatch($transfer, $dispatcher);

    // Garment in transit
    expect($garment->fresh()->current_status)->toBe('in_transit');

    // Receive
    app(TransferService::class)->receive($transfer, $receiver, receivedGarments: [$garment]);

    // Garment available at B1
    expect($garment->fresh()->current_status)->toBe('available');
    expect($garment->fresh()->current_branch_id)->toBe($b1->id);
});
```

### 4.2 Return processing tests

```php
test('return links to original sale and restores stock', function () {
    [$sale, $garment] = createCompletedSale();

    $return = app(ReturnsService::class)->processPhysicalReturn(
        originalSale: $sale,
        garmentIds: [$garment->id],
        reason: 'size issue',
        refundMethod: 'cash',
        processedBy: $supervisor,
    );

    expect($return->original_sale_invoice_id)->toBe($sale->id);
    expect($garment->fresh()->current_status)->toBe('available');
    expect($sale->fresh()->returns)->toHaveCount(1);
});

test('cannot return same garment twice', function () {
    [$sale, $garment] = createCompletedSale();
    $return1 = processReturn($sale, $garment);

    expect(fn() => processReturn($sale, $garment))
        ->toThrow(DuplicateReturnException::class);
});

test('cross-branch return is rejected', function () {
    [$sale, $garment] = createCompletedSale(branch: $branchA);
    $userAtBranchB = User::factory()->forBranch($branchB)->salesperson()->create();

    expect(fn() => app(ReturnsService::class)->processPhysicalReturn(
        originalSale: $sale,
        garmentIds: [$garment->id],
        reason: 'test',
        refundMethod: 'cash',
        processedBy: $userAtBranchB,
    ))->toThrow(CrossBranchReturnException::class);
});
```

### 4.3 Online order multi-branch test

```php
test('online order with multi-branch sourcing creates separate transfers', function () {
    $order = createOnlineOrderWithItems([
        ['product_id' => 1, 'source_branch_id' => $mainBranch],
        ['product_id' => 2, 'source_branch_id' => $b1],
    ]);

    app(OnlineOrderService::class)->confirm($order, $onlineStaff);

    expect($order->transfers)->toHaveCount(2);
    expect($order->transfers->pluck('from_branch_id'))
        ->toContain($mainBranch->id, $b1->id);
});
```

---

## 5. Concurrency Tests

### 5.1 Concurrent sale of same garment

```php
test('two concurrent sales of same garment one succeeds', function () {
    $garment = Garment::factory()->available()->create();
    $user1 = User::factory()->salesperson()->create();
    $user2 = User::factory()->salesperson()->create();

    $results = collect();

    // Simulate concurrent requests
    // Use database transactions with row-level locking
    $thread1 = fn() => $results['sale1'] = tryCompleteSale($user1, $garment);
    $thread2 = fn() => $results['sale2'] = tryCompleteSale($user2, $garment);

    // Run concurrently (using async or process fork)
    // One should succeed, one should fail with garment_not_available

    $successCount = $results->filter(fn($r) => $r['success'] === true)->count();
    expect($successCount)->toBe(1);
});
```

### 5.2 Concurrent serial generation

```php
test('concurrent serial generation produces unique serials', function () {
    $fiscalYear = FiscalYear::factory()->create();

    // Generate 100 serials concurrently
    $serials = collect(range(1, 100))->map(function () use ($fiscalYear) {
        return app(PurchasingService::class)->generateNextSerial($fiscalYear);
    });

    expect($serials->unique()->count())->toBe(100);
});
```

---

## 6. Offline Sync Tests

### 6.1 Idempotent push

```php
test('offline sale push is idempotent', function () {
    $offlineSale = createOfflineSaleData($garment);

    // Push once
    $response1 = app(SyncService::class)->push([$offlineSale]);
    expect($response1[0]['status'])->toBe('committed');

    // Push same UUID again
    $response2 = app(SyncService::class)->push([$offlineSale]);
    expect($response2[0]['status'])->toBe('already_synced');

    // Only one invoice exists
    expect(SaleInvoice::where('offline_uuid', $offlineSale['offline_uuid'])->count())->toBe(1);
});
```

### 6.2 Conflict detection

```php
test('offline sale of garment already sold online is flagged as conflict', function () {
    $garment = Garment::factory()->available()->create();

    // Online sale completes first
    $onlineInvoice = completeSale($garment);

    // Offline sale (created before online sale, synced after)
    $offlineSale = createOfflineSaleData($garment);

    $response = app(SyncService::class)->push([$offlineSale]);

    expect($response[0]['status'])->toBe('conflict');
    expect($response[0]['conflicts'][0]['garment_id'])->toBe($garment->id);
    expect($response[0]['conflicts'][0]['reason'])->toBe('already_sold');
});
```

---

## 7. Permission Tests

### 7.1 Role-based access tests

```php
test('salesperson cannot view purchase cost', function () {
    $salesperson = User::factory()->salesperson()->create();
    $garment = Garment::factory()->create(['purchase_cost' => 500]);

    $response = $this->actingAs($salesperson)
        ->getJson("/api/v1/garments/{$garment->id}");

    expect($response->json('data.purchase_cost'))->toBeNull();
    expect($response->json('data'))->not->toHaveKey('purchase_cost');
});

test('main admin can view purchase cost', function () {
    $admin = User::factory()->mainAdmin()->create();
    $garment = Garment::factory()->create(['purchase_cost' => 500]);

    $response = $this->actingAs($admin)
        ->getJson("/api/v1/garments/{$garment->id}");

    expect($response->json('data.purchase_cost'))->toBe(500);
});
```

### 7.2 Branch scope tests

```php
test('supervisor cannot access other branch data', function () {
    $supervisorA = User::factory()->supervisor()->forBranch($branchA)->create();
    $saleB = SaleInvoice::factory()->forBranch($branchB)->create();

    $response = $this->actingAs($supervisorA)
        ->getJson("/api/v1/pos/sales/{$saleB->id}");

    $response->assertForbidden();
});
```

### 7.3 Date restriction tests

```php
test('supervisor cannot view historical reports', function () {
    $supervisor = User::factory()->supervisor()->create();
    SaleInvoice::factory()->count(5)->create(['completed_at' => now()->subDays(3)]);

    $response = $this->actingAs($supervisor)
        ->getJson('/api/v1/reports/daily-sales?date=' . now()->subDays(3)->toDateString());

    $response->assertForbidden();
});
```

---

## 8. Acceptance Criteria

### 8.1 POS Sale (WF-3)

| # | Criterion | Test |
|---|---|---|
| AC-1 | Barcode scan identifies garment in < 100ms | Performance test |
| AC-2 | Only available garments can be added to cart | Unit test |
| AC-3 | Discount below minimum price is rejected | Unit test |
| AC-4 | Sale requires full payment | Integration test |
| AC-5 | Multiple payment methods supported | Integration test |
| AC-6 | Sale completion is atomic (no partial stock deduction on failure) | Concurrency test |
| AC-7 | Two concurrent sales of same garment — one fails | Concurrency test |
| AC-8 | Completed sale cannot be cancelled | Integration test |
| AC-9 | Receipt prints without tax line | E2E test |
| AC-10 | Customer phone is mandatory | Validation test |

### 8.2 Offline POS (WF-4, WF-5)

| # | Criterion | Test |
|---|---|---|
| AC-11 | POS works without internet | E2E test |
| AC-12 | Offline sale stored in local cache | Unit test |
| AC-13 | Auto-sync on reconnection | Integration test |
| AC-14 | Manual sync button works | E2E test |
| AC-15 | Duplicate push (same UUID) is idempotent | Integration test |
| AC-16 | Conflict detected when garment already sold | Integration test |
| AC-17 | Conflict escalated to admin | Integration test |
| AC-18 | Cache cleared after successful sync | Unit test |

### 8.3 Transfer (WF-6, WF-7)

| # | Criterion | Test |
|---|---|---|
| AC-19 | Transfer request reserves garments at source | Integration test |
| AC-20 | Reserved garments cannot be sold via POS | Integration test |
| AC-21 | Source supervisor approval required | Permission test |
| AC-22 | Discrepancy between dispatched and received is recorded | Integration test |
| AC-23 | Missing garments not silently marked received | Integration test |

### 8.4 Returns (WF-10, WF-11)

| # | Criterion | Test |
|---|---|---|
| AC-24 | Return only at original selling branch | Integration test |
| AC-25 | Return within 7-day period | Integration test |
| AC-26 | Duplicate return rejected | Integration test |
| AC-27 | Refund within limit approved by supervisor | Permission test |
| AC-28 | Refund above limit requires admin | Permission test |
| AC-29 | Online return Path A (immediate refund) | Integration test |
| AC-30 | Online return Path B (delayed refund) | Integration test |

### 8.5 Cash Management (WF-13, WF-14, WF-15)

| # | Criterion | Test |
|---|---|---|
| AC-31 | Cash handover requires dual confirmation | Integration test |
| AC-32 | Handover mismatch recorded as discrepancy | Integration test |
| AC-33 | Daily closing calculates expected cash | Integration test |
| AC-34 | Discrepancy escalated to admin | Integration test |
| AC-35 | No automatic salary deduction | Policy test |

### 8.6 Inventory Integrity

| # | Criterion | Test |
|---|---|---|
| AC-36 | Garment never appears available at two branches | Concurrency test |
| AC-37 | Movement history is append-only | Integration test |
| AC-38 | Stock count does not auto-change balances | Integration test |
| AC-39 | Only admin approves stock adjustments | Permission test |
| AC-40 | Missing garment recovery preserves original report | Integration test |

### 8.7 Security

| # | Criterion | Test |
|---|---|---|
| AC-41 | 5 failed logins lock account | Integration test |
| AC-42 | Only admin unlocks | Permission test |
| AC-43 | Purchase cost hidden from non-admin | Unit test |
| AC-44 | Supplier info hidden from branch users | Unit test |
| AC-45 | Audit log is append-only | Integration test |
| AC-46 | Role switching changes active scope | Integration test |

### 8.8 Fiscal Year

| # | Criterion | Test |
|---|---|---|
| AC-47 | Serial resets to 00001 in new fiscal year | Integration test |
| AC-48 | Old garments retain original serial | Integration test |
| AC-49 | Old garments carry forward with original identity | Integration test |
| AC-50 | Clearance marking does not change identity | Integration test |

---

## 9. Test Data Management

### 9.1 Factories

Every model has a factory with sensible defaults:

```php
// Example: GarmentFactory
class GarmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'internal_id' => Str::uuid(),
            'barcode' => 'NND-' . Str::random(10),
            'fiscal_year_id' => FiscalYear::factory(),
            'fiscal_year_serial' => '25-' . str_pad($this->faker->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'product_id' => Product::factory(),
            'product_size_id' => ProductSize::factory(),
            'purchase_id' => Purchase::factory(),
            'purchase_cost' => 500,
            'current_status' => 'available',
            'current_branch_id' => Branch::factory(),
        ];
    }

    public function available(): static
    {
        return $this->state(['current_status' => 'available']);
    }

    public function sold(): static
    {
        return $this->state(['current_status' => 'sold']);
    }

    public function atBranch(Branch $branch): static
    {
        return $this->state(['current_branch_id' => $branch->id]);
    }
}
```

### 9.2 Test database

- **Unit tests:** SQLite in-memory (fast, isolated).
- **Integration tests:** PostgreSQL test database (matches production, tests constraints and partitioning).
- **RefreshDatabase trait:** Migrates fresh for each test class.

### 9.3 Seed data for testing

- Test branches: Main, B1, B2, B3, Online.
- Test users: one per role.
- Test products: 10 products with 3-5 sizes each.
- Test garments: 100 garments in various states.

---

## 10. Test Execution

### 10.1 Local development

```bash
# Run all tests
php artisan test

# Run with coverage
php artisan test --coverage

# Run specific module tests
php artisan test app/Modules/Inventory/Tests

# Run only unit tests
php artisan test --testsuite=Unit

# Run with parallel execution
php artisan test --parallel
```

### 10.2 Continuous integration

- Tests run on every pull request.
- Tests must pass before merge.
- Coverage threshold: 80% for critical modules (Inventory, Sales, Cash, Returns).

### 10.3 Pre-release checklist

Before each release:
- [ ] All unit tests pass.
- [ ] All integration tests pass.
- [ ] All concurrency tests pass.
- [ ] All permission tests pass.
- [ ] All acceptance criteria tests pass.
- [ ] No critical or high-severity warnings in static analysis.
- [ ] Manual smoke test of POS, transfer, return, cash closing.
- [ ] Offline sync tested with simulated offline scenario.

---

## 11. Test Categories Summary

| Category | Test count (estimated) | Priority |
|---|---|---|
| Serial generation & garment identity | 15 | Critical |
| POS sale (online) | 25 | Critical |
| POS sale (offline) | 20 | Critical |
| Sync & conflict resolution | 15 | Critical |
| Transfer lifecycle | 20 | Critical |
| Online order multi-branch | 15 | Critical |
| Return processing (physical) | 20 | Critical |
| Return processing (online) | 15 | Critical |
| Cash handover & closing | 15 | Critical |
| Cash discrepancy resolution | 10 | High |
| Courier reconciliation | 10 | High |
| Stock count & adjustment | 15 | High |
| Damaged stock handling | 10 | Medium |
| Permission enforcement | 30 | Critical |
| Branch scope enforcement | 15 | Critical |
| Audit logging | 10 | High |
| Fiscal year management | 10 | Medium |
| Concurrency (row locking) | 10 | Critical |
| **Total estimated** | **~280 tests** | |

---

*End of Testing Strategy Document*
