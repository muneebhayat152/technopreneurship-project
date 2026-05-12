<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issue_clusters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->json('keywords')->nullable();
            $table->enum('severity', ['low', 'medium', 'high'])->default('medium');
            $table->enum('status', ['open', 'being_fixed', 'resolved'])->default('open');
            $table->unsignedInteger('complaint_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'severity']);
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->foreignId('issue_cluster_id')
                ->nullable()
                ->after('priority')
                ->constrained('issue_clusters')
                ->nullOnDelete();
        });

        Schema::create('issue_timeseries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_cluster_id')->constrained('issue_clusters')->cascadeOnDelete();
            $table->date('bucket_date');
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['issue_cluster_id', 'bucket_date']);
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('issue_cluster_id')->nullable()->constrained('issue_clusters')->nullOnDelete();
            $table->string('title');
            $table->text('body')->nullable();
            $table->enum('severity', ['info', 'warning', 'critical'])->default('warning');
            $table->boolean('is_read')->default(false);
            $table->timestamp('triggered_at')->useCurrent();
            $table->timestamps();

            $table->index(['company_id', 'is_read']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alerts');
        Schema::dropIfExists('issue_timeseries');

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropForeign(['issue_cluster_id']);
            $table->dropColumn('issue_cluster_id');
        });

        Schema::dropIfExists('issue_clusters');
    }
};
