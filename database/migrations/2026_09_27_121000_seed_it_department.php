<?php

use Database\Seeders\ItDepartmentSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * DEC-046: runs the idempotent IT department seeder on every environment via the deploy's
 * `php artisan migrate --force`.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new ItDepartmentSeeder)->run();
    }

    public function down(): void
    {
        // Leave the department in place; remove it through the admin if ever needed.
    }
};
