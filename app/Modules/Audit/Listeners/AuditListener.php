<?php

namespace App\Modules\Audit\Listeners;

use App\Modules\Audit\Events\AuditableEvent;
use App\Modules\Audit\Services\AuditService;

/**
 * Listener that processes AuditableEvent instances and writes
 * entries to the audit log.
 *
 * This listener is registered in the AuditServiceProvider for
 * the AuditableEvent class. It receives all audit-worthy events
 * and delegates to AuditService for persistence.
 */
class AuditListener
{
    /**
     * The audit service instance.
     */
    protected AuditService $auditService;

    /**
     * Create the event listener.
     */
    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle the event.
     *
     * Writes an audit log entry based on the event data.
     * If an exception occurs during logging, it is caught and
     * logged to prevent the business operation from failing
     * due to an audit logging error.
     */
    public function handle(AuditableEvent $event): void
    {
        try {
            $this->auditService->log($event->action, [
                'category' => $event->category,
                'reference_type' => $event->referenceType,
                'reference_id' => $event->referenceId,
                'previous_values' => $event->previousValues,
                'new_values' => $event->newValues,
                'reason' => $event->reason,
                'user_id' => $event->userId,
                'branch_id' => $event->branchId,
            ]);
        } catch (\Exception $e) {
            // Log the error but don't fail the business operation
            // Audit logging is important but should not block the user's action
            \Log::error('Failed to write audit log', [
                'action' => $event->action,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
