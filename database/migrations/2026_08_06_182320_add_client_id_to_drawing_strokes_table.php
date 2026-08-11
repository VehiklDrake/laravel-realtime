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
        Schema::table('drawing_strokes', function (Blueprint $table) {
            $table->uuid('client_id')->nullable()->unique();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('drawing_strokes', function (Blueprint $table) {
            $table->dropUnique(['client_id']);
            $table->dropColumn('client_id');
        });
    }
};
