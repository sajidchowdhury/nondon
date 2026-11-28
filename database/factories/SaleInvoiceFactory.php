<?php

namespace Database\Factories;

use App\Modules\Customers\Models\Customer;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use App\Modules\Organization\Models\FiscalYear;
use App\Modules\Sales\Models\SaleInvoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleInvoice>
 */
class SaleInvoiceFactory extends Factory
{
    protected $model = SaleInvoice::class;

    public function definition(): array
    {
        $fiscalYear = FiscalYear::active()->first() ?? FiscalYear::factory()->create();
        $branch = Branch::where('code', 'M')->first() ?? Branch::factory()->create();
        $salesperson = User::factory()->forBranch($branch)->create();

        return [
            'invoice_number' => 'INV-'.$fiscalYear->serial_prefix.'-'.str_pad((string) fake()->unique()->numberBetween(1, 99999), 5, '0', STR_PAD_LEFT),
            'branch_id' => $branch->id,
            'salesperson_id' => $salesperson->id,
            'customer_id' => null,
            'fiscal_year_id' => $fiscalYear->id,
            'sale_type' => SaleInvoice::SALE_TYPE_RETAIL,
            'subtotal' => 1000.00,
            'total_discount' => 0,
            'total_amount' => 1000.00,
            'total_paid' => 1000.00,
            'status' => SaleInvoice::STATUS_COMPLETED,
            'is_offline_sale' => false,
            'offline_uuid' => null,
            'completed_at' => now(),
            'return_policy_snapshot' => ['return_period_days' => 7],
            'notes' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state([
            'status' => SaleInvoice::STATUS_DRAFT,
            'completed_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state([
            'status' => SaleInvoice::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function forBranch(Branch $branch): static
    {
        return $this->state([
            'branch_id' => $branch->id,
        ]);
    }

    public function bySalesperson(User $user): static
    {
        return $this->state([
            'salesperson_id' => $user->id,
            'branch_id' => $user->assigned_branch_id,
        ]);
    }

    public function forCustomer(Customer $customer): static
    {
        return $this->state([
            'customer_id' => $customer->id,
        ]);
    }
}
