<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_settings', function (Blueprint $table): void {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });

        Schema::create('outbound_dispatches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 20);
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('status', 40);
            $table->text('error')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->string('ban_type', 20)->nullable()->after('status');
        });

        Schema::table('conversations', function (Blueprint $table): void {
            $table->string('box', 20)->default('inbox')->index();
            $table->boolean('is_starred')->default(false);
        });

        Schema::table('inquiry_logs', function (Blueprint $table): void {
            $table->string('governorate_name')->nullable();
            $table->string('widget_name')->nullable();
            $table->string('category_name')->nullable();
            $table->string('offer_name')->nullable();
            $table->string('session_result')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('inquiry_logs', function (Blueprint $table): void {
            $table->dropColumn(['governorate_name', 'widget_name', 'category_name', 'offer_name', 'session_result']);
        });
        Schema::table('conversations', function (Blueprint $table): void {
            $table->dropColumn(['box', 'is_starred']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('ban_type');
        });
        Schema::dropIfExists('outbound_dispatches');
        Schema::dropIfExists('admin_settings');
    }
};
