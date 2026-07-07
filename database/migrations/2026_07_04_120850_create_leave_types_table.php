<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');                       // e.g. "Sick Leave", "Annual Leave"
            $table->string('code')->unique();              // e.g. "sick", "annual"
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('max_days_per_year')->default(0);
            $table->boolean('requires_attachment')->default(false); // e.g. sick note for sick leave
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
