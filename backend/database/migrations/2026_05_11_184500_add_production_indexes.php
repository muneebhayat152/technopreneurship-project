<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('complaints', function (Blueprint $table) {
            $table->index(['company_id', 'created_at'], 'complaints_company_created_idx');
            $table->index(['company_id', 'status', 'created_at'], 'complaints_company_status_created_idx');
            $table->index(['company_id', 'priority', 'created_at'], 'complaints_company_priority_created_idx');
            $table->index(['company_id', 'sentiment', 'created_at'], 'complaints_company_sentiment_created_idx');
            $table->index(['company_id', 'category', 'created_at'], 'complaints_company_category_created_idx');
            $table->index(['user_id', 'created_at'], 'complaints_user_created_idx');
            $table->index(['issue_cluster_id', 'created_at'], 'complaints_cluster_created_idx');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->index(['company_id', 'triggered_at'], 'alerts_company_triggered_idx');
            $table->index(['company_id', 'is_read', 'triggered_at'], 'alerts_company_read_triggered_idx');
        });

        Schema::table('issue_clusters', function (Blueprint $table) {
            $table->index(['company_id', 'complaint_count'], 'issue_clusters_company_count_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('deleted_at', 'users_deleted_at_idx');
            $table->index(['company_id', 'deleted_at'], 'users_company_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_company_deleted_idx');
            $table->dropIndex('users_deleted_at_idx');
        });

        Schema::table('issue_clusters', function (Blueprint $table) {
            $table->dropIndex('issue_clusters_company_count_idx');
        });

        Schema::table('alerts', function (Blueprint $table) {
            $table->dropIndex('alerts_company_read_triggered_idx');
            $table->dropIndex('alerts_company_triggered_idx');
        });

        Schema::table('complaints', function (Blueprint $table) {
            $table->dropIndex('complaints_cluster_created_idx');
            $table->dropIndex('complaints_user_created_idx');
            $table->dropIndex('complaints_company_category_created_idx');
            $table->dropIndex('complaints_company_sentiment_created_idx');
            $table->dropIndex('complaints_company_priority_created_idx');
            $table->dropIndex('complaints_company_status_created_idx');
            $table->dropIndex('complaints_company_created_idx');
        });
    }
};

