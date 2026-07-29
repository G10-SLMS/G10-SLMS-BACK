<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            // 'full_day'  -> uses start_date/end_date as-is (duration_hours is null)
            // 'hourly'    -> a same-day request for a predefined number of hours
            $table->enum('duration_type', ['full_day', 'hourly'])
                ->default('full_day')
                ->after('end_date');

            // Only populated when duration_type = 'hourly'. Stored as a decimal so
            // half-hour options (e.g. 1.5) can be supported later without a schema change.
            $table->decimal('duration_hours', 4, 1)
                ->nullable()
                ->after('duration_type');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropColumn(['duration_type', 'duration_hours']);
        });
    }
};
