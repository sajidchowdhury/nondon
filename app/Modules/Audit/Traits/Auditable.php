<?php

namespace App\Modules\Audit\Traits;

use App\Modules\Audit\Events\AuditableEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait for models that need automatic audit logging.
 *
 * When applied to a model, this trait:
 * - Dispatches an AuditableEvent on model creation
 * - Dispatches an AuditableEvent on model updates (with previous and new values)
 * - Provides manual audit methods for custom actions
 *
 * The trait uses Eloquent model events:
 * - `created` event → dispatches audit with action '{prefix}.create'
 * - `updated` event → dispatches audit with action '{prefix}.update'
 *
 * Usage:
 *   class User extends Model
 *   {
 *       use Auditable;
 *
 *       protected $auditPrefix = 'user'; // Generates 'user.create', 'user.update'
 *
 *       // Optionally hide fields from audit (e.g., password_hash)
 *       protected $auditHidden = ['password_hash', 'remember_token'];
 *   }
 */
trait Auditable
{
    /**
     * Boot the trait.
     *
     * Registers model event listeners for automatic audit logging.
     */
    public static function bootAuditable(): void
    {
        // Log on creation
        static::created(function (Model $model) {
            $model->dispatchAuditEvent(
                action: $model->getAuditAction('create'),
                previousValues: null,
                newValues: $model->getAuditNewValues(),
            );
        });

        // Log on update (with previous and new values)
        static::updated(function (Model $model) {
            $model->dispatchAuditEvent(
                action: $model->getAuditAction('update'),
                previousValues: $model->getAuditPreviousValues(),
                newValues: $model->getAuditChangedValues(),
            );
        });
    }

    /**
     * Get the audit action prefix for this model.
     * Override in the model class to customize.
     */
    protected function getAuditPrefix(): string
    {
        return property_exists($this, 'auditPrefix')
            ? $this->auditPrefix
            : strtolower(class_basename($this));
    }

    /**
     * Get the full audit action string.
     *
     * @param string $operation (create, update, delete, etc.)
     * @return string
     */
    protected function getAuditAction(string $operation): string
    {
        return $this->getAuditPrefix() . '.' . $operation;
    }

    /**
     * Get the reference type for this model.
     * Uses the snake_case short class name by default.
     */
    protected function getAuditReferenceType(): string
    {
        return \Illuminate\Support\Str::snake(class_basename($this));
    }

    /**
     * Get fields to hide from audit values.
     * Override in the model class to customize.
     */
    protected function getAuditHidden(): array
    {
        return property_exists($this, 'auditHidden')
            ? $this->auditHidden
            : ['password_hash', 'remember_token'];
    }

    /**
     * Get the new values for audit (on create).
     * Excludes hidden fields.
     */
    protected function getAuditNewValues(): array
    {
        $values = $this->attributesToArray();
        return $this->filterAuditValues($values);
    }

    /**
     * Get the previous values for audit (on update).
     * Only includes changed attributes.
     */
    protected function getAuditPreviousValues(): array
    {
        $original = $this->getOriginal();
        $changes = array_keys($this->getChanges());

        $previousValues = [];
        foreach ($changes as $key) {
            if (isset($original[$key])) {
                $previousValues[$key] = $original[$key];
            }
        }

        return $this->filterAuditValues($previousValues);
    }

    /**
     * Get only the changed values for audit (on update).
     */
    protected function getAuditChangedValues(): array
    {
        $changes = $this->getChanges();
        return $this->filterAuditValues($changes);
    }

    /**
     * Filter out hidden fields from audit values.
     */
    protected function filterAuditValues(array $values): array
    {
        $hidden = $this->getAuditHidden();

        foreach ($hidden as $field) {
            unset($values[$field]);
        }

        return $values;
    }

    /**
     * Dispatch an AuditableEvent.
     *
     * @param string $action
     * @param array|null $previousValues
     * @param array|null $newValues
     * @param string|null $reason
     */
    protected function dispatchAuditEvent(
        string $action,
        ?array $previousValues = null,
        ?array $newValues = null,
        ?string $reason = null,
    ): void {
        // Use event() helper directly for synchronous dispatch
        // This avoids potential issues with Dispatchable trait's static dispatch
        event(new AuditableEvent($action, [
            'category' => $this->getAuditCategory(),
            'reference_type' => $this->getAuditReferenceType(),
            'reference_id' => $this->getKey(),
            'previous_values' => $previousValues,
            'new_values' => $newValues,
            'reason' => $reason,
        ]));
    }

    /**
     * Get the audit category for this model.
     * Override in the model class to customize.
     */
    protected function getAuditCategory(): string
    {
        return property_exists($this, 'auditCategory')
            ? $this->auditCategory
            : 'general';
    }

    /**
     * Manually dispatch a custom audit event for this model.
     *
     * Use this for actions that don't trigger standard model events
     * (e.g., activate, deactivate, lock, unlock).
     *
     * @param string $action The action suffix (e.g., 'activate', 'lock')
     * @param array|null $previousValues
     * @param array|null $newValues
     * @param string|null $reason
     */
    public function auditCustomAction(
        string $action,
        ?array $previousValues = null,
        ?array $newValues = null,
        ?string $reason = null,
    ): void {
        $this->dispatchAuditEvent(
            action: $this->getAuditPrefix() . '.' . $action,
            previousValues: $previousValues,
            newValues: $newValues,
            reason: $reason,
        );
    }
}
