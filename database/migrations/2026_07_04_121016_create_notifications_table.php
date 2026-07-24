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

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('leave_request_id')
                ->nullable()
                ->constrained('leave_requests')
                ->nullOnDelete();

            $table->string('type');                          // e.g. 'leave_request.approved', 'leave_request.commented'
            $table->string('title');
            $table->text('message');
            $table->boolean('is_read')->default(false);       // 0 = unread, 1 = read
            $table->timestamp('read_at')->nullable();
            $table->enum('priority', [
                'low',
                'normal',
                'high'
            ])->default('normal');
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->softDeletes();

            $table->timestamps();

            $table->index(['user_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
