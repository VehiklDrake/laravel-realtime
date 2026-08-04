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
        Schema::create('rounds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('game_id')->constrained()->cascadeOnDelete();
            $table->foreignId('drawer_id')->constrained('players')->restrictOnDelete();
            $table->unsignedSmallInteger('number');
            $table->string('word', 80);
            $table->timestamp('started_at');
            $table->timestamp('ends_at')->index();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->unique(['game_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rounds');
    }
};
