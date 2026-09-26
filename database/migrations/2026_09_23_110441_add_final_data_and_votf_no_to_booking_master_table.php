<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            // Guarded: environments that ran 2026_09_26_120000 first already have final_data (DEC-041).
            if (! Schema::hasColumn('xlr8_booking_master', 'final_data')) {
                $table->json('final_data')
                    ->nullable()
                    ->after('quotation_id');
            }

            if (! Schema::hasColumn('xlr8_booking_master', 'votf_no')) {
                $table->string('votf_no', 100)
                    ->nullable()
                    ->after('final_data');
            }
        });
    }

    public function down(): void
    {
        Schema::table('xlr8_booking_master', function (Blueprint $table) {
            $table->dropColumn([
                'final_data',
                'votf_no',
            ]);
        });
    }
};
