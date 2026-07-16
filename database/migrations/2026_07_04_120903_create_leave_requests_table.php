<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')                   // the student requesting leave
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('leave_type_id')
                ->constrained('leave_types')
                ->restrictOnDelete();                       // don't allow deleting a leave type in use

            $table->date('start_date');
            $table->date('end_date');
            $table->text('reason');

            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])
                ->default('pending');

            $table->foreignId('reviewed_by')                // trainer/admin who approved/rejected
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
