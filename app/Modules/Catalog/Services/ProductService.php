<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductSize;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductService
{
    /**
     * Create a new product with optional sizes.
     *
     * @param array $data {
     *   - code: string (required, unique)
     *   - name: string (required)
     *   - description: string|null
     *   - category: string|null
     *   - sizes: array (optional, list of size labels)
     * }
     * @return Product
     * @throws ValidationException
     */
    public function create(array $data): Product
    {
        // Validate unique code
        if (Product::where('code', $data['code'])->exists()) {
            throw ValidationException::withMessages([
                'code' => __('Product code already exists.'),
            ]);
        }

        return DB::transaction(function () use ($data) {
            $product = Product::create([
                'code' => $data['code'],
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'category' => $data['category'] ?? null,
                'is_active' => true,
                'created_by' => Auth::id(),
            ]);

            // Add sizes if provided
            if (!empty($data['sizes'])) {
                foreach ($data['sizes'] as $sizeLabel) {
                    $this->addSize($product, $sizeLabel);
                }
            }

            return $product->fresh('sizes');
        });
    }

    /**
     * Update product details (not sizes — use addSize/removeSize for that).
     *
     * @param Product $product
     * @param array $data
     * @return Product
     * @throws ValidationException
     */
    public function update(Product $product, array $data): Product
    {
        // Validate unique code if changing
        if (isset($data['code']) && $data['code'] !== $product->code) {
            if (Product::where('code', $data['code'])->exists()) {
                throw ValidationException::withMessages([
                    'code' => __('Product code already exists.'),
                ]);
            }
        }

        return DB::transaction(function () use ($product, $data) {
            $product->update([
                'code' => $data['code'] ?? $product->code,
                'name' => $data['name'] ?? $product->name,
                'description' => $data['description'] ?? $product->description,
                'category' => $data['category'] ?? $product->category,
            ]);

            return $product->fresh('sizes');
        });
    }

    /**
     * Add a size to a product.
     *
     * @param Product $product
     * @param string $sizeLabel
     * @return ProductSize
     * @throws ValidationException if the size already exists for this product
     */
    public function addSize(Product $product, string $sizeLabel): ProductSize
    {
        if (ProductSize::where('product_id', $product->id)
            ->where('size_label', $sizeLabel)
            ->exists()) {
            throw ValidationException::withMessages([
                'size_label' => __('Size :label already exists for this product.', ['label' => $sizeLabel]),
            ]);
        }

        return DB::transaction(function () use ($product, $sizeLabel) {
            return ProductSize::create([
                'product_id' => $product->id,
                'size_label' => $sizeLabel,
                'is_active' => true,
            ]);
        });
    }

    /**
     * Remove a size from a product.
     *
     * Note: In later phases, this will check if any garments use this size
     * before allowing removal. For now, it's a simple delete.
     *
     * @param Product $product
     * @param string $sizeLabel
     * @return void
     * @throws ValidationException if the size doesn't exist
     */
    public function removeSize(Product $product, string $sizeLabel): void
    {
        $size = ProductSize::where('product_id', $product->id)
            ->where('size_label', $sizeLabel)
            ->first();

        if (!$size) {
            throw ValidationException::withMessages([
                'size_label' => __('Size :label does not exist for this product.', ['label' => $sizeLabel]),
            ]);
        }

        // TODO: Phase 2+ — Check if any garments use this size before deleting
        // For now, we deactivate rather than delete to preserve referential integrity
        $size->update(['is_active' => false]);
        $size->auditCustomAction('remove', ['is_active' => true], ['is_active' => false]);
    }

    /**
     * Activate a product.
     */
    public function activate(Product $product): Product
    {
        return $product->activate();
    }

    /**
     * Deactivate a product.
     * Deactivated products remain in historical transactions.
     */
    public function deactivate(Product $product): Product
    {
        return $product->deactivate(Auth::id());
    }

    /**
     * Search products by code or name.
     */
    public function search(string $term): Collection
    {
        return Product::search($term)
            ->with('sizes')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get products with their sizes eager-loaded.
     */
    public function getWithSizes(): Collection
    {
        return Product::active()
            ->with('sizes')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get a paginated list of products with filters.
     *
     * @param array $filters {
     *   - search: string
     *   - category: string
     *   - status: string ('active', 'inactive')
     *   - per_page: int (default 25)
     * }
     */
    public function getPaginatedList(array $filters = []): LengthAwarePaginator
    {
        $query = Product::query()->with('sizes');

        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        if (!empty($filters['category'])) {
            $query->byCategory($filters['category']);
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

    /**
     * Get all unique categories.
     *
     * @return SupportCollection<string>
     */
    public function getCategories(): SupportCollection
    {
        return Product::whereNotNull('category')
            ->where('category', '!=', '')
            ->distinct()
            ->pluck('category');
    }
}
