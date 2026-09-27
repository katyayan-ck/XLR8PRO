<?php

use App\Models\Module\Booking\Booking;
use App\Models\Module\Booking\Bookingamount;
use App\Models\Module\Booking\Xl_Refunds;
use App\Models\Module\Booking\XlDelivery;
use App\Models\Module\Booking\XlRto;
use App\Models\Module\Finance\XFinance;
use App\Models\Module\Insurance\XlInsurance;
use App\Models\Utilities\Docs\Document;
use App\Services\Sales\Booking\BookingDeliveryService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DEC-069: booking proofs become Docs documents. Each existing media row is re-pointed from its record to a new
 * Document attached to that record (same collection name). Spatie keeps files under the media id, so no file moves.
 * The original owner is kept in `tags.migrated_from` so down() can restore it exactly.
 * BUG-196: policy copies stored under the misspelled type `…\Insurance\Xlinsurer` are attached to XlInsurance.
 * Idempotent: media already owned by a Document are skipped.
 */
return new class extends Migration
{
    /** stored media owner type => [record class used for the Document, collections] */
    private function map(): array
    {
        $delivery = BookingDeliveryService::PHOTO_COLLECTIONS;

        return [
            Bookingamount::class => [Bookingamount::class, ['amount-proof']],
            XFinance::class => [XFinance::class, ['instrument_proof']],
            XlInsurance::class => [XlInsurance::class, ['policy_copy']],
            'App\\Models\\Module\\Insurance\\Xlinsurer' => [XlInsurance::class, ['policy_copy']],
            'App\\Models\\Module\\Insurance\\XlInsurer' => [XlInsurance::class, ['policy_copy']],
            'App\\Models\\Module\\Booking\\XlInsurer' => [XlInsurance::class, ['policy_copy']],
            XlRto::class => [XlRto::class, ['trc_copy', 'tax_receipt_copy']],
            Xl_Refunds::class => [Xl_Refunds::class, ['acc-proof', 'acc_proof', 'aadhar', 'aadhaar', 'pan', 'pay-proof', 'pay_proof']],
            XlDelivery::class => [XlDelivery::class, $delivery],
            Booking::class => [Booking::class, ['chassis_image']],
        ];
    }

    /** legacy spellings folded onto the collection the code reads now */
    private const CANONICAL = ['acc_proof' => 'acc-proof', 'aadhaar' => 'aadhar', 'pay_proof' => 'pay-proof'];

    public function up(): void
    {
        if (! Schema::hasTable('media') || ! Schema::hasTable('xlr8_utils_docs_document')) {
            return;
        }

        DB::transaction(function () {
            foreach ($this->map() as $storedType => [$recordClass, $collections]) {
                $rows = DB::table('media')->where('model_type', $storedType)->whereIn('collection_name', $collections)->orderBy('id')->get();
                foreach ($rows as $media) {
                    $collection = self::CANONICAL[$media->collection_name] ?? $media->collection_name;
                    $docId = DB::table('xlr8_utils_docs_document')->insertGetId([
                        'documentable_type' => $recordClass,
                        'documentable_id' => $media->model_id,
                        'kind' => str_starts_with((string) $media->mime_type, 'image/') ? 'IMAGE' : 'DOCUMENT',
                        'collection' => $collection,
                        'title' => $media->file_name,
                        'tags' => json_encode(['migrated_from' => [
                            'model_type' => $storedType, 'model_id' => $media->model_id, 'collection_name' => $media->collection_name,
                        ]]),
                        'created_at' => $media->created_at,
                        'updated_at' => now(),
                    ]);
                    DB::table('media')->where('id', $media->id)->update([
                        'model_type' => Document::class, 'model_id' => $docId, 'collection_name' => $collection,
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('media') || ! Schema::hasTable('xlr8_utils_docs_document')) {
            return;
        }

        DB::transaction(function () {
            $docs = DB::table('xlr8_utils_docs_document')->where('tags', 'like', '%migrated_from%')->get(['id', 'tags']);
            foreach ($docs as $doc) {
                $from = json_decode((string) $doc->tags, true)['migrated_from'] ?? null;
                if (! $from) {
                    continue;
                }
                DB::table('media')->where('model_type', Document::class)->where('model_id', $doc->id)->update([
                    'model_type' => $from['model_type'], 'model_id' => $from['model_id'], 'collection_name' => $from['collection_name'],
                ]);
                DB::table('xlr8_utils_docs_document')->where('id', $doc->id)->delete();
            }
        });
    }
};
