<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds display_name, module, and description columns to the Spatie
 * permission and role tables.
 *
 * Spatie's default migration only creates: id, name, guard_name, timestamps.
 * Our application uses display_name (for UI labels), module (for grouping
 * permissions in the matrix), and description (for role/permission info).
 *
 * This migration uses a far-future timestamp (9999_12_31) to ensure it
 * runs AFTER the Spatie permission tables migration, which is published
 * at runtime with the current date as its timestamp.
 *
 * The ALTER TABLE statements use IF NOT EXISTS so this migration is
 * safe to rerun.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add columns to permissions table
        if (Schema::hasTable('permissions')) {
            if (! Schema::hasColumn('permissions', 'display_name')) {
                DB::statement('ALTER TABLE permissions ADD COLUMN display_name VARCHAR(255) NULL');
            }
            if (! Schema::hasColumn('permissions', 'module')) {
                DB::statement('ALTER TABLE permissions ADD COLUMN module VARCHAR(50) NULL');
            }
            if (! Schema::hasColumn('permissions', 'description')) {
                DB::statement('ALTER TABLE permissions ADD COLUMN description TEXT NULL');
            }
        }

        // Add columns to roles table
        if (Schema::hasTable('roles')) {
            if (! Schema::hasColumn('roles', 'display_name')) {
                DB::statement('ALTER TABLE roles ADD COLUMN display_name VARCHAR(255) NULL');
            }
            if (! Schema::hasColumn('roles', 'description')) {
                DB::statement('ALTER TABLE roles ADD COLUMN description TEXT NULL');
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('permissions')) {
            if (Schema::hasColumn('permissions', 'display_name')) {
                DB::statement('ALTER TABLE permissions DROP COLUMN display_name');
            }
            if (Schema::hasColumn('permissions', 'module')) {
                DB::statement('ALTER TABLE permissions DROP COLUMN module');
            }
            if (Schema::hasColumn('permissions', 'description')) {
                DB::statement('ALTER TABLE permissions DROP COLUMN description');
            }
        }

        if (Schema::hasTable('roles')) {
            if (Schema::hasColumn('roles', 'display_name')) {
                DB::statement('ALTER TABLE roles DROP COLUMN display_name');
            }
            if (Schema::hasColumn('roles', 'description')) {
                DB::statement('ALTER TABLE roles DROP COLUMN description');
            }
        }
    }
};
