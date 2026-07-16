<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('leave_request_id')
                ->constrained('leave_requests')
                ->cascadeOnDelete();

            $table->foreignId('user_id') 
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamp('edited_at')->nullable();
            $table->softDeletes();
            $table->foreignId('parent_id')
                ->nullable()
                ->constrained('comments')
                ->nullOnDelete();

            $table->text('body');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};
