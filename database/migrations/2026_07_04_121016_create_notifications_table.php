<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')                     // recipient
                ->constrained('users')
                ->cascadeOnDelete();

            // Nullable + nullOnDelete so a notification can survive
            // even if the related leave request is later deleted.
            $table->foreignId('leave_request_id')
                ->nullable()
                ->constrained('leave_requests')
                ->nullOnDelete();

            $table->string('type');                          // e.g. 'leave_request.approved', 'leave_request.commented'
            $table->string('title');
            $table->text('message');
            $table->timestamp('read_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'read_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
