<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();

            // 🔥 Basic Info
            $table->string('name');
            $table->string('email')->unique();

            // 📊 SaaS Plan
            $table->enum('subscription', ['free', 'premium'])->default('free');

            // 🟢 Status
            $table->boolean('is_active')->default(true);

            // 🌍 Optional Fields
            $table->string('industry')->nullable();
            $table->string('country')->nullable();

            // 🔥 Future Ready (optional but useful)
            $table->string('phone')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('companies');
    }
};