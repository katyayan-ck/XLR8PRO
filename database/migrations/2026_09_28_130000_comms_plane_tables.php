<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-064: comms plane (FRS §12–17). New tables only.
 */
return new class extends Migration
{
    private function audit(Blueprint $t, bool $softDeletes = true): void
    {
        $t->unsignedBigInteger('created_by')->nullable();
        $t->unsignedBigInteger('updated_by')->nullable();
        $t->unsignedBigInteger('deleted_by')->nullable();
        $t->timestamps();
        if ($softDeletes) {
            $t->softDeletes();
        }
    }

    public function up(): void
    {
        if (! Schema::hasTable('xlr8_comm_template')) {
            Schema::create('xlr8_comm_template', function (Blueprint $t) {
                $t->id();
                $t->string('code', 120);
                $t->string('channel', 10);
                $t->string('locale', 10)->default('en-IN');
                $t->string('brand', 20)->default('BMPL');
                $t->string('category', 15)->default('TRANSACTIONAL');
                $t->string('name', 150);
                $t->text('description')->nullable();
                $t->date('deprecated_at')->nullable();
                $t->string('replaced_by', 120)->nullable();
                $t->boolean('is_system')->default(false);
                $this->audit($t);
                $t->unique(['code', 'channel', 'locale'], 'uq_comm_template');
            });
        }

        if (! Schema::hasTable('xlr8_comm_template_version')) {
            Schema::create('xlr8_comm_template_version', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('template_id')->index();
                $t->unsignedInteger('version');
                $t->string('status', 20)->default('DRAFT')->index();
                $t->string('subject', 250)->nullable();
                $t->longText('body_html')->nullable();
                $t->text('body_text')->nullable();
                $t->json('wa_components')->nullable();
                $t->json('variables')->nullable();
                $t->json('sample_vars')->nullable();
                $t->string('provider_template_id', 100)->nullable();
                $t->string('dlt_entity_id', 50)->nullable();
                $t->string('dlt_header', 20)->nullable();
                $t->unsignedBigInteger('approval_request_id')->nullable();
                $t->unsignedBigInteger('approved_by')->nullable();
                $t->timestamp('approved_at')->nullable();
                $t->timestamp('activated_at')->nullable();
                $t->timestamp('retired_at')->nullable();
                $t->unsignedInteger('usage_count')->default(0);
                $t->timestamp('last_used_at')->nullable();
                $this->audit($t);
                $t->unique(['template_id', 'version'], 'uq_comm_template_version');
            });
        }

        if (! Schema::hasTable('xlr8_comm_outbox')) {
            Schema::create('xlr8_comm_outbox', function (Blueprint $t) {
                $t->id();
                $t->string('channel', 10)->index();
                $t->string('status', 12)->default('QUEUED')->index();
                $t->string('driver', 30)->nullable();
                $t->string('to_address', 250)->nullable()->index();
                $t->string('to_person_code', 50)->nullable()->index();
                $t->json('envelope')->nullable();
                $t->string('subject', 250)->nullable();
                $t->text('body_preview')->nullable();
                $t->longText('payload')->nullable();
                $t->string('template_code', 120)->nullable()->index();
                $t->unsignedInteger('template_version')->nullable();
                $t->string('category', 15)->nullable();
                $t->string('ref_type', 30)->nullable();
                $t->unsignedBigInteger('ref_id')->nullable();
                $t->string('idempotency_key', 191)->unique();
                $t->string('provider_message_id', 150)->nullable()->index();
                $t->unsignedSmallInteger('attempts')->default(0);
                $t->string('error', 500)->nullable();
                $t->unsignedSmallInteger('units')->nullable();
                $t->unsignedBigInteger('parent_outbox_id')->nullable();
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->timestamp('sent_at')->nullable();
                $t->timestamp('delivered_at')->nullable();
                $t->timestamps();
                $t->index(['ref_type', 'ref_id']);
            });
        }

        if (! Schema::hasTable('xlr8_comm_sandbox')) {
            Schema::create('xlr8_comm_sandbox', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('outbox_id')->nullable()->index();
                $t->string('channel', 10);
                $t->string('driver', 30);
                $t->string('to_address', 250)->nullable();
                $t->json('payload')->nullable();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (! Schema::hasTable('xlr8_comm_consent')) {
            Schema::create('xlr8_comm_consent', function (Blueprint $t) {
                $t->id();
                $t->string('person_code', 50);
                $t->string('channel', 10);
                $t->boolean('granted');
                $t->string('source', 50)->nullable();
                $t->unsignedBigInteger('changed_by')->nullable();
                $t->timestamps();
                $t->unique(['person_code', 'channel'], 'uq_comm_consent');
            });
        }

        if (! Schema::hasTable('xlr8_comm_suppression')) {
            Schema::create('xlr8_comm_suppression', function (Blueprint $t) {
                $t->id();
                $t->string('channel', 10);
                $t->string('address', 250);
                $t->string('reason', 20);
                $t->string('note', 250)->nullable();
                $t->timestamps();
                $t->unique(['channel', 'address'], 'uq_comm_suppression');
            });
        }

        if (! Schema::hasTable('xlr8_comm_otp')) {
            Schema::create('xlr8_comm_otp', function (Blueprint $t) {
                $t->id();
                $t->string('person_code', 50)->index();
                $t->string('purpose', 30);
                $t->string('destination_masked', 30)->nullable();
                $t->string('code_hash', 255);
                $t->timestamp('expires_at');
                $t->unsignedTinyInteger('attempts')->default(0);
                $t->timestamp('used_at')->nullable();
                $t->unsignedBigInteger('outbox_id')->nullable();
                $t->timestamps();
                $t->index(['person_code', 'purpose', 'created_at']);
            });
        }

        if (! Schema::hasTable('xlr8_comm_wa_thread')) {
            Schema::create('xlr8_comm_wa_thread', function (Blueprint $t) {
                $t->id();
                $t->string('wa_id', 30)->unique();
                $t->boolean('is_group')->default(false);
                $t->string('title', 150)->nullable();
                $t->string('person_code', 50)->nullable()->index();
                $t->string('ref_type', 30)->nullable();
                $t->unsignedBigInteger('ref_id')->nullable();
                $t->unsignedBigInteger('assigned_to')->nullable()->index();
                $t->string('label', 10)->default('OPEN');
                $t->timestamp('session_expires_at')->nullable();
                $t->timestamp('last_message_at')->nullable();
                $t->unsignedInteger('unread')->default(0);
                $this->audit($t);
            });
        }

        if (! Schema::hasTable('xlr8_comm_wa_message')) {
            Schema::create('xlr8_comm_wa_message', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('thread_id')->index();
                $t->string('direction', 3);
                $t->string('type', 15)->default('TEXT');
                $t->text('text')->nullable();
                $t->unsignedBigInteger('doc_id')->nullable();
                $t->json('payload')->nullable();
                $t->string('sender_wa_id', 30)->nullable();
                $t->string('provider_message_id', 150)->nullable()->unique();
                $t->string('status', 12)->default('SENT');
                $t->unsignedBigInteger('outbox_id')->nullable();
                $t->unsignedBigInteger('actor_id')->nullable();
                $t->timestamp('read_at')->nullable();
                $t->timestamps();
            });
        }

        if (! Schema::hasTable('xlr8_comm_call')) {
            Schema::create('xlr8_comm_call', function (Blueprint $t) {
                $t->id();
                $t->string('direction', 3)->default('OUT');
                $t->string('from_number', 20)->nullable();
                $t->string('to_number', 20)->nullable();
                $t->unsignedBigInteger('agent_user_id')->nullable()->index();
                $t->string('person_code', 50)->nullable()->index();
                $t->string('status', 12)->default('DIALING')->index();
                $t->timestamp('started_at')->nullable();
                $t->timestamp('answered_at')->nullable();
                $t->timestamp('ended_at')->nullable();
                $t->unsignedInteger('duration_seconds')->nullable();
                $t->string('disposition', 30)->nullable();
                $t->string('disposition_remark', 500)->nullable();
                $t->unsignedBigInteger('recording_doc_id')->nullable();
                $t->boolean('recording_missing')->default(false);
                $t->string('vendor_call_id', 100)->nullable()->unique();
                $t->string('driver', 30)->nullable();
                $t->string('caller_id', 50)->nullable();
                $t->boolean('is_campaign')->default(false);
                $t->string('ref_type', 30)->nullable();
                $t->unsignedBigInteger('ref_id')->nullable();
                $this->audit($t);
                $t->index(['ref_type', 'ref_id']);
            });
        }

        if (! Schema::hasTable('xlr8_comm_webhook_event')) {
            Schema::create('xlr8_comm_webhook_event', function (Blueprint $t) {
                $t->id();
                $t->string('channel', 10);
                $t->string('event_id', 150);
                $t->json('payload')->nullable();
                $t->string('result', 250)->nullable();
                $t->timestamp('created_at')->nullable();
                $t->unique(['channel', 'event_id'], 'uq_comm_webhook_event');
            });
        }
    }

    public function down(): void
    {
        foreach (['xlr8_comm_webhook_event', 'xlr8_comm_call', 'xlr8_comm_wa_message', 'xlr8_comm_wa_thread', 'xlr8_comm_otp',
            'xlr8_comm_suppression', 'xlr8_comm_consent', 'xlr8_comm_sandbox', 'xlr8_comm_outbox', 'xlr8_comm_template_version', 'xlr8_comm_template'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
