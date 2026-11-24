# Business Requirements Document
## Multi-Branch Women's Dress Retail & Wholesale ERP

**Project:** Nondon
**Document Version:** 1.6
**Date:** 24 November 2026
**Status:** Requirements discovery complete — pending client formal approval before development
**Database:** PostgreSQL
**Architecture:** Laravel modular monolith
**Fiscal Year:** July 1 to June 30 (notation: FY 25_26 = July 2025 through June 2026)

---

## 1. Executive Summary

The proposed ERP will manage a women's dress retail and wholesale business operating through a central Main Branch, multiple physical retail branches (B1, B2, B3), and an Online Sales channel.

The system will provide centralized purchasing, garment-level inventory tracking, branch transfers, POS sales, online order management, cash management, customer records, operational reporting, employee permissions, and auditability.

The Main Branch is the central purchasing and inventory authority. All purchased garments are first received and stored at Main Branch before distribution to other branches.

The system must support business growth without sacrificing inventory accuracy, financial integrity, employee accountability, or performance.

### 1.1 Business objectives

- Maintain accurate garment-level stock visibility.
- Ensure every physical garment has a unique identity and permanent barcode.
- Centralize purchasing and supplier communication.
- Track inventory across branches and movements.
- Prevent duplicate sales and conflicting stock reservations.
- Manage retail, wholesale, and online sales.
- Control returns, exchanges, damaged stock, and refunds.
- Maintain salesperson-level and branch-level cash accountability.
- Reconcile courier COD collections.
- Support physical stock counting and discrepancy investigations.
- Restrict sensitive financial and purchasing information according to user permissions.
- Maintain reliable historical records and audit trails.
- Operate POS during internet outages via local cache.

---

## 2. Business Structure

### 2.1 Physical branches

- **Main Branch** — Head Office, central purchasing, central warehouse, and retail sales point.
- **B1** — Retail branch.
- **B2** — Retail branch.
- **B3** — Retail branch.
- Architecture must support additional branches in the future (designed for 100+ branch scale).

### 2.2 Online Sales Channel

The Online Sales Channel is treated as a **branch-equivalent entity** for both cash management and transient stock-holding purposes.

- Operates as a sales channel with its own cash account.
- Receives stock via transfer from physical branches (transient holding).
- Dispatches garments to customers via courier.
- Does not hold long-term inventory.
- Online Sales Supervisor has full branch-equivalent financial authority over the online channel.

### 2.3 Main Branch authority

Main Branch is responsible for:
- Supplier relationships and purchasing.
- Initial goods receiving.
- Central stock distribution.
- Product and design management.
- Pricing and minimum selling prices.
- Central financial management.
- Employee account and permission management.
- Final inventory adjustments and central administrative approvals.
- Supplier-return inspection.
- End-of-day consolidated cashbook review across all branches and the online channel.

Other physical branches must not access supplier information.

---

## 3. Product and Garment Identity

### 3.1 Individual garment identity

Every physical garment must have:
- An **immutable internal garment ID** — never replaced, never reused.
- A **permanent, globally unique barcode** — never reused, independent of fiscal-year serial.
- A **fiscal-year serial number** — restarts each fiscal year, format defined in §3.2.
- **Product/design identity.**
- **Size or variant.**
- **Purchase batch reference.**
- **Current branch and inventory status.**

The permanent barcode and internal garment ID must never change, even when stock carries into a later fiscal year.

### 3.2 Fiscal-year serial numbers

- Fiscal year runs from July 1 to June 30.
- Serial numbering restarts each fiscal year.
- **Format:** `YY-NNNNN` where `YY` = 2-digit calendar year of fiscal year start, `NNNNN` = 5-digit zero-padded sequence.
- **Scope:** Single global sequence per fiscal year across all garments — not per-product, not per-design. Each physical garment receives the next number regardless of design, size, or supplier.
- **Example:**
  - FY 25_26 (July 2025 – June 2026): `25-00001`, `25-00002`, ..., `25-01209` (mid-year), ..., `25-06500` (year-end).
  - FY 26_27 (July 2026 – June 2027): `26-00001`, `26-00002`, ...
- The serial number itself indicates approximate total garments received in the fiscal year.
- **Uniqueness constraint:** `(fiscal_year_id, serial_number)` globally unique across all garments.
- Existing garments retain their original serial and fiscal-year identity even when stock carries into a later fiscal year.

### 3.3 Product sizes and designs

- A design may have sizes such as 32, 34, and 36.
- Each physical garment is individually identifiable.
- The same design may be purchased multiple times in one fiscal year.
- Purchase batches must be separately traceable.
- Product/design information is centrally managed.

### 3.4 Product management authority

Only Main Branch Admin can:
- Create products and designs.
- Edit product names and descriptions.
- Add or modify sizes and variants.
- Activate or deactivate product/design records.
- Set standard selling prices and minimum selling prices.

Deactivated products remain available in historical transactions and reports.

Identity-related corrections to individual garments follow the controlled approval process defined in §16.

---

## 4. Purchasing and Goods Receiving

### 4.1 Purchasing authority

Only Main Branch Admin can:
- Create purchase records.
- Edit purchase records before confirmation.
- Confirm and finalize purchases.
- Manage supplier information.
- Record purchase costs and related financial details.

Only Main Branch communicates with suppliers. Other branches must not see supplier details, supplier contacts, or purchase costs.

### 4.2 Receiving workflow

1. Purchased garments physically arrive at Main Branch.
2. Authorized receiving staff enter the confirmed received quantity.
3. The system creates individual garment records.
4. The system generates fiscal-year serials (next number in the global sequence) and permanent barcodes.
5. Barcode labels are printed and attached to garments.
6. Garments enter Main Branch inventory.
7. Main Branch can subsequently distribute stock to other branches.

Suppliers may provide products without barcodes. The ERP-generated barcode is the official identification method. The system must prevent duplicate barcode generation.

### 4.3 Purchase integrity

- Every garment must be traceable to its purchase batch.
- Confirmed purchases cannot be silently edited or deleted.
- Corrections must preserve the original record and audit history.
- Purchase cost is restricted to Main Branch Admin.
- Access to purchase-cost information must be audited.

### 4.4 Barcode label hardware

- **Hang tags:** 50 x 30 mm or 75 x 50 mm.
- **Adhesive stickers:** 1" x 1.5" to 1" x 2.625" (25.4 x 38.1 mm to 25.4 x 66.7 mm).
- **Supported printers:** Xprinter XP-420B, G-Printer GP-3120TUC, Rongta RP400H (thermal, ESC/POS or TSCL protocol).
- Label-generation module must support all listed sizes and protocols.

---

## 5. Inventory Management

### 5.1 Inventory model

The system tracks every physical garment individually.

Inventory states must distinguish at least:
- Available.
- Reserved (against a transfer request or online order).
- In transit.
- Sold.
- Damaged.
- Under inspection.
- Missing or under investigation.
- Temporarily frozen for stock counting.
- Other approved non-saleable states.

A garment cannot be available in two branches simultaneously. A garment can belong to only one active physical inventory location or reservation state at a time.

### 5.2 Main Branch stock area

- Central warehouse stock and Main Branch retail stock occupy the same physical storage area initially.
- The user interface must not force artificial separation.
- The underlying design supports multiple stock locations per branch in the future.

### 5.3 Stock visibility

All authorized branch staff can view available stock across all physical branches and the online channel.

**Visible details:**
- Design/product.
- Size.
- Available quantity.
- Garment serial number.
- Availability status.
- Current branch/channel.

**Restricted details (not visible to non-admin roles):**
- Purchase cost.
- Supplier details.
- Supplier communications.

Stock visibility does not authorize transactions at another branch. **Stock visibility and stock authority are separate permissions.**

### 5.4 Fiscal-year stock carry-forward

- Unsold garments retain their original identity, barcode, and serial.
- Stock carries forward into the next fiscal year.
- Management can mark selected old stock for clearance.
- Clearance status does not create a new garment identity.
- Historical purchase and serial information remains intact.

### 5.5 Stock balance integrity

Implementation:
- Immutable inventory transaction ledger (append-only movement history).
- Current stock balance projection (denormalized, maintained alongside ledger).
- Separate reservation records.
- Reconciliation between ledger and current balances.
- Atomic transactions for all stock movements.
- Row-level locking or equivalent concurrency control.
- Database constraints for unique garment identities and barcodes.

Routine stock queries must not repeatedly scan the full historical ledger — they use the current-stock projection.

---

## 6. Branch-to-Branch Stock Transfers

### 6.1 Transfer request authority

Only Branch Supervisors can create and submit formal transfer requests for their own branch. Online Sales Supervisors create transfer requests from physical branches to the online channel.

### 6.2 Approval authority

- Transfers originating from B1, B2, or B3 require approval from the respective source Branch Supervisor.
- Transfers originating from Main Branch require Main Branch Admin approval.

### 6.3 Transfer workflow

1. Destination branch/channel requests stock.
2. Source branch reviews the request.
3. Authorized source branch personnel approve the transfer.
4. **Creating the transfer request implicitly reserves the garments** at the source branch — they cannot be sold to another customer while the transfer is pending.
5. Source branch prepares and dispatches the garments.
6. Stock enters an in-transit state.
7. Destination branch/channel confirms the received quantity.
8. System records the completed receipt and any discrepancy.

### 6.4 Dispatch and receiving

- Source branch records dispatched garments.
- Destination branch confirms total received quantity.
- Individual barcode scanning is not mandatory at receiving.
- Individual garment identities remain recorded in the transfer.
- Any shortage, excess, or discrepancy must be recorded.
- Receiving discrepancies cannot be silently accepted.

### 6.5 Branch scope

- Employees can perform transactions only at their assigned physical branch.
- An employee from Branch A can only sell from Branch A.
- If a customer wants a product not available at Branch A but available at Branch B:
  1. Salesman at Branch A searches central stock, finds it at Branch B.
  2. Salesman creates a demand/transfer request to Branch B.
  3. Branch B approves and dispatches.
  4. Branch A physically receives the item.
  5. Now Branch A owns the product; salesman can sell it.
- **Cross-branch payment is prohibited.** Customer cannot buy at one branch and pay at another. "Sell-here-pick-there" is not allowed.
- Viewing another branch's stock does not grant permission to sell, receive, transfer, or adjust that stock.

---

## 7. Pricing and Sales Policy

### 7.1 Standard pricing

Only Main Branch Admin can create or modify:
- Standard selling prices.
- Minimum selling prices.

Price configuration supports per-design or per-design-size at admin's discretion. No tiered or quantity-based pricing in the initial release. Branch Supervisors cannot independently modify prices.

### 7.2 Discount policy

- Salespeople can apply discounts freely.
- No employee-specific discount limit in the initial release.
- Selling below the configured minimum price is prohibited.
- Discount activity is recorded against the invoice and responsible salesperson.
- Main Branch Admin can review discounts across all branches.

### 7.3 Retail and wholesale

- Retail and wholesale use the same standard selling price initially.
- The same discount policy applies.
- Sale type is separately recorded as retail or wholesale.
- No separate wholesale price or quantity-tier pricing in the initial release.

### 7.4 Purchase-cost confidentiality

Only Main Branch Admin users can view purchase costs.

The following users must not see purchase costs:
- Branch Supervisors.
- Salespeople.
- Online Sales Team.
- Online Sales Supervisor.

Access to purchase-cost information must be audited.

---

## 8. Physical POS Sales

### 8.1 Garment selection

POS supports:
- Barcode scanning.
- Manual search by product/design.
- Manual search by size.
- Manual search by fiscal-year serial number.

Only saleable (Available) garments can be added. The system revalidates availability during checkout.

### 8.2 Invoice composition

- One invoice can contain multiple designs and sizes.
- Every garment remains individually identifiable.
- All invoice items must be committed atomically.
- No fixed business-imposed limit on the number of garment combinations in an invoice.

### 8.3 Reservation and checkout

- Checkout temporarily reserves selected garments.
- Stock is revalidated at checkout.
- Completed and fully paid sales deduct stock.
- Payment and inventory updates must be atomic.
- Incomplete or unpaid carts do not permanently deduct inventory.
- The system must prevent two users from selling the same garment concurrently.

### 8.4 Wrong garment selection

Before payment:
- Staff can remove an individual garment.
- The original reservation is released.
- A replacement garment can be selected and reserved.
- Invoice totals are recalculated.

### 8.5 Completed sale correction

- Completed invoices cannot be deleted or cancelled.
- Corrections must use controlled return or reversal transactions linked to the original sale.
- Unpaid or incomplete carts may be abandoned.

### 8.6 Printing

Both formats are supported:
- Thermal receipt: 58mm or 80mm.
- A4 invoice.

Printing preferences can be configured per branch. Both documents derive from the same invoice record.

**No tax/VAT line on customer-facing invoice** (client decision). Legal/compliance review required before final approval for Bangladesh VAT requirements.

### 8.7 Physical customer payment

- Customer phone number is mandatory.
- Customer name is optional.
- Existing customer profiles should be reused (search by phone).
- New customers can be created during sales.
- Physical sales must be paid in full.
- No customer credit or partial-payment facility for physical POS sales.
- One sale can use multiple payment methods (cash + bKash, etc.).

### 8.8 Offline POS mode

POS devices operate offline when internet is unavailable:
- Local cache contains product/design data, prices, and stock availability snapshot.
- Sales recorded offline are queued locally.
- When internet returns: auto-sync uploads offline sales and refreshes cache.
- Alternative: manual sync button to push and pull data.
- After successful sync, stale local cache data is cleared/refreshed to prevent duplicates.

**Offline scope:**
- Offline mode supports POS sales and customer lookup only.
- Transfers, receiving, stock counts, refunds, and adjustments require online connection.

**Conflict handling:**
- If a garment was sold both offline and online (or on two offline devices) before sync, the system flags the conflict on reconnection and escalates to Main Branch Admin for resolution.
- Online reservations against garments at an offline branch are not visible to the offline device; conflicts are flagged on reconnection.
- Cache is per-device, per-branch.

---

## 9. Customer Management

### 9.1 Customer registration

Salespeople and cashiers can create customer records during sales.

They can:
- Search and select existing customers (by phone number).
- Create new customers during sales.
- Link sales, returns, and exchanges to customer records.

### 9.2 Customer record fields

- **Phone number** — mandatory, primary unique identifier, single phone per customer (no multi-phone support).
- **Name** — optional.
- **Delivery address** — for online customers.

### 9.3 Customer record administration

Only Main Branch Admin can:
- Edit existing customer information.
- Merge duplicate customer records.

Customer history must remain linked and auditable.

### 9.4 Returns without printed receipt

A return may be accepted without a printed receipt if the original sale can be verified using garment barcode or customer phone number.

---

## 10. Online Sales and Order Management

### 10.1 Online order workflow

1. Customer places an online order (may contain multiple garments from multiple branches).
2. Order enters Pending Confirmation.
3. Online staff verifies order and customer details.
4. Online staff checks stock availability across all branches.
5. Online staff creates transfer request(s) from source branch(es) to the online channel.
6. Creating the transfer request implicitly reserves the garments at the source branch(es).
7. Source branch supervisor approves and dispatches.
8. Online channel receives the garment(s).
9. Once all garments for the order are received, online staff dispatches to customer via courier.
10. Order progresses through dispatch and delivery statuses.
11. Payment and courier settlement are tracked independently.

### 10.2 Multi-branch sourcing

- One online order can contain garments sourced from multiple branches.
- Each order line reserves its specific garment at its own source branch via a transfer request.
- Each source branch prepares and dispatches its portion independently.
- Order status is composite — "fully dispatched" only when all lines are dispatched.
- Payment is collected once for the whole order (advance or COD).
- Returns/refunds can be per-line (customer returns one garment from a multi-garment order).

### 10.3 Reservation model

- Reservation occurs when the transfer request is created (after staff confirms the order).
- The transfer request implicitly reserves the garments at the source branch.
- Reserved garments cannot be sold through POS or another online order.
- No manual reservation override.
- Reservation is released when:
  - The transfer is received at the online channel (garment moves to online channel stock).
  - The transfer is cancelled/rejected (garment returns to available at source branch).
  - The order is cancelled before transfer approval.

### 10.4 Reservation expiry

- **No automatic expiry.** Reservation release is manual.
- Each reservation is managed based on the commitment made to the customer.
- The system displays the count of currently-reserved garments per branch so staff can manage them.
- Authorized staff (Online Sales Supervisor, Main Branch Admin) can manually release reservations.
- Reservation creation, release, and manual actions must be auditable.

### 10.5 Online payments

Supported payment models:
- **Advance payment** — full advance only. No partial advance payments.
- **Cash on delivery (COD).**

Payment status is separate from order status.

COD is not treated as received business cash until courier settlement is reconciled and recorded by the Online Sales Supervisor.

### 10.6 Courier handling

- Courier companies are not pre-configured in the system.
- Online staff enter courier name and tracking number as free-text notes on the order.
- No courier master data or API integration in the initial release.
- COD reconciliation is performed by the Online Sales Supervisor against order records.

### 10.7 Online cancellation

- Online Sales Team members can cancel eligible orders.
- Before transfer dispatch, cancellation releases the reservation and the garment returns to available stock.
- After transfer dispatch, the garment follows the physical return workflow back to the source branch.
- After courier dispatch, returned garments follow the inspection workflow.
- Cancellation cannot erase the original order or payment history.

### 10.8 Online customer access

Online Sales Team members and Online Sales Supervisor can view:
- Customer name.
- Phone number.
- Delivery address.
- Previous orders.
- Returns and exchanges.

They cannot access purchase costs, profit, supplier information, or restricted financial information (other than online-channel financial data for the supervisor).

---

## 11. Returns, Exchanges, and Refunds

### 11.1 Return eligibility

- **Physical sales:** Only the original selling branch can accept the return.
- **Online sales:** Returns come back to the online channel.
- **Return period:** 7 days for non-faulty dresses.
- **Defect claim period:** 7 days.
- The policy applicable at the original sale must be preserved for historical eligibility.

### 11.2 Return verification

- A return may be accepted without a printed receipt if the original sale is verified via garment barcode or customer phone number.
- The system must verify the original sale and apply the applicable return policy.
- The system must prevent duplicate returns against the same sale.

### 11.3 Refund approval

**Physical sales:**
- Salespeople and cashiers can initiate refund requests.
- Branch Supervisor approves refunds within a management-configured limit.
- Higher-value refunds require Main Branch Admin approval.

**Online sales:**
- Only Online Sales Team members can initiate online refund requests.
- Online Sales Supervisor approves within the same configurable limit.
- Main Branch Admin approves higher-value refunds.
- Online Sales Supervisor cannot approve physical branch refunds.

### 11.4 Online return flow

1. Customer returns the dress to the online channel (ships back or drops off).
2. Online staff receives the returned garment.
3. Two refund paths:
   - **Path A — Online channel has cash:** Online staff refunds the customer immediately from online channel cash, then transfers the garment back to the original physical branch that held it.
   - **Path B — Online channel does not have cash:** Online staff transfers the garment to the original physical branch; the original branch processes the refund and sends the money to the online channel.
4. The original physical branch is always tracked per garment so returns route correctly.
5. If the garment is defective, it follows the defective-garment flow to Main Branch for inspection.

### 11.5 Refund methods

All refund methods permitted:
- Cash.
- Bank transfer.
- bKash.
- Nagad.
- Additional configured methods.

### 11.6 Defective garments

- Defective garments go to Main Branch for inspection.
- Branch Supervisors handle branch-level defective inspection for customer returns.
- Main Branch Admin handles inspection for supplier returns.
- Main Branch Admin makes the final disposition decision.
- If the supplier rejects a defective garment, it remains in a separate damaged-stock area until management decides.
- Damaged garments cannot be sold or offered online.

---

## 12. Cash and Payment Management

### 12.1 Cash account structure

- Each salesperson has an individual cash balance.
- Each branch has a consolidated cash account managed by its supervisor.
- The online channel has its own cash account managed by the Online Sales Supervisor (branch-equivalent).
- Cash handovers require independent confirmation by salesperson and supervisor.
- Mismatches remain recorded as discrepancies.

### 12.2 Cash closing

- System calculates expected cash based on recorded transactions.
- Authorized staff enter physically counted cash.
- Differences are recorded.
- Branch Supervisor records the factual count and difference at closing.
- Main Branch Admin takes over all investigation and resolution of discrepancies.
- Main Branch Admin can review all closings and historical records.
- Routine daily closing does not require Main Branch Admin approval.

### 12.3 Payment methods

Configurable methods include:
- Cash.
- bKash.
- Nagad.
- Bank transfer.
- Debit/credit card.
- Additional methods as configured.

One sale can use multiple payment methods. Each payment entry records amount, method, reference, and time.

### 12.4 Cash discrepancy resolution

- Branch Supervisor performs the daily cash closing, records the physically counted cash, and the system records the difference between expected and counted amounts.
- The supervisor's responsibility ends at recording the factual count and difference.
- Main Branch Admin takes over all investigation and financial resolution.
- No automatic salary deduction.
- Original cash collection and handover records cannot be overwritten.

---

## 13. Courier and COD Reconciliation

### 13.1 Operational responsibility

Online Sales Team can enter:
- Courier tracking information (free-text notes).
- Dispatch updates.
- Delivery status.
- Operational delivery information.

### 13.2 Financial responsibility

Online Sales Supervisor handles all online-channel financial operations:
- Records courier settlements.
- Reconciles COD statements.
- Matches tracking numbers and orders.
- Verifies courier charges, deductions, and net payments.
- Confirms financial receipt.
- Receives advance payments.
- Performs online-channel fund collection.

Main Branch Admin receives the end-of-day consolidated cashbook report covering all branches including the online channel, same as for any physical branch. Admin still approves refunds above the configurable limit.

### 13.3 Reconciliation controls

Flag:
- Missing settlements.
- Unmatched settlements.
- Short payments.
- Excess payments.
- Duplicate entries.

COD becomes received business cash only after settlement verification by the Online Sales Supervisor.

---

## 14. Employee Roles and Permissions

### 14.1 Access model

- Customizable role-based access control with predefined roles.
- An employee can hold multiple roles simultaneously.
- Multi-role employees **switch between roles** in the UI; the currently-active role determines the permission scope.
- Authorization checks use the active role, not the union of all assigned roles.
- Branch scope and sensitive-data restrictions always apply regardless of active role.
- Combining roles must not bypass restrictions.
- **All roles can sell, including Supervisors and Main Branch Admin.** Supervision and admin functions are additional permission layers on top of base selling capability, not a replacement for it.

### 14.2 Main roles (5 roles)

| Role | Sells? | Additional authority |
|---|---|---|
| Main Branch Admin | Yes | Full business authority; supplier payments; supplier-return inspection; inventory adjustment approval; end-of-day consolidated cashbook review; employee account/permission management; all-branch all-date reporting |
| Branch Supervisor | Yes | Branch operations; transfer request and approval for own branch; cash management and daily closing (records factual count/difference); branch-level defective-garment inspection for customer returns; refund approval within configured limit; own-branch today-only operational reporting |
| Salesperson | Yes | Base selling; customer lookup and creation; individual cash collection and handover; own current-day activity view only |
| Online Sales Team | Yes (online channel) | Online order placement and management; online refund initiation; customer service; courier operational tracking; own current-day activity view only |
| Online Sales Supervisor | Yes (online channel) | Full online-channel authority equivalent to Branch Supervisor: online refund approval within limit; courier settlement and COD reconciliation; advance payment receipt; fund collection; daily online cash closing; today-only online operational reporting |

**Eliminated roles** (merged for simplicity):
- Cashier → merged into Salesperson.
- Stock Manager → merged into Supervisor.
- Inspection Staff → eliminated; branch-level inspection by Supervisor, supplier-return inspection by Admin.

### 14.3 Employee account administration

Only Main Branch Admin can:
- Create accounts.
- Edit accounts.
- Activate/deactivate accounts.
- Assign, change, and remove roles.
- Customize permissions.
- Reset passwords and require change at next login.
- Unlock locked accounts.

Branch Supervisors cannot directly manage employee accounts or permissions.

### 14.4 Branch assignment

- Each physical-branch employee belongs to exactly one assigned branch.
- Only Main Branch Admin can change branch assignment.
- Employees cannot temporarily work or perform transactions at another branch.
- Historical transactions retain their original branch.
- Pending responsibilities must be reviewed before branch reassignment.
- Online Sales Team requires a non-stock-holding channel assignment (online channel).

### 14.5 Login security

- Username and password authentication.
- Unique username across the business.
- Secure password hashing.
- No shared employee accounts.
- Employees can change their own passwords.
- Five consecutive failed attempts lock an account.
- Only Main Branch Admin can unlock it.
- Successful login resets the consecutive-failure counter.
- Login attempts, lockouts, and unlocks are logged.

### 14.6 Session policy

- No automatic inactivity logout. Employees remain logged in until manual logout.
- This decision must be revisited during security review, especially for shared devices.

---

## 15. Employee Activity and Reporting Permissions

### 15.1 Search vs. report boundary

**Transactional search (allowed any date, all roles):**
- Looking up a specific customer by name, phone, or invoice number.
- Looking up a specific garment by barcode or serial.
- Verifying an original sale for return processing.
- Any single-record lookup for operational purposes.

**Aggregate/date-wise reports (today only for non-admin roles):**
- Date-wise total sales.
- Cashbook.
- Salesperson performance summaries.
- Customer lists.
- Supplier lists.
- Branch-wide performance reports.

Main Branch Admin retains all-branch, all-date access to both transactional search and aggregate reports.

### 15.2 Salesperson

- Own current-business-day activity only.
- Can search for specific customers, sales, and garments by name, phone, invoice number, barcode, or serial number for any date for transactional purposes.
- Cannot run date-wise aggregate reports for any date other than today.
- Cannot view branch-wide performance reports.

### 15.3 Branch Supervisor

- Own branch operational reports — current business day only.
- Can perform transactional search any date (for returns, service).
- Today-only restriction applies to aggregate/operational reports only.
- Can view today's individual employee performance.
- Cannot view another branch's employee activity.
- Cannot view historical date-range reports.

### 15.4 Online Sales Supervisor

- Today's online operational activity.
- Today's individual online employee performance.
- Can perform transactional search any date.
- No historical aggregate reports.
- No restricted financial reports (other than online-channel financials).

### 15.5 Main Branch Admin

- All employees across all branches.
- Current and historical activity.
- Business-wide operational and financial reports.

### 15.6 Performance metrics

Permitted daily metrics include:
- Sales count.
- Total sales amount.
- Garments sold.
- Discounts.
- Payment breakdown.
- Cash collection and handover status.

Purchase cost and profit remain restricted to Main Branch Admin.

---

## 16. Stock Counts, Discrepancies, and Adjustments

### 16.1 Stock counting

Support:
- Scheduled full-branch stock counts.
- Selective counts by design, size, or group.
- Management-initiated ad hoc counts.

Store expected quantity, physical quantity, discrepancy, staff, and date.

A stock count does not automatically change official inventory.

### 16.2 Discrepancy approval

- Branch Supervisor verifies and submits discrepancies.
- Only Main Branch Admin approves or rejects official stock adjustments.
- Preserve original quantity, proposed quantity, reason, evidence, decision, approver, and timestamp.

### 16.3 Mandatory reasons

Every shortage or excess requires a reason. Supporting evidence is optional.

Examples:
- Misplaced garment.
- Unrecorded transfer.
- Damage.
- Suspected theft/loss.
- Incorrect entry.
- Barcode/counting error.
- Other, with explanation.

A discrepancy is an investigation signal, not proof of misconduct.

### 16.4 Misplaced or unidentified garments

- Staff scan the barcode.
- System displays recorded identity, status, and location.
- Staff create a misplaced/unidentified garment report.
- Garment enters a temporary investigation status.
- Main Branch Admin investigates and approves or rejects location correction.
- The same garment must never appear available in two branches.

### 16.5 Damaged garments

- Staff report damage.
- System immediately blocks normal sale and online availability.
- Branch Supervisor verifies and submits findings.
- Main Branch Admin decides: repair, supplier return, permitted clearance, damaged stock, or disposal/write-off.
- Blocking happens immediately; it must not wait for the final management decision.

### 16.6 Missing garment recovery

- Staff locate and scan the garment.
- Supervisor verifies identity and condition.
- Main Branch Admin approves or rejects restoration.
- Original missing report remains linked to recovery.
- History is never erased.

### 16.7 Stock freeze

Management can select:
- Full-branch freeze.
- Selective freeze by products, sizes, or garments.

During a freeze, affected stock transactions are blocked. Existing committed transactions remain preserved. The system must handle in-progress transactions so they cannot bypass a freeze.

Record initiator, scope, start time, release time, and reason.

### 16.8 Inventory alerts

Configurable alerts include:
- Individual discrepancies.
- Transfer receiving discrepancies.
- Damaged or missing garments.
- Unusual adjustments.
- Repeated shortages by branch or employee.
- Repeated transfer shortages.
- High damage reports.
- Repeated adjustments.

Alerts indicate cases for investigation, not proof of wrongdoing.

### 16.9 Stock replenishment

No minimum-stock thresholds or automatic low-stock alerts in the initial release.

Management checks branch-wise reports and initiates transfers manually.

### 16.10 Garment metadata corrections

- Basic corrections may be handled by Branch Supervisors within authorized scope.
- Identity-related changes require Main Branch Admin approval.
- Internal garment ID is immutable.
- Permanent barcode is never reused.
- Serial history is preserved.
- All changes are audited.

---

## 17. Reservation and Concurrency Integrity

- A garment reserved by a transfer request cannot be sold through POS.
- Online cannot reserve a garment already reserved.
- No manual reservation override.
- Reservation release follows the transfer cancellation or manual release workflow.
- Checkout and reservation use atomic database transactions and concurrency protection.
- Stock must be revalidated before commitment.
- A garment can belong to only one active physical inventory location or reservation state at a time.

---

## 18. Expense Management

- Branch Supervisors can submit expenses for their own branch.
- Online Sales Supervisor can submit expenses for the online channel.
- Only Main Branch Admin approves, rejects, and finalizes expenses.
- Expense records include amount, category, date, description, branch, submitter, and optional evidence.
- Approved expenses cannot be silently changed or deleted.
- Corrections preserve audit history.

---

## 19. Supplier Payments

Only Main Branch Admin can:
- Record supplier payments.
- Edit entries before confirmation.
- Confirm and finalize payments.
- Review supplier payment history and outstanding balances.

Payments should link to supplier and purchase records where applicable. Duplicate payment entries must be detected and flagged.

Confirmed payments cannot be silently edited or deleted.

---

## 20. Attendance and Employee Management Scope

### 20.1 Physical branch attendance

- Branch Supervisors manually record attendance for employees assigned to their own branch.
- No employee self-check-in/check-out.
- No GPS or biometric integration in the initial release.
- Main Branch Admin can view and manage attendance across all branches.
- Corrections preserve original values and audit details.

### 20.2 Leave management

No leave-request or leave-management module in the initial release.

### 20.3 Payroll and salary

Only Main Branch Admin can view and manage salary and payroll records.

Employees and Branch Supervisors cannot view salary information through the ERP.

### 20.4 Excluded employee modules

The initial release does not include:
- Employee loans or salary advances.
- Employee commissions or incentives.
- Disciplinary or warning management.
- Shift scheduling or shift-to-shift handovers.
- Online Sales Team attendance.
- Formal leave management.

---

## 21. Audit and Data Integrity

All important operations must be traceable.

Audit records should include:
- Actor/user.
- Branch or channel scope.
- Timestamp.
- Action.
- Affected record.
- Previous value where applicable.
- New value where applicable.
- Reason or supporting evidence where relevant.

Audit logs are append-only and protected from ordinary modification.

### 21.1 Audit-log retention

- **Business audit logs** (sales, returns, transfers, cash, adjustments, permissions, approvals, corrections, sensitive-data access) — retained long-term (multi-year) for compliance and investigation.
- **Login attempt logs** (successful, failed, lockout, unlock) — retained for 90 days.
- Only meaningful business events are logged; unnecessary noise (UI reads, page views) is not logged.

### 21.2 Audit visibility

**Main Branch Admin:**
- All branches.
- All historical dates.
- All permitted audit categories.

**Branch Supervisor:**
- Own branch only.
- Current business day only.
- Cannot edit or delete logs.

**Other employees:**
- No direct audit-log access in the initial release.

### 21.3 Financial and inventory integrity

- Completed transactions cannot be silently deleted.
- Corrections use controlled reversal or adjustment workflows.
- Inventory balances must reconcile with transaction history.
- Payment and stock changes must be atomic where they form one business transaction.
- Historical records must remain attributable to their original branch and employee.
- Role-based access restrictions must not be used to falsify, conceal, or delete legally required business records.

---

## 22. Financial Scope

The initial ERP includes basic financial operations:
- Sales and payment recording.
- Cash collection and handover.
- Branch cash closing.
- Expense records and approval.
- Supplier payments.
- Courier COD settlement.
- Basic outstanding and payment tracking.

This is not intended to be a full double-entry accounting system in the initial release.

The system must maintain accurate transaction records and must not falsify, conceal, or delete legally required business records.

---

## 23. Reporting

Reports must respect role, branch, date, and sensitive-data restrictions.

### 23.1 Main Branch Admin
- All-branch current and historical reports.
- Sales, expenses, cash, supplier payments, and courier settlement reports.
- Purchase costs and other authorized financial reports.
- All employee activity and performance.
- Inventory, transfer, count, discrepancy, and adjustment reports.

### 23.2 Branch Supervisor
- Own branch operational reports.
- Current business day only (aggregate).
- Transactional search any date.
- Employee performance for today.
- No purchase cost, supplier payment, profit, or business-wide financial reports.

### 23.3 Salesperson
- Own current-day activity only.
- No branch-wide performance reports.
- No historical date-range aggregate reports.
- Transactional search any date.

### 23.4 Online Sales Supervisor
- Today's online operational activity.
- Today's individual online employee performance.
- Online-channel financial reports.
- No historical aggregate reports.
- No restricted financial reports.

These restrictions must apply to dashboards, exports, and APIs.

---

## 24. Technical and Non-Functional Requirements

### 24.1 Proposed architecture

- Laravel modular monolith.
- PostgreSQL relational database.
- Centralized business rules and authorization.
- API-ready architecture for future integrations.
- Responsive browser-based interface.
- Offline-capable POS.

Avoid premature microservices and unnecessary complexity.

### 24.2 Data integrity

- Database-level unique constraints for garment IDs, barcodes, and fiscal-year serial combinations.
- Transactions for sales, reservations, payments, and stock movements.
- Row-level locking or equivalent concurrency control.
- Immutable transaction history.
- Auditable corrections.

### 24.3 Performance and scalability

Design for future growth, including:
- 100 or more branches.
- 10,000+ products/designs.
- Millions of garment movements and sales lines.
- Multi-year historical records.

Use appropriate indexes, summary projections, and queued/chunked exports where necessary.

### 24.4 Security

- Server-side authorization on every protected operation.
- Branch-scoped access enforcement.
- Sensitive-data restrictions independent of role combinations.
- Secure password hashing.
- Login lockout.
- Audit logging.
- No shared accounts.

### 24.5 Maintainability

- Modular business domains.
- Clear service boundaries.
- Documented business workflows.
- Automated tests for inventory, payment, permissions, and concurrency.
- No direct database manipulation that bypasses business rules.

### 24.6 Barcode label hardware

- Hang tags: 50 x 30 mm or 75 x 50 mm.
- Adhesive stickers: 1" x 1.5" to 1" x 2.625".
- Supported printers: Xprinter XP-420B, G-Printer GP-3120TUC, Rongta RP400H.

### 24.7 Backup and disaster recovery

- Weekly automatic backup, stored on the server (client decision).
- **Recommendation:** Daily backup with offsite copy for retail business continuity. To be reviewed during deployment design.
- PostgreSQL continuous WAL archiving recommended for point-in-time recovery.

---

## 25. Confirmed Decisions Register

This document consolidates confirmed decisions from Discovery Rounds 1-5.

### Key confirmed policies

1. All purchased garments are first received at Main Branch.
2. Only Main Branch communicates with suppliers.
3. Each physical garment has a unique permanent barcode and internal ID.
4. Fiscal-year serials restart each July. Format: `YY-NNNNN` (e.g., `25-00001`). Global sequence per fiscal year.
5. Branch transfers require source-side approval and destination-side receipt confirmation.
6. Transfer requests implicitly reserve garments at the source branch.
7. Online orders trigger transfer requests from source branches to the online channel; online channel receives then dispatches.
8. Physical POS sales are fully paid; no credit.
9. Cross-branch returns are not allowed; returns go to the original selling branch (or online channel for online sales).
10. Main Branch Admin controls pricing, purchasing, products, accounts, permissions, supplier payments, and business settings.
11. Branch Supervisors control their own branch's operational activities within the defined authority.
12. Main Branch Admin alone approves official inventory adjustments.
13. Branch operational and audit reporting is limited to the current business day (aggregate); transactional search is any date.
14. Only Main Branch Admin has all-branch historical and financial reporting access.
15. Online Sales Supervisor has current-day operational supervision and full online-channel financial authority (branch-equivalent).
16. No employee shift, leave, loan, commission, disciplinary, or online attendance module in the initial release.
17. All roles can sell, including supervisors and admin.
18. 5 roles: Main Branch Admin, Branch Supervisor, Salesperson, Online Sales Team, Online Sales Supervisor.
19. Multi-role employees switch between roles; active role determines scope.
20. No sell-here-pick-there; demand-request transfer required for cross-branch stock.
21. Return period: 7 days for non-faulty, 7 days for defect claims.
22. No partial advance payments; full advance or COD online only.
23. No automatic reservation expiry; manual release with reservation count display.
24. Price set by admin; no tiered/quantity pricing.
25. Couriers are free-text notes, not master data.
26. No tax line on invoice (legal review pending).
27. Single phone per customer; phone is primary unique identifier.
28. All refund methods permitted (cash, bank, bKash, Nagad).
29. Offline POS mode with local cache and auto-sync on reconnection.
30. Multiple devices per branch simultaneously.
31. Online channel has its own cash account (branch-equivalent).
32. Cash discrepancy: supervisor records factual count/difference; admin investigates and resolves.
33. Meaningful business audit logs retained long-term; login attempt logs retained 90 days.
34. Weekly on-server backup (daily offsite recommended).

---

## 26. Implementation Readiness

**Status: Requirements discovery complete. Pending client formal approval before development.**

All architectural decisions are made. Remaining items are configuration values and design-phase artifacts (detailed role-permission matrix, cash closing edge cases, expense categories, report definitions, no-auto-logout security review, final acceptance criteria).

Before development begins:
1. Client formal approval of this BRD.
2. Produce design artifacts (architecture, database design, workflows, security design, performance strategy, API documentation, testing strategy, deployment guide, architecture decisions, development roadmap).
3. Review and approve the technical design.
4. Begin implementation in controlled, testable modules.

### 26.1 Core implementation principle

The system must preserve the identity and history of every physical garment and every completed business transaction.

Stock, payment, reservation, transfer, stock count, adjustment, damage, and return workflows must be designed around data integrity, traceability, authorization, and concurrency safety.

The objective is not merely to build a working POS or inventory application, but to create a reliable long-term operational platform for the business.

---

*End of Business Requirements Document v1.6*
