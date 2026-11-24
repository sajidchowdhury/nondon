# Inventory & Operational Workflow Document
## Nondon — Multi-Branch Women's Dress Retail & Wholesale ERP

**Project:** Nondon
**Version:** 1.0
**Date:** 24 November 2026
**Status:** Design document — pending approval

---

## 1. Purpose

This document defines the step-by-step operational workflows for all critical business processes. Each workflow includes participants, preconditions, step-by-step flow, post-conditions, error handling, and audit points.

These workflows are the basis for implementation, testing, and acceptance criteria.

---

## 2. Workflow Index

| # | Workflow | Module |
|---|---|---|
| 1 | Goods Receiving & Garment Identity Generation | Purchasing |
| 2 | Barcode Label Printing | Purchasing |
| 3 | Physical POS Sale (Online Mode) | Sales |
| 4 | Physical POS Sale (Offline Mode) | Sales |
| 5 | Offline Sync & Conflict Resolution | Sales |
| 6 | Branch Transfer Request & Approval | Transfers |
| 7 | Transfer Dispatch & Receiving | Transfers |
| 8 | Online Order with Multi-Branch Sourcing | Online Orders |
| 9 | Online Order Courier Dispatch | Online Orders |
| 10 | Physical Customer Return | Returns |
| 11 | Online Customer Return (Two Refund Paths) | Returns |
| 12 | Defective Garment Handling | Damaged Stock |
| 13 | Cash Handover (Salesperson to Supervisor) | Cash |
| 14 | Daily Cash Closing | Cash |
| 15 | Cash Discrepancy Resolution | Cash |
| 16 | Courier COD Settlement Reconciliation | Courier Recon |
| 17 | Stock Count (Full & Selective) | Stock Count |
| 18 | Stock Count Freeze | Stock Count |
| 19 | Stock Discrepancy & Adjustment | Stock Count |
| 20 | Missing Garment Report & Recovery | Stock Count |
| 21 | Misplaced/Unidentified Garment Handling | Stock Count |
| 22 | Expense Submission & Approval | Expenses |
| 23 | Supplier Payment | Supplier Payments |
| 24 | Price Update | Catalog |
| 25 | Customer Merge (Duplicate Handling) | Customers |
| 26 | Fiscal Year Close & Carry-Forward | System |
| 27 | Scheduled Report Email | Notifications |

---

## 3. Workflow Details

### WF-1: Goods Receiving & Garment Identity Generation

**Participants:** Main Branch Admin, Receiving Staff
**Preconditions:** Confirmed purchase order exists. Physical garments have arrived at Main Branch.

**Flow:**
1. Receiving staff logs in and opens the purchase record.
2. Staff enters the confirmed received quantity per purchase item line.
3. System validates received quantity <= ordered quantity (excess flagged for review).
4. Staff clicks "Receive & Generate Identities."
5. System begins a database transaction:
   a. Acquires advisory lock for serial generation (`pg_advisory_xact_lock`).
   b. For each received garment (1 to received_quantity):
      - Increments `fiscal_years.current_serial_sequence`.
      - Generates fiscal-year serial: `{prefix}-{sequence_padded_to_5}` (e.g., `25-00001`).
      - Generates internal UUID.
      - Generates permanent barcode (e.g., `NND-{uuid_short}-{serial}` or a system-defined format).
      - Creates `garments` record with status `available`, branch = Main Branch.
      - Creates `garment_current_stock` projection record.
      - Creates `inventory_movements` record (type: `goods_receipt`).
   c. Creates `goods_receipts` record.
   d. Updates `purchase_items.received_quantity`.
   e. Updates `purchases.status` to `received` or `partially_received`.
   f. Dispatches `PurchaseReceived` event.
   g. Audit log: `purchase.receive` per garment and per receipt.
6. Transaction commits.
7. System presents barcode labels for printing (WF-2).

**Post-conditions:**
- Each garment has immutable internal ID, permanent barcode, fiscal-year serial.
- Garments are `available` at Main Branch.
- Movement history begins with `goods_receipt`.

**Error handling:**
- If transaction fails, all garment records are rolled back. No partial identities.
- If received quantity > ordered quantity, system flags the excess; admin decides whether to accept (creates additional purchase item line) or reject (return to supplier).

---

### WF-2: Barcode Label Printing

**Participants:** Receiving Staff
**Preconditions:** Garment records created (WF-1 complete).

**Flow:**
1. Staff selects garments to print labels for (by receipt number or garment range).
2. System generates label data: barcode (Code 128 or similar), garment serial, product name, size, standard price (optional).
3. Staff selects label format:
   - Hang tag: 50x30mm or 75x50mm.
   - Adhesive sticker: 1"x1.5" or 1"x2.625".
4. Staff selects printer (Xprinter XP-420B, G-Printer GP-3120TUC, or Rongta RP400H).
5. System sends print commands via browser (CSS print media or ESC/POS/TSCL via JavaScript bridge).
6. Staff physically attaches labels to garments.

**Post-conditions:**
- Each garment has a physical barcode label attached.
- System records print event in audit log (optional).

---

### WF-3: Physical POS Sale (Online Mode)

**Participants:** Salesperson (or Supervisor/Admin acting as seller)
**Preconditions:** User is logged in with an active role that includes selling. User is at their assigned branch. Internet is available.

**Flow:**
1. Salesperson opens POS interface.
2. **Customer selection:**
   a. Salesperson enters customer phone number.
   b. System searches `customers` table by phone.
   c. If found: select existing customer.
   d. If not found: create new customer (phone mandatory, name optional).
3. **Garment selection (repeatable):**
   a. Salesperson scans barcode OR searches by product/size/serial.
   b. System looks up garment by barcode in `garments` table.
   c. System verifies: garment status = `available`, garment branch = user's branch.
   d. If eligible: add to cart, create temporary reservation (in-memory or short-lived DB reservation with TTL).
   e. If ineligible: show reason (sold, reserved, wrong branch, damaged, etc.).
4. **Discount application (optional):**
   a. Salesperson enters discount amount or percentage per item.
   b. System verifies: final_price >= minimum_price for that product.
   c. If below minimum: reject with message.
5. **Payment collection:**
   a. System calculates total = sum(final_price) - total_discount.
   b. Salesperson enters payment method(s) and amount(s).
   c. Multiple methods allowed (cash + bKash, etc.).
   d. System verifies: total_paid >= total_amount.
   e. If insufficient: block completion, show shortage.
6. **Completion (atomic transaction):**
   a. BEGIN transaction.
   b. For each cart item:
      - `SELECT * FROM garments WHERE id = ? FOR UPDATE` — lock the garment row.
      - Re-verify status = `available`.
      - Update garment status to `sold`.
      - Update `garment_current_stock`.
      - Create `inventory_movements` record (type: `sale`, reference: sale_invoice_id).
   c. Create `sale_invoices` record (status: `completed`).
   d. Create `sale_invoice_items` records.
   e. Create `sale_payments` records.
   f. Record cash collection in `cash_transactions` (credit salesperson's cash account for cash payments).
   g. Link customer to invoice.
   h. Store `return_policy_snapshot` (current return policy at sale time).
   i. Dispatch `SaleCompleted` event.
   j. Audit log: `sale.complete`.
   k. COMMIT.
7. **Receipt printing:**
   a. System generates receipt (thermal 58mm/80mm or A4, per branch config).
   b. No tax line on receipt.
   c. Salesperson hands receipt to customer.

**Post-conditions:**
- Invoice is immutable (cannot be deleted or cancelled).
- Garments are marked `sold`.
- Salesperson's cash account is credited (for cash payments).
- Customer is linked to the sale.
- Return policy snapshot is preserved.

**Error handling:**
- If any garment becomes unavailable between cart creation and checkout (e.g., another device sold it), the transaction fails and the specific garment is flagged. Salesperson removes it and retries.
- If payment fails, the entire transaction rolls back. No stock is deducted.

---

### WF-4: Physical POS Sale (Offline Mode)

**Participants:** Salesperson
**Preconditions:** Internet is unavailable. Device has a populated local cache.

**Flow:**
1. System detects no internet; switches to offline mode indicator.
2. Salesperson opens POS (works from local cache).
3. **Customer selection:**
   a. Salesperson enters phone number.
   b. System searches local customer index (IndexedDB).
   c. If found locally: select.
   d. If not found: create new customer locally (stored in offline queue).
4. **Garment selection:**
   a. Salesperson scans barcode or searches local product catalog.
   b. System checks local stock snapshot — may show as available.
   c. Add to cart (no server-side reservation possible).
5. **Discount and payment:** Same as WF-3 (validated against cached prices).
6. **Completion:**
   a. Generate client UUID for the sale.
   b. Store complete sale record (invoice, items, payments, customer) in IndexedDB offline queue.
   c. Show receipt to customer (printed from local data).
   d. Mark garment as "offline sold" in local cache (prevents double-sell on same device).
7. **No server interaction** until sync (WF-5).

**Post-conditions:**
- Sale is recorded locally only.
- Server does not know about the sale yet.
- Stock on server is not updated.
- Receipt is printed and given to customer.

**Risks:**
- Another device or online order may sell the same garment before sync. Conflict will be detected in WF-5.

---

### WF-5: Offline Sync & Conflict Resolution

**Participants:** System (automatic), Main Branch Admin (for conflicts)
**Preconditions:** Internet is restored OR salesperson clicks "Sync Now."

**Flow:**
1. System detects internet is available.
2. **Push phase:**
   a. System reads all offline sales from IndexedDB.
   b. For each offline sale (identified by client UUID):
      - POST to `/api/sync/push` with full sale data.
      - Server processes in a transaction:
        - Check if `offline_uuid` already exists in `sale_invoices` (idempotency).
        - If exists: return `already_synced`.
        - If not: for each garment in the sale:
          - `SELECT * FROM garments WHERE id = ? FOR UPDATE`.
          - Check status = `available`.
          - If available: proceed (mark sold, create movement).
          - If NOT available (sold/reserved by another transaction while offline):
            flag as **conflict**. Do NOT mark sold. Continue with other garments.
        - If all garments available: create invoice, items, payments, movements. Return `committed`.
        - If some garments conflicted: create invoice with available garments only, flag conflicted garments separately. Return `partial_conflict`.
   c. Server returns per-sale status to client.
3. **Pull phase:**
   a. Client calls `/api/sync/pull`.
   b. Server returns: updated product catalog, prices, current stock snapshot for the branch, customer index.
   c. Client replaces local cache with fresh data.
   d. Clear offline sale queue (all successfully synced sales removed).
4. **Conflict escalation:**
   a. Conflicted sales appear in admin's conflict resolution queue.
   b. Admin reviews: which sale happened first (server timestamp vs offline sale's local timestamp).
   c. Admin decides:
      - **Refund the offline sale** (if online sale was first): create a reversal, refund the customer, record discrepancy.
      - **Reverse the online sale** (if offline sale was first but online completed after): rare; requires refunding the online customer.
      - **Treat as discrepancy**: if both sales genuinely went through, investigate as potential fraud or system error.
   d. Resolution is a separate correction transaction linked to both sales. Original sales are never deleted.

**Post-conditions:**
- All offline sales are either committed or flagged as conflicts.
- Local cache is refreshed.
- Conflicts are resolved by admin with full audit trail.

---

### WF-6: Branch Transfer Request & Approval

**Participants:** Requesting Branch Supervisor (or Online Supervisor), Source Branch Supervisor (or Main Admin)
**Preconditions:** Requesting branch needs stock from another branch.

**Flow:**
1. **Request creation:**
   a. Requesting supervisor opens "New Transfer Request."
   b. Selects source branch (searches stock availability first).
   c. Selects garments to request (by product/size, system shows available garments at source branch).
   d. System creates `transfer_requests` record (status: `pending`).
   e. System creates `transfer_request_items` records (one per garment).
   f. **Implicit reservation:** System updates each garment's status to `reserved` with reservation reference = transfer_request_id.
   g. Creates `inventory_movements` (type: `reservation`, reference: transfer_request_id).
   h. Audit log: `transfer.request`.
2. **Approval:**
   a. Source branch supervisor (or Main Admin if source = Main Branch) sees pending request.
   b. Reviews requested garments.
   c. Approves or rejects:
      - **Approve:** Update status to `approved`. Audit log: `transfer.approve`.
      - **Reject:** Update status to `rejected`, record reason. System releases reservations (garment back to `available`). Audit log: `transfer.reject`.
3. **Post-conditions (approved):**
   - Garments remain `reserved` at source branch.
   - Source branch can now dispatch (WF-7).

**Error handling:**
- If a garment becomes unavailable between request and approval (shouldn't happen since it's reserved), system flags the inconsistency.

---

### WF-7: Transfer Dispatch & Receiving

**Participants:** Source Branch Staff, Destination Branch Staff
**Preconditions:** Transfer request is approved.

**Flow:**
1. **Dispatch:**
   a. Source branch staff opens approved transfer request.
   b. Physically gathers the garments.
   c. Clicks "Dispatch."
   d. System transaction:
      - Update each garment: status `reserved` → `in_transit`.
      - Update `garment_current_stock`.
      - Create `inventory_movements` (type: `transfer_dispatch`, from_branch, to_branch).
      - Update `transfer_requests.status` to `dispatched`.
      - Audit log: `transfer.dispatch`.
   e. System generates dispatch note (printable).
2. **Receiving:**
   a. Destination branch staff opens incoming transfer.
   b. Physically counts received garments.
   c. Enters received quantity.
   d. Optionally scans individual barcodes (not mandatory).
   e. System transaction:
      - If received == dispatched: update each garment status `in_transit` → `available`, update branch to destination.
      - If received < dispatched: update received garments to `available`; create `transfer_discrepancies` record for missing garments (missing garments stay `in_transit` or move to `missing` status).
      - If received > dispatched: flag excess (unexpected garments).
      - Create `inventory_movements` (type: `transfer_receive`).
      - Update `transfer_requests.status` to `received` or `discrepancy`.
      - Audit log: `transfer.receive`.
3. **Discrepancy handling:**
   - Missing garments are flagged for investigation (WF-20).
   - System never silently marks missing garments as received.

**Post-conditions:**
- Received garments are `available` at destination branch.
- Discrepancies are recorded and visible for investigation.
- Transfer is complete or flagged for discrepancy resolution.

---

### WF-8: Online Order with Multi-Branch Sourcing

**Participants:** Online Sales Team, Source Branch Supervisors, Online Sales Supervisor
**Preconditions:** Customer places an online order.

**Flow:**
1. **Order placement:**
   a. Customer places order (via website/Facebook — external; order entered by online staff).
   b. Online staff creates `online_orders` record (status: `pending_confirmation`).
   c. Online staff creates `online_order_items` records (one per garment, specifying product/size).
   d. Customer phone is mandatory; name and address recorded.
2. **Stock verification:**
   a. Online staff checks stock availability across all branches.
   b. For each order item, identifies which branch has the garment available.
   c. System recommends a source branch; staff can override.
3. **Confirmation & transfer requests:**
   a. Online staff clicks "Confirm Order."
   b. System transaction:
      - For each order item:
        - Create `transfer_requests` from source branch to online channel.
        - Create `transfer_request_items` (link to specific garment).
        - Garment status → `reserved` (reservation reference = transfer_request_id).
        - Update `online_order_items.source_branch_id` and `transfer_request_id`.
        - Update `online_order_items.status` to `transfer_requested`.
      - Update `online_orders.status` to `transfers_pending`.
      - Audit log: `online_order.confirm`.
4. **Transfer approval & dispatch (per source branch):**
   a. Each source branch supervisor approves their transfer request (WF-6, WF-7).
   b. Each source branch dispatches to online channel.
   c. Online channel receives each transfer.
   d. Update `online_order_items.status` to `received_at_online` per item.
5. **Ready for dispatch:**
   a. When all items are `received_at_online`, order is ready for courier dispatch.
   b. Update `online_orders.status` to `transfers_received`.

**Post-conditions:**
- Garments are physically at the online channel.
- Order is ready for courier dispatch (WF-9).
- Each garment's movement history shows: goods_receipt → reserved → transfer_dispatch → transfer_receive (at online channel).

---

### WF-9: Online Order Courier Dispatch

**Participants:** Online Sales Team
**Preconditions:** All order items received at online channel (WF-8 complete). Payment status is advance_received or cod_pending.

**Flow:**
1. Online staff opens order ready for dispatch.
2. Enters courier name and tracking number (free-text notes).
3. Clicks "Dispatch to Customer."
4. System transaction:
   - For each order item garment:
     - Update status to `sold`.
     - Create `inventory_movements` (type: `sale`, reference: online_order_id).
   - Update `online_orders.status` to `courier_dispatched`.
   - Update `courier_dispatched_at`.
   - If advance payment: payment_status remains `advance_received`.
   - If COD: payment_status remains `cod_pending`.
   - Audit log: `online_order.dispatch`.
5. System generates dispatch slip (printable).

**Post-conditions:**
- Garments are marked `sold`.
- Courier tracking info is recorded (free-text).
- Order awaits delivery confirmation.

**Delivery confirmation (later):**
- Online staff marks order as `delivered` when courier confirms delivery.
- For COD orders: settlement is pending (WF-16).

---

### WF-10: Physical Customer Return

**Participants:** Salesperson/Cashier (initiates), Branch Supervisor (approves within limit), Main Branch Admin (approves above limit)
**Preconditions:** Customer returns a garment to the original selling branch within 7 days. Garment is not defective (defective goes to WF-12).

**Flow:**
1. **Return initiation:**
   a. Salesperson opens "New Return."
   b. Searches for original sale by:
      - Customer phone number, OR
      - Invoice number, OR
      - Garment barcode scan.
   c. System finds original `sale_invoices` record.
   d. Verifies: return is at the original selling branch (cross-branch returns not allowed).
   e. Verifies: return is within 7-day period (using `return_policy_snapshot` from the invoice).
   f. Verifies: garment has not already been returned (prevent duplicate returns).
2. **Return item selection:**
   a. Salesperson selects which garment(s) from the original invoice are being returned.
   b. Enters return reason.
3. **Refund calculation:**
   a. System calculates refund amount = original final_price for each returned garment.
   b. If exchange (replacement garment): calculate price difference.
4. **Approval:**
   a. If refund amount <= supervisor limit: Branch Supervisor approves.
   b. If refund amount > supervisor limit: escalate to Main Branch Admin.
   c. System records approver, timestamp, reason.
5. **Refund processing:**
   a. Salesperson selects refund method (cash, bKash, bank transfer, Nagad).
   b. If cash: debit salesperson's or branch cash account.
   c. If digital: record reference number.
   d. System transaction:
     - Create `returns` record (status: `completed`).
     - Create `return_items` records.
     - For each returned garment:
       - Update status to `available` (if restockable) or `damaged` (if defective, route to WF-12).
       - Create `inventory_movements` (type: `return_from_customer`).
     - Create cash transaction (debit for refund).
     - Update original invoice (link to return).
     - Audit log: `return.complete`.
6. **Receipt:** Generate return receipt for customer.

**Post-conditions:**
- Original sale is never deleted; return is linked.
- Garment is restocked or routed to damaged stock.
- Refund is recorded with method and reference.
- Customer history is updated.

---

### WF-11: Online Customer Return (Two Refund Paths)

**Participants:** Online Sales Team, Online Sales Supervisor, original physical branch
**Preconditions:** Online customer returns a garment to the online channel.

**Flow:**
1. Customer ships or drops off the garment at the online channel.
2. Online staff receives the garment.
3. Online staff looks up the original online order.
4. Verifies return eligibility (7-day period, not already returned).
5. Creates return record.
6. **Refund path determination:**
   - **Path A — Online channel has sufficient cash:**
     a. Online Supervisor approves refund (within limit) or escalates to Admin (above limit).
     b. Online staff refunds customer immediately (cash, bKash, etc.).
     c. Debit online channel cash account.
     d. Transfer the garment back to the original physical branch (create transfer request from online channel to original branch).
     e. When original branch receives the garment, it's restocked as `available`.
   - **Path B — Online channel does not have sufficient cash:**
     a. Online Supervisor approves return (within limit) or escalates to Admin.
     b. Transfer the garment to the original physical branch.
     c. When original branch receives the garment:
       - Original branch processes the refund.
       - Original branch sends money to online channel (via bank transfer, etc.).
       - Online channel's cash account is credited.
7. System transaction per path:
   - Create `returns` record (return_type: `online`).
   - Create `return_items` records.
   - Create transfer request (online channel → original branch).
   - Update garment status to `in_transit` (being transferred back).
   - Audit log: `online_return.initiate`.
   - On receipt at original branch: update garment to `available`, audit log: `online_return.complete`.
8. **If defective:** Route to WF-12 (damaged garment flow to Main Branch).

**Post-conditions:**
- Garment returns to the original physical branch.
- Refund money flows correctly between online channel and original branch.
- Full audit trail of garment movement and money flow.

---

### WF-12: Defective Garment Handling

**Participants:** Salesperson (reports), Branch Supervisor (verifies, for customer returns), Main Branch Admin (decides, for supplier returns)
**Preconditions:** A garment is identified as defective.

**Flow:**
1. **Damage report:**
   a. Staff reports the garment as damaged/defective.
   b. System immediately blocks the garment from sale and online availability (status → `damaged` or `under_inspection`).
   c. Create `damage_reports` record.
   d. Audit log: `damage.report`.
2. **Verification:**
   a. Branch Supervisor verifies the damage physically.
   b. Records findings and evidence (photo optional).
   c. Submit to Main Branch.
3. **Transfer to Main Branch (if at a physical branch):**
   a. Create transfer request to Main Branch.
   b. Dispatch garment to Main Branch.
   c. Main Branch receives, status → `under_inspection`.
4. **Inspection:**
   a. Branch Supervisor handles inspection for customer returns.
   b. Main Branch Admin handles inspection for supplier returns.
   c. Record findings, recommendations.
5. **Disposition decision (Main Branch Admin):**
   - **Repair and return to stock:** status → `available`.
   - **Return to supplier:** status → `returned_to_supplier`, create supplier return record.
   - **Clearance:** status → `clearance`, is_clearance = true.
   - **Keep in damaged stock:** status remains `damaged`, moved to damaged stock location.
   - **Dispose/write-off:** status → `disposed`.
6. **Supplier rejection (if returned to supplier and supplier rejects):**
   a. Record rejection and reason.
   b. Move garment to separate damaged-stock holding area.
   c. Block from all sales channels.
   d. Pending management decision.
7. Audit log: `damage.disposition` with decision.

**Post-conditions:**
- Garment is blocked from sale from the moment damage is reported.
- Full inspection and disposition history retained.
- Supplier rejection (if any) is recorded.

---

### WF-13: Cash Handover (Salesperson to Supervisor)

**Participants:** Salesperson, Branch Supervisor
**Preconditions:** Salesperson has collected cash during the day and wants to hand over to supervisor.

**Flow:**
1. **Salesperson initiates:**
   a. Opens "New Cash Handover."
   b. Enters the amount to hand over.
   c. System shows salesperson's current cash balance.
   d. Salesperson confirms the amount.
   e. System creates `cash_handovers` record (status: `pending`, salesperson_entered_amount set).
   f. Audit log: `handover.initiate`.
2. **Supervisor receives:**
   a. Supervisor sees pending handover.
   b. Physically counts the cash.
   c. Enters the counted amount.
   d. System compares:
     - If matched (salesperson_entered == supervisor_entered): status → `matched`.
     - If mismatched: status → `mismatched`, discrepancy_amount recorded.
3. **If matched:**
   a. System transaction:
     - Debit salesperson's cash account.
     - Credit branch cash account.
     - Create two `cash_transactions` records (one per account).
     - Audit log: `handover.complete`.
4. **If mismatched:**
   a. Both amounts and the discrepancy are retained.
   b. Supervisor records explanation.
   c. System still moves the supervisor_entered_amount to branch cash.
   d. Discrepancy is flagged for admin investigation (WF-15).
   e. Audit log: `handover.mismatch`.

**Post-conditions:**
- Cash has moved from salesperson to branch account.
- Both amounts are recorded with timestamps and users.
- Mismatches are visible for investigation.

---

### WF-14: Daily Cash Closing

**Participants:** Branch Supervisor (or Online Supervisor for online channel)
**Preconditions:** Business day is ending.

**Flow:**
1. Supervisor opens "Daily Cash Closing."
2. System calculates expected cash:
   ```
   Expected = Opening Cash
            + Cash Sales Collections
            - Cash Refunds
            - Approved Cash Expenses
            - Cash Handovers (to supervisor)
   ```
3. Supervisor physically counts cash.
4. Enters counted amount.
5. System records:
   - `cash_closings` record with opening, expected, counted, discrepancy (generated column).
   - Status: `submitted`.
6. **If discrepancy == 0:** Closing is complete.
7. **If discrepancy != 0:**
   a. Supervisor's responsibility ends at recording the factual count and difference.
   b. Status → `submitted` (with discrepancy).
   c. Escalated to Main Branch Admin for investigation (WF-15).
8. Audit log: `cash.closing.submit`.
9. System triggers scheduled daily cash report email to configured recipients.

**Post-conditions:**
- Daily cash closing is recorded.
- Discrepancies are flagged for admin.
- Historical closing records are immutable.

---

### WF-15: Cash Discrepancy Resolution

**Participants:** Main Branch Admin
**Preconditions:** A cash closing with discrepancy exists, OR a cash handover mismatch exists.

**Flow:**
1. Admin sees discrepancy in review queue.
2. Admin investigates:
   - Reviews cash transactions for the day.
   - Reviews handovers.
   - Reviews individual sales.
   - May interview staff (offline process).
3. Admin records:
   - Investigation findings.
   - Resolution decision (e.g., "counting error," "cash shortage accepted," "further action required").
   - Resolution notes.
4. System updates:
   - `cash_closings.status` → `resolved`.
   - `cash_closings.investigated_by`, `resolved_by`, `resolution_notes`.
   - Or `cash_handovers.status` → `resolved`.
5. **No automatic salary deduction** (policy).
6. Audit log: `cash.discrepancy.resolve`.

**Post-conditions:**
- Discrepancy is resolved with documented findings.
- Original records are never overwritten.
- Resolution is a separate audit entry.

---

### WF-16: Courier COD Settlement Reconciliation

**Participants:** Online Sales Supervisor
**Preconditions:** Courier has collected COD amounts and provided a settlement statement.

**Flow:**
1. Online Supervisor opens "New Courier Settlement."
2. Enters settlement details:
   - Settlement date.
   - Total COD collected.
   - Courier charges.
   - COD fees.
   - Other deductions.
   - Net amount received.
3. **Line-by-line matching:**
   a. Supervisor enters or imports settlement lines (per order).
   b. Each line: courier tracking ref, COD amount collected, courier charge, net.
   c. System matches each line to an `online_order` by tracking ref or order number.
   d. Match status per line:
     - `matched`: order found, amounts match.
     - `unmatched`: no order found for this tracking ref.
     - `short`: COD collected < order total.
     - `excess`: COD collected > order total.
     - `duplicate`: same order already settled in another settlement.
4. **Confirmation:**
   a. Supervisor reviews all lines and match statuses.
   b. Confirms the settlement.
   c. System transaction:
     - Create `courier_settlements` record.
     - Create `courier_settlement_lines` records.
     - For each matched order: update `online_orders.payment_status` → `cod_settled`.
     - Credit online channel cash account with net_amount_received.
     - Create `cash_transactions` (type: `courier_settlement`).
     - Audit log: `courier.settlement.reconcile`.
5. **Unmatched/disputed lines:**
   - Remain visible for investigation.
   - Admin can review (end-of-day consolidated report).

**Post-conditions:**
- COD amounts are recognized as business cash only after settlement.
- Unmatched lines are preserved for investigation.
- Online channel cash account is credited.

---

### WF-17: Stock Count (Full & Selective)

**Participants:** Branch Supervisor (initiates), Salesperson/Staff (counts), Main Branch Admin (approves adjustments)
**Preconditions:** Stock count is scheduled or initiated ad hoc.

**Flow:**
1. **Initiation:**
   a. Supervisor creates `stock_counts` record.
   b. Selects scope: full branch or selective (by product/size/garment).
   c. Selects freeze type: none, full branch, or selective.
   d. If freeze: system applies freeze (WF-18).
2. **Expected stock generation:**
   a. System generates `stock_count_lines` for each garment in scope.
   b. Records expected status, expected branch.
3. **Physical count:**
   a. Counting staff scan barcodes or enter garment IDs.
   b. For each garment found: mark as `was_found`, record physical location.
   c. Garments in expected list but not found: remain `was_found = false` (potential missing).
   d. Garments found but not in expected list: flagged as `unexpected`.
4. **Completion:**
   a. Staff marks count as complete.
   b. System calculates discrepancies:
     - Missing: expected but not found.
     - Unexpected: found but not expected.
     - Wrong location: found in different branch/location than expected.
     - Wrong status: found with different status than expected.
   c. Status → `counted`.
5. **Submission:**
   a. Supervisor reviews discrepancies.
   b. Enters reasons for each discrepancy (mandatory).
   c. Optionally attaches evidence.
   d. Submits to Main Branch Admin.
   e. Status → `submitted`.
6. **Approval & adjustment:**
   a. Admin reviews submitted count.
   b. For each discrepancy, admin approves or rejects the proposed adjustment.
   c. Approved adjustments create `stock_adjustments` records (WF-19).
   d. Status → `approved` (or `rejected` if admin rejects the entire count).
   e. Freeze is released (if applicable).

**Post-conditions:**
- Stock count does not automatically change balances.
- Only admin-approved adjustments change official inventory.
- Full count history is retained.

---

### WF-18: Stock Count Freeze

**Participants:** Branch Supervisor (initiates), System (enforces)
**Preconditions:** Stock count with freeze type selected.

**Flow:**
1. Supervisor initiates count with freeze.
2. System records freeze scope (full branch or selective garments).
3. System creates a freeze record: initiator, scope, start time, reason.
4. **Enforcement:** During freeze:
   - All stock-mutating operations for frozen scope are blocked:
     - POS sales of frozen garments → blocked with message "garment is frozen for stock count."
     - Transfer requests involving frozen garments → blocked.
     - Online order confirmation reserving frozen garments → blocked.
   - In-progress transactions at freeze start: completed transactions are preserved. If a transaction is in progress (cart created but not completed), it must either complete before the freeze takes effect or be abandoned.
5. **Release:**
   a. When count is complete and approved (or cancelled).
   b. System releases freeze.
   c. Records release time.
   d. Frozen scope returns to normal operation.

**Post-conditions:**
- No stock transactions bypassed the freeze.
- Freeze record is retained with full scope and timing.

---

### WF-19: Stock Discrepancy & Adjustment

**Participants:** Main Branch Admin
**Preconditions:** Stock count submitted with discrepancies.

**Flow:**
1. Admin reviews each discrepancy from the submitted count.
2. For each discrepancy (missing, excess, wrong location, wrong status):
   a. Admin reviews the reason and evidence submitted by supervisor.
   b. Admin may request additional investigation.
   c. Admin approves or rejects the adjustment.
3. **If approved:**
   a. System creates `stock_adjustments` record:
     - garment_id, adjustment_type, from_status, to_status, from_branch, to_branch, reason.
     - approved_by, approved_at.
   b. System applies the adjustment:
     - Update garment status/location.
     - Update `garment_current_stock`.
     - Create `inventory_movements` (type: `stock_adjustment`).
   c. Audit log: `stock.adjustment.approve`.
4. **If rejected:**
   a. Record rejection reason.
   b. Garment remains in its pre-count state.
   c. Audit log: `stock.adjustment.reject`.

**Post-conditions:**
- Only approved adjustments change official inventory.
- Every adjustment is linked to the stock count and discrepancy.
- Full history is retained.

---

### WF-20: Missing Garment Report & Recovery

**Participants:** Branch Supervisor, Main Branch Admin
**Preconditions:** A garment is reported missing (from stock count or transfer discrepancy).

**Flow:**
1. **Missing report:**
   a. Garment is flagged as missing (from stock count or transfer receiving discrepancy).
   b. System updates garment status → `missing`.
   c. Create `inventory_movements` (type: `missing_report`).
   d. Audit log: `garment.missing`.
2. **Investigation:**
   a. Admin reviews garment's movement history.
   b. Checks last known location, branch, status.
   c. Interviews staff if needed (offline).
3. **Recovery (if found):**
   a. Staff scans the garment's barcode.
   b. System identifies the garment and its missing status.
   c. Branch Supervisor physically verifies identity and condition.
   d. Supervisor submits recovery report with explanation.
   e. Admin approves or rejects restoration.
   f. If approved:
     - Update garment status → `available` (or appropriate status).
     - Create `inventory_movements` (type: `recovery`).
     - Link recovery to original missing report.
     - Audit log: `garment.recover`.
   g. If rejected: garment remains `missing`, record rejection reason.
4. **Never erase history:**
   - The original missing report remains linked to the recovery.
   - Recovery does not delete or overwrite the missing record.

**Post-conditions:**
- Missing and recovery events are both retained.
- Garment is restored only after admin approval.

---

### WF-21: Misplaced/Unidentified Garment Handling

**Participants:** Branch Staff, Branch Supervisor, Main Branch Admin
**Preconditions:** A garment is physically found at a branch but its recorded location doesn't match.

**Flow:**
1. Staff scans the barcode of a garment found at their branch.
2. System displays recorded identity, status, and location.
3. If recorded branch != current branch:
   a. Staff creates a "misplaced/unidentified garment report."
   b. Garment enters `missing` or investigation status.
   c. System does NOT automatically move the garment to the current branch's stock.
4. Admin investigates:
   - Reviews movement history.
   - Determines how the garment arrived at the wrong location (unrecorded transfer, theft recovery, etc.).
5. Admin approves or rejects location correction:
   - **Approve:** Create stock adjustment moving garment to correct branch. Create `inventory_movements` (type: `stock_adjustment`).
   - **Reject:** Garment remains in investigation status; physical handling decided offline.
6. **Principle:** A valid barcode proves identity, not authority. The garment is not claimed by the branch that found it without admin approval.
7. **Principle:** The same garment must never appear as available at two branches.

**Post-conditions:**
- Location correction only after admin approval.
- Full investigation history retained.

---

### WF-22: Expense Submission & Approval

**Participants:** Branch Supervisor (submits), Main Branch Admin (approves)
**Preconditions:** Branch incurs an expense.

**Flow:**
1. Supervisor opens "New Expense."
2. Enters: amount, category, date, description, branch (own branch only).
3. Optionally attaches evidence (photo/document).
4. System creates `expenses` record (status: `pending`).
5. Audit log: `expense.submit`.
6. Admin reviews pending expenses.
7. Admin approves or rejects:
   - **Approve:** Status → `approved`. Debit branch cash account. Create `cash_transactions` (type: `expense`). Audit log: `expense.approve`.
   - **Reject:** Status → `rejected`. Record rejection reason. Audit log: `expense.reject`.
8. Approved expenses cannot be silently changed or deleted.

---

### WF-23: Supplier Payment

**Participants:** Main Branch Admin
**Preconditions:** Supplier is owed money for purchases.

**Flow:**
1. Admin opens "New Supplier Payment."
2. Selects supplier.
3. System shows outstanding balance and linked purchases.
4. Admin selects purchase(s) to pay against (or records as general payment).
5. Enters: amount, payment method, reference number, payment date.
6. System creates `supplier_payments` record (status: `draft`).
7. Admin confirms payment:
   - Status → `confirmed`.
   - Update `suppliers.outstanding_balance`.
   - Audit log: `supplier.payment.confirm`.
8. Duplicate detection:
   - System checks for existing confirmed payment with same supplier, amount, and date.
   - If potential duplicate: flag for admin review.
9. Confirmed payments cannot be edited or deleted. Corrections require reversal transactions.

---

### WF-24: Price Update

**Participants:** Main Branch Admin
**Preconditions:** Admin wants to change standard or minimum price for a product.

**Flow:**
1. Admin opens product's price management.
2. Enters new standard_price and/or minimum_price.
3. Sets effective date (default: now).
4. System creates new `product_prices` record with `effective_from = now`.
5. Sets `effective_to` of the previous price record to the new effective date.
6. Audit log: `price.update` with previous and new values.
7. Historical invoices retain the price at sale time (they reference the price record effective at sale time).
8. New sales use the new price immediately.

**Post-conditions:**
- Price history is retained.
- Historical invoices are unaffected.
- New sales use the new price.

---

### WF-25: Customer Merge (Duplicate Handling)

**Participants:** Main Branch Admin
**Preconditions:** Two customer records exist for the same person (duplicate phone or name match).

**Flow:**
1. Admin identifies duplicate customers (system may suggest based on phone similarity).
2. Admin selects primary customer and duplicate customer.
3. System transaction:
   a. Update all `sale_invoices.customer_id` from duplicate to primary.
   b. Update all `online_orders.customer_id` from duplicate to primary.
   c. Update all `returns.customer_id` from duplicate to primary.
   d. Set duplicate customer's `merged_into_id = primary.id`.
   e. Mark duplicate as inactive (do not delete).
   f. Audit log: `customer.merge`.
4. Primary customer retains all purchase history from both records.

**Post-conditions:**
- No customer records are deleted.
- All transaction history is linked to the primary customer.
- Merge is auditable.

---

### WF-26: Fiscal Year Close & Carry-Forward

**Participants:** Main Branch Admin
**Preconditions:** Fiscal year is ending (June 30).

**Flow:**
1. Admin opens fiscal year management.
2. Creates new fiscal year record (e.g., FY 26_27, start July 1, prefix `26`).
3. System validates date overlap with existing fiscal years.
4. **Carry-forward of unsold stock:**
   a. All garments with status `available` (or other non-sold statuses) carry forward with their original identity, barcode, and serial.
   b. No new identities are created.
   c. No status changes are forced.
5. **Clearance marking (optional):**
   a. Admin can mark selected old-year garments as `clearance`.
   b. This sets `is_clearance = true` but does not change garment identity.
6. **Serial sequence reset:**
   a. New fiscal year's `current_serial_sequence` starts at 0.
   b. New garments received after July 1 get serials starting from `26-00001`.
7. **Old fiscal year close:**
   a. Set old fiscal year `is_closed = true`, `is_active = false`.
   b. Historical reports remain associated with the correct fiscal year.
8. Audit log: `fiscal_year.close`, `fiscal_year.create`.

**Post-conditions:**
- Old garments retain their original fiscal-year identity.
- New garments get new fiscal-year serials starting from 00001.
- Historical reports remain tied to their fiscal year.

---

### WF-27: Scheduled Report Email

**Participants:** System (automatic), Main Branch Admin (configures)
**Preconditions:** Scheduled report is configured.

**Flow:**
1. Admin configures scheduled report:
   - Report type (daily cash, monthly ledger, etc.).
   - Recipient email(s).
   - Schedule (cron expression).
   - Branch scope (all branches or specific).
2. System creates `scheduled_reports` record.
3. Laravel Scheduler checks scheduled reports.
4. At the scheduled time:
   a. System generates the report data (queries, summary).
   b. Renders report as PDF or HTML email body.
   c. Sends email to configured recipients.
   d. Records `last_run_at` and calculates `next_run_at`.
5. **Daily cash report (example):**
   - Triggered daily at a configured time (e.g., 8:00 AM).
   - Covers previous business day.
   - Per branch: opening cash, sales, refunds, expenses, handovers, closing cash, discrepancy.
   - Sent to admin and any configured supervisors.
6. **Monthly ledger report (example):**
   - Triggered monthly on the 1st.
   - Covers previous month.
   - Per customer: purchases, returns, outstanding.
   - Sent to admin.
7. Audit log: `report.schedule.send`.

**Post-conditions:**
- Reports are emailed automatically.
- Admin receives reports without needing to log in.
- Historical report generation is auditable.

---

## 4. Workflow Summary Table

| Workflow | Atomic Transaction | Audit Logged | Admin Approval Required | Concurrency Protected |
|---|---|---|---|---|
| WF-1 Goods Receiving | Yes | Yes | No | Yes (advisory lock) |
| WF-3 POS Sale (online) | Yes | Yes | No | Yes (row lock) |
| WF-4 POS Sale (offline) | Local only | On sync | No | Conflict on sync |
| WF-6 Transfer Request | Yes | Yes | Yes (source supervisor) | Yes (reservation) |
| WF-7 Transfer Dispatch/Receive | Yes | Yes | No | Yes |
| WF-8 Online Order | Yes | Yes | Yes (per source branch) | Yes (reservation) |
| WF-10 Physical Return | Yes | Yes | Yes (within/above limit) | Yes |
| WF-11 Online Return | Yes | Yes | Yes (within/above limit) | Yes |
| WF-12 Defective Handling | Yes | Yes | Yes (admin disposition) | Yes (immediate block) |
| WF-13 Cash Handover | Yes | Yes | Dual confirmation | No |
| WF-14 Cash Closing | Yes | Yes | No (discrepancy escalates) | No |
| WF-15 Cash Discrepancy | Yes | Yes | Yes (admin) | No |
| WF-16 Courier Settlement | Yes | Yes | No (supervisor) | No |
| WF-17 Stock Count | No (count only) | Yes | Yes (adjustments) | Freeze enforces |
| WF-19 Stock Adjustment | Yes | Yes | Yes (admin) | Yes |
| WF-20 Missing Recovery | Yes | Yes | Yes (admin) | Yes |
| WF-23 Supplier Payment | Yes | Yes | Yes (admin) | No |
| WF-24 Price Update | Yes | Yes | Yes (admin) | No |
| WF-26 Fiscal Year Close | Yes | Yes | Yes (admin) | No |

---

*End of Inventory & Operational Workflow Document*
