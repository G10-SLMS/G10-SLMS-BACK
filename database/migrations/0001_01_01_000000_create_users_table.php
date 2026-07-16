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
            $table->string('avatar')->nullable();
            $table->enum('role', ['admin', 'trainer', 'student'])->default('student');
            $table->foreignId('trainer_id')->nullable()->constrained('users')->nullOnDelete();

            // Applies to all roles
            $table->string('phone')->nullable();

            // Student-only fields
            $table->enum('gender', ['male', 'female'])->nullable();
            $table->string('class')->nullable();
            $table->string('generation')->nullable();
            $table->string('province')->nullable();

            $table->rememberToken();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
