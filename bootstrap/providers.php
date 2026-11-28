<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;

/**
 * Laravel 11 Application Service Providers
 *
 * In Laravel 11, this file is REQUIRED for application service providers
 * to be loaded. Without it, only framework providers are registered,
 * and AppServiceProvider (which registers all module service providers)
 * would never be loaded.
 *
 * The AppServiceProvider registers all module service providers:
 * - IdentityServiceProvider (users, roles, auth)
 * - OrganizationServiceProvider (branches, fiscal years)
 * - AuditServiceProvider (audit log)
 * - CatalogServiceProvider (products, prices)
 * - InventoryServiceProvider (garments, movements)
 *
 * The AuthServiceProvider registers model policies and authorization gates.
 */
return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
];
