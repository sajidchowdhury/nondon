<?php

namespace App\Modules\Audit\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Identity\Models\User;
use App\Modules\Organization\Models\Branch;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Write an audit log entry.
     *
     * This is the primary method for recording business actions.
     * It automatically captures the current user, branch, IP, and user agent.
     *
     * @param  string  $action  The action identifier (e.g., 'user.create', 'sale.complete')
     * @param  array  $context  Additional context:
     *                          - category: string (e.g., 'inventory', 'sales', 'security', 'admin')
     *                          - reference_type: string|null (e.g., 'user', 'garment', 'sale_invoice')
     *                          - reference_id: int|null
     *                          - previous_values: array|null (for updates — old field values)
     *                          - new_values: array|null (for creates/updates — new field values)
     *                          - reason: string|null (user-provided reason for the action)
     *                          - user_id: int|null (override the authenticated user, for system actions)
     *                          - branch_id: int|null (override the branch context)
     */
    public function log(string $action, array $context = []): AuditLog
    {
        $user = $context['user_id'] ?? Auth::id();
        $branch = $context['branch_id'] ?? $this->getCurrentBranchId();

        return AuditLog::create([
            'user_id' => $user,
            'branch_id' => $branch,
            'action' => $action,
            'category' => $context['category'] ?? $this->inferCategory($action),
            'reference_type' => $context['reference_type'] ?? null,
            'reference_id' => $context['reference_id'] ?? null,
            'previous_values' => $context['previous_values'] ?? null,
            'new_values' => $context['new_values'] ?? null,
            'reason' => $context['reason'] ?? null,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'performed_at' => now(),
        ]);
    }

    /**
     * Query audit logs with filters.
     *
     * @param  array  $filters  {
     *                          - user_id: int
     *                          - branch_id: int
     *                          - action: string
     *                          - category: string
     *                          - reference_type: string
     *                          - reference_id: int
     *                          - date_from: string|Carbon
     *                          - date_to: string|Carbon
     *                          - per_page: int (default 50)
     *                          }
     */
    public function query(array $filters = []): Builder
    {
        // NOTE: Do not use AuditLog::latest() here.
        // The Builder's built-in latest() method takes precedence over the
        // model's scopeLatest() (scopes only fire via __call when the Builder
        // lacks the method). The built-in latest() orders only by performed_at
        // DESC without the deterministic secondary sort on id DESC, which makes
        // ordering non-deterministic when several entries share the same
        // performed_at timestamp (common in rapid-fire test logging).
        // Use the explicit orderBy to match scopeLatest()'s behaviour.
        $query = AuditLog::query()
            ->orderBy('performed_at', 'desc')
            ->orderBy('id', 'desc');

        if (! empty($filters['user_id'])) {
            $query->forUser((int) $filters['user_id']);
        }

        if (! empty($filters['branch_id'])) {
            $query->forBranch((int) $filters['branch_id']);
        }

        if (! empty($filters['action'])) {
            $query->forAction($filters['action']);
        }

        if (! empty($filters['category'])) {
            $query->forCategory($filters['category']);
        }

        if (! empty($filters['reference_type'])) {
            $query->where('reference_type', $filters['reference_type']);

            if (! empty($filters['reference_id'])) {
                $query->where('reference_id', $filters['reference_id']);
            }
        }

        if (! empty($filters['date_from'])) {
            $query->where('performed_at', '>=', Carbon::parse($filters['date_from'])->startOfDay());
        }

        if (! empty($filters['date_to'])) {
            $query->where('performed_at', '<=', Carbon::parse($filters['date_to'])->endOfDay());
        }

        return $query;
    }

    /**
     * Get audit entries for a specific user.
     *
     * @param  int  $limit  = 100
     */
    public function forUser(User $user, int $limit = 100): Collection
    {
        return AuditLog::forUser($user->id)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * Get audit entries for a branch on a specific date.
     *
     * @param  \DateTimeInterface|string  $date
     */
    public function forBranch(Branch $branch, $date = null): Collection
    {
        $query = AuditLog::forBranch($branch->id)->latest();

        if ($date) {
            $query->whereDate('performed_at', $date);
        }

        return $query->get();
    }

    /**
     * Get audit entries for a specific entity (reference).
     *
     * @param  string  $type  The reference type (e.g., 'user', 'garment')
     * @param  int  $id  The reference ID
     */
    public function forReference(string $type, int $id): Collection
    {
        return AuditLog::forReference($type, $id)
            ->latest()
            ->get();
    }

    /**
     * Log a sensitive data access event.
     *
     * Used by the LogDataAccess middleware when a user views
     * purchase cost, supplier information, or other restricted data.
     *
     * @param  string  $dataType  The type of sensitive data accessed (e.g., 'purchase_cost')
     */
    public function logDataAccess(string $dataType, string $referenceType, int $referenceId): AuditLog
    {
        return $this->log('sensitive_data.view', [
            'category' => 'security',
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'new_values' => ['data_type' => $dataType],
            'reason' => __('Sensitive data access'),
        ]);
    }

    /**
     * Get the current branch ID from the session/container.
     * Returns null if no branch is set (e.g., in tests without auth).
     */
    protected function getCurrentBranchId(): ?int
    {
        try {
            $branch = app('active.branch');

            return $branch?->id;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Infer the category from the action prefix.
     */
    protected function inferCategory(string $action): string
    {
        // Map action prefixes to categories
        $prefixMap = [
            'user.' => 'identity',
            'role.' => 'identity',
            'permission.' => 'identity',
            'password.' => 'security',
            'login.' => 'security',
            'account.' => 'security',
            'branch.' => 'organization',
            'fiscal.' => 'organization',
            'product.' => 'catalog',
            'price.' => 'catalog',
            'purchase.' => 'purchasing',
            'supplier.' => 'purchasing',
            'garment.' => 'inventory',
            'inventory.' => 'inventory',
            'transfer.' => 'transfers',
            'sale.' => 'sales',
            'pos.' => 'sales',
            'online.' => 'online',
            'courier.' => 'courier',
            'return.' => 'returns',
            'cash.' => 'cash',
            'expense.' => 'expenses',
            'stock_count' => 'stock_count',
            'damage.' => 'damaged_stock',
            'report.' => 'reporting',
            'system.' => 'system',
            'sensitive_data' => 'security',
        ];

        foreach ($prefixMap as $prefix => $category) {
            if (str_starts_with($action, $prefix)) {
                return $category;
            }
        }

        return 'general';
    }
}
