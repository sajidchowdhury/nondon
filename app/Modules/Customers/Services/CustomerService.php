<?php

namespace App\Modules\Customers\Services;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    /**
     * Normalize a phone number: trim, strip spaces/dashes, strip the leading
     * country code variants for Bangladesh (88, +880, 880) down to a 0-prefixed
     * local number. Duplicate detection must compare normalized phones so that
     * "01712 345678" and "+8801712345678" are recognized as the same customer.
     */
    public function normalizePhone(string $phone): string
    {
        $phone = trim($phone);
        // Strip spaces, dashes, parentheses, dots.
        $phone = preg_replace('/[\s\-().]/', '', $phone);

        // Normalize Bangladesh country-code variants to local 0-prefix.
        if (str_starts_with($phone, '+880')) {
            $phone = '0'.substr($phone, 4);
        } elseif (str_starts_with($phone, '880')) {
            $phone = '0'.substr($phone, 3);
        }

        return $phone;
    }

    /**
     * Create a new customer.
     *
     * Phone is mandatory and unique (after normalization). If a customer with
     * the same normalized phone already exists, throws a ValidationException
     * whose error payload includes the existing customer's id — so the UI can
     * offer "reuse existing" instead of forcing the user to search again.
     *
     * @param  array  $data  {
     *                       - phone: string (required)
     *                       - name: string|null
     *                       - address: string|null
     *                       - is_online_customer: bool (inferred from creator if absent)
     *                       }
     *
     * @throws ValidationException if phone is duplicate
     */
    public function create(array $data, User $creator): Customer
    {
        $phone = $this->normalizePhone((string) ($data['phone'] ?? ''));

        if ($phone === '') {
            throw ValidationException::withMessages([
                'phone' => __('Customer phone is required.'),
            ]);
        }

        // Duplicate detection — return the existing id in the error so the
        // POS UI can offer to reuse the existing customer instead of failing.
        $existing = Customer::where('phone', $phone)->first();
        if ($existing !== null) {
            throw ValidationException::withMessages([
                'phone' => __('A customer with this phone already exists.'),
                'existing_customer_id' => (string) $existing->id,
            ]);
        }

        // Infer online-customer flag from the creator when not provided:
        // online team / online supervisor create online-channel customers.
        $isOnline = array_key_exists('is_online_customer', $data)
            ? (bool) $data['is_online_customer']
            : $creator->is_online_channel;

        return DB::transaction(function () use ($phone, $data, $creator, $isOnline) {
            return Customer::create([
                'phone' => $phone,
                'name' => $data['name'] ?? null,
                'address' => $data['address'] ?? null,
                'is_online_customer' => $isOnline,
                'is_active' => true,
                'merged_into_id' => null,
                'created_by' => $creator->id,
            ]);
        });
    }

    /**
     * Find a customer by phone, or create a new one if none exists.
     *
     * This is the POS helper (WF-3 step 2): the salesperson enters a phone;
     * the system returns the matching customer or creates a walk-in. If the
     * existing customer was merged (inactive), returns the primary it was
     * merged into — never the duplicate.
     */
    public function findOrCreateByPhone(string $phone, User $creator, ?string $name = null): Customer
    {
        $normalized = $this->normalizePhone($phone);

        $customer = Customer::where('phone', $normalized)->first();

        // If the matching record was merged, resolve to the primary.
        if ($customer && $customer->isMerged()) {
            $customer = Customer::find($customer->merged_into_id);
        }

        if ($customer) {
            return $customer;
        }

        return $this->create(['phone' => $normalized, 'name' => $name], $creator);
    }

    /**
     * Update customer details. Admin only (enforced by CustomerPolicy::update,
     * but double-checked here so the service is safe to call directly).
     *
     * @throws ValidationException if phone is being changed to a duplicate
     * @throws AuthorizationException if the actor lacks customer.edit
     */
    public function update(Customer $customer, array $data, User $actor): Customer
    {
        if (! $actor->can('update', $customer)) {
            throw new AuthorizationException(__('Only Main Branch Admin can edit customer details.'));
        }

        if (isset($data['phone'])) {
            $newPhone = $this->normalizePhone($data['phone']);
            if ($newPhone !== $customer->phone) {
                if (Customer::where('phone', $newPhone)->where('id', '!=', $customer->id)->exists()) {
                    throw ValidationException::withMessages([
                        'phone' => __('A customer with this phone already exists.'),
                    ]);
                }
                $data['phone'] = $newPhone;
            } else {
                unset($data['phone']);
            }
        }

        return DB::transaction(function () use ($customer, $data) {
            $customer->update([
                'phone' => $data['phone'] ?? $customer->phone,
                'name' => array_key_exists('name', $data) ? $data['name'] : $customer->name,
                'address' => array_key_exists('address', $data) ? $data['address'] : $customer->address,
            ]);

            return $customer->fresh();
        });
    }

    /**
     * Deactivate a customer (admin only). Customers are never deleted —
     * deactivation removes them from POS lookup. Used outside the merge flow;
     * the merge flow (Session 3.10) sets merged_into_id alongside is_active.
     */
    public function deactivate(Customer $customer, User $actor): Customer
    {
        if (! $actor->can('update', $customer)) {
            throw new AuthorizationException(__('Only Main Branch Admin can deactivate a customer.'));
        }

        return DB::transaction(function () use ($customer) {
            $customer->update(['is_active' => false]);

            return $customer->fresh();
        });
    }

    /**
     * Search customers by phone or name, applying PII scope for the viewer.
     *
     * PII scope (Security §4.2, §7.4):
     *  - main_branch_admin: all customers.
     *  - branch_supervisor / salesperson: customers that have at least one
     *    sale at the viewer's branch, OR walk-in customers with no sales yet
     *    (they may be served at the branch). Online-only customers are hidden
     *    unless the supervisor is on the online channel.
     *  - online_team / online_supervisor: only is_online_customer customers.
     *
     * Merged (inactive) customers are excluded from results unless the viewer
     * is admin and explicitly requests them via $includeMerged.
     */
    public function search(string $term, User $viewer, bool $includeMerged = false): Collection
    {
        $query = Customer::query();

        if ($term !== '') {
            $query->search($term);
        }

        // PII scope
        $this->applyPiiScope($query, $viewer);

        if (! $includeMerged || ! $viewer->hasRole('main_branch_admin')) {
            $query->active();
        }

        return $query->orderBy('phone')->limit(50)->get();
    }

    /**
     * Fetch a single customer, enforcing PII scope. Throws AuthorizationException
     * if the viewer may not see this customer.
     */
    public function get(Customer $customer, User $viewer): Customer
    {
        if (! $viewer->can('view', $customer)) {
            throw new AuthorizationException(__('You do not have permission to view this customer.'));
        }

        return $customer;
    }

    /**
     * Paginated customer list for the admin UI (25 per page per doc 06 §6.3).
     */
    public function getPaginatedList(array $filters, User $viewer): LengthAwarePaginator
    {
        $query = Customer::query();

        if (! empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Admin may opt to see merged customers.
        $includeMerged = ! empty($filters['include_merged']) && $viewer->hasRole('main_branch_admin');
        if (! $includeMerged) {
            $query->active();
        }

        if (($filters['type'] ?? null) === 'online') {
            $query->onlineCustomers();
        } elseif (($filters['type'] ?? null) === 'walkin') {
            $query->walkIn();
        }

        return $query->orderByDesc('id')->paginate($filters['per_page'] ?? 25);
    }

    /**
     * Purchase history for a customer.
     *
     * STUB (Phase 3 Session 3.1): returns an empty collection. Fully
     * implemented in Session 3.10 once the sale_invoices table exists and the
     * Customer → sale_invoices relationship is wired up. Kept here so the
     * CustomerView component can call it now and render a placeholder.
     */
    public function getPurchaseHistory(Customer $customer, User $viewer): Collection
    {
        // @todo Phase 3.10 — join to sale_invoices once Sales module lands.
        return new Collection;
    }

    /*
    |--------------------------------------------------------------------------
    | PII Scope
    |--------------------------------------------------------------------------
    */

    /**
     * Apply the viewer's PII scope to a customer query.
     *
     * Customers are not branch-owned, so this is enforced in the query (and
     * in CustomerPolicy::view), not via an Eloquent global scope.
     *
     *  - admin: no restriction.
     *  - online_team / online_supervisor: is_online_customer = true.
     *  - branch_supervisor / salesperson: walk-in customers OR customers with
     *    a sale at their branch. The "sale at their branch" check requires the
     *    sale_invoices table (Session 3.2); until then, branch roles see all
     *    walk-in customers plus any customer they could plausibly serve. Once
     *    sales exist, this is tightened to a branch join.
     */
    protected function applyPiiScope($query, User $viewer): void
    {
        if ($viewer->hasRole('main_branch_admin')) {
            return; // no restriction
        }

        if ($viewer->hasRole(['online_team', 'online_supervisor'])) {
            $query->onlineCustomers();

            return;
        }

        // branch_supervisor / salesperson: walk-in customers with no online
        // flag. Online-only customers are NOT visible to physical-branch
        // staff (Security §7.4: online customers are the online team's PII).
        $query->walkIn();
    }
}
