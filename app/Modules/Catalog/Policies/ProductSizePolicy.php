<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Catalog\Models\ProductSize;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ProductSizePolicy
{
    use HandlesAuthorization;

    /**
     * All authenticated users can view sizes (needed for POS and stock).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProductSize $size): Response
    {
        return Response::allow();
    }

    /**
     * Only admin can create sizes.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Only admin can update sizes.
     */
    public function update(User $user, ProductSize $size): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Sizes are deactivated, not deleted.
     */
    public function delete(User $user, ProductSize $size): bool
    {
        return false;
    }
}
