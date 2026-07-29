<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_requests', function (Blueprint $table) {
            $table->enum('status', ['pending', 'under_review', 'approved', 'rejected', 'cancelled'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('leave_requests')
            ->where('status', 'under_review')
            ->update(['status' => 'pending']);

        Schema::table('leave_requests', function (Blueprint $table) {
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending')
                ->change();
        });
    }
};
