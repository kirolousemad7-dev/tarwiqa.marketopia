<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_types', function (Blueprint $table): void {
            if (! Schema::hasColumn('service_types', 'widget_type')) {
                $table->string('widget_type', 40)->nullable();
            }
            if (! Schema::hasColumn('service_types', 'visibility')) {
                $table->string('visibility', 40)->nullable();
            }
            if (! Schema::hasColumn('service_types', 'primary_owner')) {
                $table->string('primary_owner')->nullable();
            }
            if (! Schema::hasColumn('service_types', 'sla_group')) {
                $table->string('sla_group')->nullable();
            }
            if (! Schema::hasColumn('service_types', 'description')) {
                $table->text('description')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_types', function (Blueprint $table): void {
            $table->dropColumn(['widget_type', 'visibility', 'primary_owner', 'sla_group', 'description']);
        });
    }
};
