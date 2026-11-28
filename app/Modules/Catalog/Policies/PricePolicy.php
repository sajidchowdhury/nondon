<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Catalog\Models\ProductPrice;
use App\Modules\Identity\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class PricePolicy
{
    use HandlesAuthorization;

    /**
     * All authenticated users can view prices (needed for POS, transfers, etc.).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductPrice $price): Response
    {
        return Response::allow();
    }

    /**
     * Only admin can set/change prices.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    public function update(User $user, ProductPrice $price): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Prices are never deleted — they are historical records.
     */
    public function delete(User $user, ProductPrice $price): bool
    {
        return false;
    }
}
