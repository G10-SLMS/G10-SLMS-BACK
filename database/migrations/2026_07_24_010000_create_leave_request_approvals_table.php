<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{

    public function up(): void
    {
        Schema::create('leave_request_approvals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('leave_request_id')
                ->constrained('leave_requests')
                ->cascadeOnDelete();

            $table->foreignId('approver_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('approver_name');
            $table->string('approver_role');

            $table->enum('status', ['approved', 'rejected']);
            $table->text('reason')->nullable();

            $table->timestamp('action_at');

            $table->timestamps();

            $table->index(['leave_request_id', 'action_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_approvals');
    }
};