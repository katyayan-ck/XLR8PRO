<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-061: Settings scopes, Notify dispatch master + inbox state, Chat thread kinds/subscriptions,
 * Docs library columns. Additive only; existing rows keep working (mobile v1 API unchanged).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('xlr8_utils_setting_scope')) {
            Schema::create('xlr8_utils_setting_scope', function (Blueprint $t) {
                $t->id();
                $t->string('setting_key', 191);
                $t->string('scope_type', 20);   // COMPANY | BRANCH | DESK
                $t->string('scope_code', 50);
                $t->text('value')->nullable();
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->unique(['setting_key', 'scope_type', 'scope_code'], 'uq_setting_scope');
            });
        }

        if (! Schema::hasTable('xlr8_utils_noty_dispatch')) {
            Schema::create('xlr8_utils_noty_dispatch', function (Blueprint $t) {
                $t->id();
                $t->char('kind', 1);
                $t->string('title');
                $t->text('body')->nullable();
                $t->string('ref_type', 30)->nullable();
                $t->unsignedBigInteger('ref_id')->nullable();
                $t->json('channels')->nullable();
                $t->json('data')->nullable();
                $t->string('template', 150)->nullable();
                $t->string('idempotency_key', 191)->nullable()->unique();
                $t->unsignedBigInteger('sender_id')->nullable();
                $t->unsignedInteger('recipient_count')->default(0);
                $t->unsignedBigInteger('created_by')->nullable();
                $t->unsignedBigInteger('updated_by')->nullable();
                $t->timestamps();
                $t->index(['ref_type', 'ref_id']);
            });
        }

        foreach (['xlr8_utils_noty_notification' => true, 'xlr8_utils_noty_alert' => false] as $table => $withKind) {
            Schema::table($table, function (Blueprint $t) use ($table, $withKind) {
                if ($withKind && ! Schema::hasColumn($table, 'kind')) {
                    $t->char('kind', 1)->default('N')->after('type')->index();
                }
                if (! Schema::hasColumn($table, 'dispatch_id')) {
                    $t->unsignedBigInteger('dispatch_id')->nullable()->index();
                }
                if (! Schema::hasColumn($table, 'archived_at')) {
                    $t->timestamp('archived_at')->nullable();
                }
            });
        }

        Schema::table('xlr8_utils_comm_thread', function (Blueprint $t) {
            if (! Schema::hasColumn('xlr8_utils_comm_thread', 'kind')) {
                $t->string('kind', 10)->default('EVENT')->after('actor_id')->index();
            }
            if (! Schema::hasColumn('xlr8_utils_comm_thread', 'is_internal')) {
                $t->boolean('is_internal')->default(false);
            }
            if (! Schema::hasColumn('xlr8_utils_comm_thread', 'edited_at')) {
                $t->timestamp('edited_at')->nullable();
            }
        });

        if (! Schema::hasTable('xlr8_utils_comm_subscription')) {
            Schema::create('xlr8_utils_comm_subscription', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('comm_master_id');
                $t->unsignedBigInteger('user_id');
                $t->timestamps();
                $t->unique(['comm_master_id', 'user_id'], 'uq_comm_subscription');
            });
        }

        Schema::table('xlr8_utils_docs_document', function (Blueprint $t) {
            $t->string('documentable_type')->nullable()->change();
            $t->unsignedBigInteger('documentable_id')->nullable()->change();
            foreach ([
                'kind' => fn () => $t->string('kind', 20)->default('DOCUMENT')->after('documentable_id'),
                'collection' => fn () => $t->string('collection', 50)->default('docs')->after('kind'),
                'path_entity' => fn () => $t->string('path_entity', 100)->nullable(),
                'path_location' => fn () => $t->string('path_location', 100)->nullable(),
                'path_category' => fn () => $t->string('path_category', 100)->nullable(),
                'path_sub' => fn () => $t->string('path_sub', 100)->nullable(),
                'path_item' => fn () => $t->string('path_item', 150)->nullable(),
                'fy' => fn () => $t->string('fy', 9)->nullable(),
                'info_body' => fn () => $t->text('info_body')->nullable(),
                'owner_id' => fn () => $t->unsignedBigInteger('owner_id')->nullable()->index(),
            ] as $column => $add) {
                if (! Schema::hasColumn('xlr8_utils_docs_document', $column)) {
                    $add();
                }
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('xlr8_utils_comm_subscription');
        Schema::dropIfExists('xlr8_utils_noty_dispatch');
        Schema::dropIfExists('xlr8_utils_setting_scope');

        foreach (['xlr8_utils_noty_notification' => ['kind', 'dispatch_id', 'archived_at'], 'xlr8_utils_noty_alert' => ['dispatch_id', 'archived_at'], 'xlr8_utils_comm_thread' => ['kind', 'is_internal', 'edited_at'], 'xlr8_utils_docs_document' => ['kind', 'collection', 'path_entity', 'path_location', 'path_category', 'path_sub', 'path_item', 'fy', 'info_body', 'owner_id']] as $table => $columns) {
            $existing = array_values(array_filter($columns, fn ($c) => Schema::hasColumn($table, $c)));
            if ($existing !== []) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($existing));
            }
        }
    }
};
