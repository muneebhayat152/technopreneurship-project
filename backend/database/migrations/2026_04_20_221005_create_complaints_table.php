<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('complaints', function (Blueprint $table) {
            $table->id();

            // 🔗 Multi-tenant (company)
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            // 🔗 User who created complaint
            $table->foreignId('user_id')->constrained()->onDelete('cascade');

            // 🧾 Core complaint text
            $table->text('complaint_text');

            // 😊 Sentiment (AI result)
            $table->enum('sentiment', ['positive', 'neutral', 'negative'])->nullable();

            // 🧠 Category (AI grouping)
            $table->string('category')->nullable();

            // 📊 Status (workflow)
            $table->enum('status', ['open', 'in_progress', 'resolved'])->default('open');

            // ⚠️ Priority
            $table->enum('priority', ['low', 'medium', 'high'])->default('medium');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('complaints');
    }
};