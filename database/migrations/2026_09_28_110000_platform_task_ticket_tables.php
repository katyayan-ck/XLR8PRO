<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-062: Task and Ticket utilities (FRS §5–6). New tables only.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('xlr8_utils_task')) {
            Schema::create('xlr8_utils_task', function (Blueprint $t) {
                $t->id();
                $t->string('title', 250);
                $t->string('type', 50);
                $t->string('priority', 50)->nullable();
                $t->string('status', 20)->default('FRESH')->index();
                $t->unsignedBigInteger('owner_id')->index();
                $t->text('details')->nullable();
                $t->dateTime('deadline')->nullable()->index();
                $t->string('ref_type', 30)->nullable();
                $t->unsignedBigInteger('ref_id')->nullable();
                $t->boolean('is_group')->default(false);
                $t->timestamp('submitted_at')->nullable();
                $t->timestamp('closed_at')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->unsignedBigInteger('deleted_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['ref_type', 'ref_id']);
            });
        }

        if (! Schema::hasTable('xlr8_utils_task_person')) {
            Schema::create('xlr8_utils_task_person', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('task_id');
                $t->unsignedBigInteger('user_id')->index();
                $t->string('role', 10);
                $t->timestamps();
                $t->unique(['task_id', 'user_id', 'role'], 'uq_task_person_role');
            });
        }

        if (! Schema::hasTable('xlr8_utils_ticket')) {
            Schema::create('xlr8_utils_ticket', function (Blueprint $t) {
                $t->id();
                $t->string('number', 40)->unique();
                $t->string('branch_code', 20);
                $t->string('fy', 5);
                $t->unsignedInteger('seq');
                $t->string('category', 50);
                $t->string('priority', 5);
                $t->string('status', 20)->default('NEW')->index();
                $t->string('title', 250);
                $t->text('details')->nullable();
                $t->unsignedBigInteger('requester_id')->index();
                $t->unsignedBigInteger('owner_id')->nullable()->index();
                $t->string('ref_type', 30)->nullable();
                $t->unsignedBigInteger('ref_id')->nullable();
                $t->dateTime('due_at')->nullable()->index();
                $t->dateTime('sla_paused_at')->nullable();
                $t->unsignedInteger('sla_paused_minutes')->default(0);
                $t->dateTime('breached_at')->nullable();
                $t->dateTime('acknowledged_at')->nullable();
                $t->dateTime('resolved_at')->nullable();
                $t->dateTime('closed_at')->nullable();
                $t->string('close_reason', 500)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->unsignedBigInteger('deleted_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['ref_type', 'ref_id']);
            });
        }

        if (! Schema::hasTable('xlr8_utils_ticket_person')) {
            Schema::create('xlr8_utils_ticket_person', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('ticket_id');
                $t->unsignedBigInteger('user_id')->index();
                $t->string('role', 10);
                $t->timestamps();
                $t->unique(['ticket_id', 'user_id', 'role'], 'uq_ticket_person_role');
            });
        }

        if (! Schema::hasTable('xlr8_utils_ticket_counter')) {
            Schema::create('xlr8_utils_ticket_counter', function (Blueprint $t) {
                $t->id();
                $t->string('branch_code', 20);
                $t->string('fy', 5);
                $t->unsignedInteger('last_seq')->default(0);
                $t->timestamps();
                $t->unique(['branch_code', 'fy'], 'uq_ticket_counter');
            });
        }
    }

    public function down(): void
    {
        foreach (['xlr8_utils_ticket_counter', 'xlr8_utils_ticket_person', 'xlr8_utils_ticket', 'xlr8_utils_task_person', 'xlr8_utils_task'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
