<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->index(['status', 'created_at'], 'leave_requests_status_created_at_index');
            $table->index('start_date', 'leave_requests_start_date_index');
            $table->index('end_date', 'leave_requests_end_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->dropIndex('leave_requests_status_created_at_index');
            $table->dropIndex('leave_requests_start_date_index');
            $table->dropIndex('leave_requests_end_date_index');
        });
    }
};
