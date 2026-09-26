<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * User-level permission overrides come in two halves:
 *  - "added" (extra permission beyond the user's role) is handled natively by
 *    Spatie's own model_has_permissions table via $user->givePermissionTo().
 *  - "removed" (a role-granted permission explicitly revoked for one user,
 *    without touching the role itself) has no native Spatie mechanism, since
 *    Spatie only supports additive direct grants. This table is that missing
 *    half — checked by a Gate::before() hook in AppServiceProvider that denies
 *    access outright before Spatie's own role/permission resolution runs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('xlr8_iam_user_permission_denials', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->unsignedBigInteger('permission_id')->index();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'permission_id']);
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('permission_id')->references('id')->on('xlr8_iam_permissions')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xlr8_iam_user_permission_denials');
    }
};
