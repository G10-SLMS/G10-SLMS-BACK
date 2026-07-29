<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leave_request_approvals', function (Blueprint $table) {
            $table->enum('status', ['under_review', 'approved', 'rejected'])
                ->change();
        });
    }

    public function down(): void
    {

        DB::table('leave_request_approvals')
            ->where('status', 'under_review')
            ->delete();

        Schema::table('leave_request_approvals', function (Blueprint $table) {
            $table->enum('status', ['approved', 'rejected'])->change();
        });
    }
};
