<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-063: approval engine (FRS §7–8) in the reserved xlr8_approval_* prefix. New tables only.
 */
return new class extends Migration
{
    private const SCOPES = ['company_code', 'zone_code', 'state_code', 'branch_code', 'desk_code', 'segment_code', 'model_code', 'variant_code', 'permit_code', 'channel_code'];

    public function up(): void
    {
        if (! Schema::hasTable('xlr8_approval_topic')) {
            Schema::create('xlr8_approval_topic', function (Blueprint $t) {
                $t->id();
                $t->string('code', 100)->unique();
                $t->unsignedBigInteger('parent_id')->nullable()->index();
                $t->string('title', 150);
                $t->string('item_key', 60)->nullable()->unique();
                $t->string('mode', 15)->nullable();
                $t->string('value_type', 12)->nullable();
                $t->boolean('is_mandatory')->default(false);
                $t->boolean('is_active')->default(true);
                $t->text('description')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->unsignedBigInteger('deleted_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
            });
        }

        if (! Schema::hasTable('xlr8_approval_rule')) {
            Schema::create('xlr8_approval_rule', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('topic_id')->index();
                foreach (self::SCOPES as $scope) {
                    $t->string($scope, 50)->nullable();
                }
                $t->date('valid_from')->nullable();
                $t->date('valid_to')->nullable();
                $t->boolean('is_active')->default(true);
                $t->string('note', 250)->nullable();
                $t->string('import_batch', 40)->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->unsignedBigInteger('deleted_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['topic_id', 'is_active']);
            });
        }

        if (! Schema::hasTable('xlr8_approval_rule_level')) {
            Schema::create('xlr8_approval_rule_level', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('rule_id')->index();
                $t->unsignedSmallInteger('level_no');
                $t->string('designation_code', 50)->nullable();
                $t->json('user_ids')->nullable();
                $t->string('value_type', 12)->default('AMOUNT');
                $t->decimal('std_value', 15, 2)->nullable();
                $t->decimal('min_value', 15, 2)->nullable();
                $t->decimal('max_value', 15, 2)->nullable();
                $t->timestamps();
                $t->unique(['rule_id', 'level_no'], 'uq_approval_rule_level');
            });
        }

        if (! Schema::hasTable('xlr8_approval_request')) {
            Schema::create('xlr8_approval_request', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('topic_id')->index();
                $t->string('topic_code', 100)->index();
                $t->string('topic_title', 150);
                $t->string('item_key', 60)->nullable()->index();
                $t->string('mode', 15);
                $t->string('source_type', 30)->nullable();
                $t->unsignedBigInteger('source_id')->nullable();
                $t->unsignedBigInteger('requester_id')->index();
                $t->string('value_type', 12);
                $t->decimal('asked', 15, 2)->default(0);
                $t->unsignedInteger('ask_revision')->default(1);
                $t->json('scope')->nullable();
                $t->json('snapshot');
                $t->string('status', 12)->default('OPEN')->index();
                $t->unsignedSmallInteger('effective_level')->nullable();
                $t->decimal('effective_value', 15, 2)->nullable();
                $t->unsignedBigInteger('effective_actor_id')->nullable();
                $t->unsignedSmallInteger('current_level')->nullable();
                $t->boolean('auto_accepted')->default(false);
                $t->string('branch_code', 20)->nullable()->index();
                $t->string('fy', 5)->nullable()->index();
                $t->timestamp('closed_at')->nullable();
                $t->unsignedBigInteger('closed_by')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->unsignedBigInteger('deleted_by')->nullable();
                $t->timestamps();
                $t->softDeletes();
                $t->index(['source_type', 'source_id']);
            });
        }

        if (! Schema::hasTable('xlr8_approval_counter')) {
            Schema::create('xlr8_approval_counter', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('request_id')->index();
                $t->unsignedInteger('ask_revision');
                $t->unsignedSmallInteger('level_no');
                $t->unsignedBigInteger('actor_id');
                $t->decimal('value', 15, 2);
                $t->string('remark', 1000)->nullable();
                $t->boolean('is_system')->default(false);
                $t->timestamp('created_at')->nullable();
                $t->index(['request_id', 'ask_revision']);
            });
        }

        if (! Schema::hasTable('xlr8_approval_event')) {
            Schema::create('xlr8_approval_event', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('request_id')->index();
                $t->string('type', 20);
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->unsignedInteger('ask_revision')->nullable();
                $t->unsignedSmallInteger('level_no')->nullable();
                $t->decimal('value', 15, 2)->nullable();
                $t->json('data')->nullable();
                $t->timestamp('created_at')->nullable();
                $t->index(['type', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        foreach (['xlr8_approval_event', 'xlr8_approval_counter', 'xlr8_approval_request', 'xlr8_approval_rule_level', 'xlr8_approval_rule', 'xlr8_approval_topic'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
