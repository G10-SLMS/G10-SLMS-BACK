<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('role', ['admin', 'trainer', 'student'])->default('student');
            $table->string('phone')->nullable();
            $table->enum('gender', ['male', 'female'])->nullable();

            // Student-only fields
            $table->integer('student_id')->nullable();
            $table->string('class_name')->nullable();
            $table->string('generation')->nullable();
            $table->string('province')->nullable();

            // Foreign Keys
            $table->foreignId('avatar_id')->nullable()->constrained('avatars')->nullOnDelete();
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();

            $table->boolean('is_active')->default(true);

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
