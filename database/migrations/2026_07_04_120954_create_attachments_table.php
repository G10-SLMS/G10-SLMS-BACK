<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attachments', function (Blueprint $table) {
            $table->id();

            $table->foreignId('leave_request_id')
                ->constrained('leave_requests')
                ->cascadeOnDelete();

            $table->string('original_name');
            $table->string('path');                          // storage path (e.g. avatars/xxx.pdf equivalent)
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('size')->nullable();   // bytes
            $table->foreignId('uploaded_by')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->boolean('is_verified')->default(false);
            $table->foreignId('verified_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->softDeletes();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
