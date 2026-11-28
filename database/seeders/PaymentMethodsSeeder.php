<?php

namespace Database\Seeders;

use App\Modules\Sales\Models\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodsSeeder extends Seeder
{
    /**
     * Seed the default POS payment methods (BRD §3.7, doc 03 §3.10).
     *
     * Cash is the only is_cash_method (used by Phase 6 cash-closing calc).
     * bkash / nagad / bank_transfer / card require a reference number.
     */
    public function run(): void
    {
        $methods = [
            ['name' => 'cash', 'display_name' => 'Cash', 'requires_reference' => false, 'is_cash_method' => true, 'sort_order' => 1],
            ['name' => 'bkash', 'display_name' => 'bKash', 'requires_reference' => true, 'is_cash_method' => false, 'sort_order' => 2],
            ['name' => 'nagad', 'display_name' => 'Nagad', 'requires_reference' => true, 'is_cash_method' => false, 'sort_order' => 3],
            ['name' => 'bank_transfer', 'display_name' => 'Bank Transfer', 'requires_reference' => true, 'is_cash_method' => false, 'sort_order' => 4],
            ['name' => 'card', 'display_name' => 'Card', 'requires_reference' => true, 'is_cash_method' => false, 'sort_order' => 5],
        ];

        foreach ($methods as $method) {
            PaymentMethod::firstOrCreate(
                ['name' => $method['name']],
                $method
            );
        }
    }
}
