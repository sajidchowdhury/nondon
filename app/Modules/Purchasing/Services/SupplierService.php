<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Purchasing\Models\Supplier;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupplierService
{
    /**
     * Create a new supplier.
     *
     * @param array $data {
     *   - code: string (required, unique)
     *   - name: string (required)
     *   - contact_person: string|null
     *   - phone: string|null
     *   - email: string|null
     *   - address: string|null
     * }
     * @return Supplier
     * @throws ValidationException
     */
    public function create(array $data): Supplier
    {
        // Validate unique code
        if (Supplier::where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => __('Supplier code already exists.'),
            ]);
        }

        return DB::transaction(function () use ($data) {
            return Supplier::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'contact_person' => $data['contact_person'] ?? null,
                'phone' => $data['phone'] ?? null,
                'email' => $data['email'] ?? null,
                'address' => $data['address'] ?? null,
                'outstanding_balance' => 0,
                'is_active' => true,
                'created_by' => Auth::id(),
            ]);
        });
    }

    /**
     * Update supplier details.
     *
     * @param Supplier $supplier
     * @param array $data
     * @return Supplier
     * @throws ValidationException
     */
    public function update(Supplier $supplier, array $data): Supplier
    {
        // Validate unique code if changing
        if (isset($data['code']) && $data['code'] !== $supplier->code) {
            if (Supplier::where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages([
                    'code' => __('Supplier code already exists.'),
                ]);
            }
        }

        return DB::transaction(function () use ($supplier, $data) {
            $supplier->update([
                'code' => $data['code'] ?? $supplier->code,
                'name' => $data['name'] ?? $supplier->name,
                'contact_person' => array_key_exists('contact_person', $data) ? $data['contact_person'] : $supplier->contact_person,
                'phone' => array_key_exists('phone', $data) ? $data['phone'] : $supplier->phone,
                'email' => array_key_exists('email', $data) ? $data['email'] : $supplier->email,
                'address' => array_key_exists('address', $data) ? $data['address'] : $supplier->address,
            ]);

            return $supplier->fresh();
        });
    }

    /**
     * Activate a supplier.
     */
    public function activate(Supplier $supplier): Supplier
    {
        return $supplier->activate();
    }

    /**
     * Deactivate a supplier.
     *
     * Refuses if the supplier has a non-zero outstanding balance.
     *
     * @param Supplier $supplier
     * @return Supplier
     * @throws ValidationException
     */
    public function deactivate(Supplier $supplier): Supplier
    {
        return $supplier->deactivate();
    }

    /**
     * Search suppliers by code, name, or phone.
     *
     * @return Collection
     */
    public function search(string $term): Collection
    {
        return Supplier::search($term)
            ->orderBy('name')
            ->get();
    }

    /**
     * Get a supplier's outstanding balance (read-only in Phase 2).
     */
    public function getOutstandingBalance(Supplier $supplier): float
    {
        return (float) $supplier->outstanding_balance;
    }

    /**
     * Get a paginated list of suppliers with filters.
     *
     * @param array $filters {
     *   - search: string
     *   - status: string ('active', 'inactive')
     *   - per_page: int (default 25)
     * }
     */
    public function getPaginatedList(array $filters = []): LengthAwarePaginator
    {
        $query = Supplier::query();

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['status'])) {
            if ($filters['status'] === 'active') {
                $query->active();
            } elseif ($filters['status'] === 'inactive') {
                $query->inactive();
            }
        }

        return $query->orderBy('name')
            ->paginate($filters['per_page'] ?? 25);
    }
}
