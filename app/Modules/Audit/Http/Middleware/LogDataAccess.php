<?php

namespace App\Modules\Audit\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Modules\Audit\Services\AuditService;

/**
 * Middleware that logs when a user accesses sensitive data.
 *
 * Apply this middleware to routes or API endpoints that expose
 * sensitive data such as:
 * - Purchase cost
 * - Supplier information
 * - Profit/margin reports
 * - All-branch historical financial reports
 *
 * The middleware logs the access to the audit log with action
 * 'sensitive_data.view' and category 'security', capturing:
 * - Who accessed the data (user_id)
 * - What data was accessed (data_type)
 * - Which entity (reference_type, reference_id)
 * - When (performed_at)
 * - From where (ip_address, user_agent)
 *
 * Usage in routes:
 *   Route::get('/garments/{garment}', ...)->middleware('log.data-access:purchase_cost,garment');
 *
 * The first parameter is the data type, the second is the reference type
 * (defaults to the route parameter name).
 */
class LogDataAccess
{
    /**
     * The audit service instance.
     */
    protected AuditService $auditService;

    /**
     * Create the middleware.
     */
    public function __construct(AuditService $auditService)
    {
        $this->auditService = $auditService;
    }

    /**
     * Handle an incoming request.
     *
     * @param Request $request
     * @param Closure $next
     * @param string $dataType The type of sensitive data being accessed (e.g., 'purchase_cost')
     * @param string|null $referenceType The reference type (e.g., 'garment'). If null, inferred from route.
     * @return Response
     */
    public function handle(Request $request, Closure $next, string $dataType, ?string $referenceType = null): Response
    {
        $response = $next($request);

        // Only log for authenticated users
        if (!auth()->check()) {
            return $response;
        }

        // Only log successful responses (2xx)
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }

        // Infer reference type from route parameters if not provided
        if (!$referenceType) {
            $referenceType = $this->inferReferenceType($request);
        }

        // Get reference ID from route parameters
        $referenceId = $this->getReferenceId($request);

        if ($referenceId) {
            $this->auditService->logDataAccess($dataType, $referenceType, $referenceId);
        }

        return $response;
    }

    /**
     * Infer the reference type from the route parameters.
     *
     * Looks for common parameter names like 'user', 'garment', 'product'.
     */
    protected function inferReferenceType(Request $request): ?string
    {
        $params = $request->route()?->parameters();

        if (!$params) {
            return null;
        }

        // Use the first parameter as the reference type
        $firstKey = array_key_first($params);
        $value = $params[$firstKey];

        // If the value is a model, use its class basename
        if (is_object($value)) {
            return strtolower(class_basename($value));
        }

        return $firstKey;
    }

    /**
     * Get the reference ID from the route parameters.
     */
    protected function getReferenceId(Request $request): ?int
    {
        $params = $request->route()?->parameters();

        if (!$params) {
            return null;
        }

        $firstValue = reset($params);

        if (is_object($firstValue) && method_exists($firstValue, 'getKey')) {
            return $firstValue->getKey();
        }

        if (is_numeric($firstValue)) {
            return (int) $firstValue;
        }

        return null;
    }
}
