<?php

namespace App\Modules\Catalog\Policies;

use App\Modules\Identity\Models\User;
use App\Modules\Catalog\Models\Product;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Auth\Access\Response;

class ProductPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view the list of products.
     * All authenticated users can view the product catalog.
     * (Salespersons need it for POS, supervisors for stock management.)
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users
    }

    /**
     * Determine whether the user can view a specific product.
     */
    public function view(User $user, Product $product): Response
    {
        return Response::allow(); // All authenticated users can view products
    }

    /**
     * Determine whether the user can create products.
     * Admin only.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can update a product.
     * Admin only.
     */
    public function update(User $user, Product $product): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can activate/deactivate a product.
     * Admin only.
     */
    public function toggleStatus(User $user, Product $product): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can manage sizes for a product.
     * Admin only.
     */
    public function manageSizes(User $user, Product $product): bool
    {
        return $user->hasRole('main_branch_admin');
    }

    /**
     * Determine whether the user can delete a product.
     * Products are never deleted — they are deactivated.
     */
    public function delete(User $user, Product $product): bool
    {
        return false;
    }
}
