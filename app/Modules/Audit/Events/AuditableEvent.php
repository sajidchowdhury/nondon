<?php

namespace App\Modules\Audit\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Base event class for all audit-worthy events.
 *
 * Modules dispatch events that extend this class when a meaningful
 * business action occurs. The AuditListener picks up the event and
 * writes to the audit log via AuditService.
 *
 * Usage:
 *   AuditableEvent::dispatch('user.create', [
 *       'category' => 'identity',
 *       'reference_type' => 'user',
 *       'reference_id' => $user->id,
 *       'new_values' => ['username' => $user->username, 'full_name' => $user->full_name],
 *   ]);
 *
 * Or via explicit event() helper:
 *   event(new AuditableEvent('user.create', [...]));
 *
 * Or for module-specific events, extend this class:
 *   class UserCreated extends AuditableEvent
 *   {
 *       // Additional properties specific to the event
 *   }
 */
class AuditableEvent
{
    use Dispatchable;

    /**
     * The action identifier (e.g., 'user.create', 'sale.complete').
     */
    public string $action;

    /**
     * The category (e.g., 'identity', 'inventory', 'sales').
     */
    public string $category;

    /**
     * The reference type (e.g., 'user', 'garment', 'sale_invoice').
     */
    public ?string $referenceType;

    /**
     * The reference ID.
     */
    public ?int $referenceId;

    /**
     * Previous values (for updates — old field values).
     */
    public ?array $previousValues;

    /**
     * New values (for creates/updates — new field values).
     */
    public ?array $newValues;

    /**
     * User-provided reason for the action.
     */
    public ?string $reason;

    /**
     * Override user ID (for system actions where no user is authenticated).
     */
    public ?int $userId;

    /**
     * Override branch ID.
     */
    public ?int $branchId;

    /**
     * Create a new event instance.
     *
     * @param string $action
     * @param array $context {
     *   - category: string
     *   - reference_type: string|null
     *   - reference_id: int|null
     *   - previous_values: array|null
     *   - new_values: array|null
     *   - reason: string|null
     *   - user_id: int|null
     *   - branch_id: int|null
     * }
     */
    public function __construct(string $action, array $context = [])
    {
        $this->action = $action;
        $this->category = $context['category'] ?? 'general';
        $this->referenceType = $context['reference_type'] ?? null;
        $this->referenceId = $context['reference_id'] ?? null;
        $this->previousValues = $context['previous_values'] ?? null;
        $this->newValues = $context['new_values'] ?? null;
        $this->reason = $context['reason'] ?? null;
        $this->userId = $context['user_id'] ?? null;
        $this->branchId = $context['branch_id'] ?? null;
    }
}
