<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Garov — duel boshlanishidan oldin kelishiladigan yutuq va jazo (SPEC §17).
 *
 *   battle_stakes      — har o'yinchining har garov turi bo'yicha VARIANTI.
 *                        Tanlangani `chosen_via` bilan belgilanadi.
 *   battle_stake_votes — kim qaysi variantni tanlagani (o'yinchi × tur bo'yicha bitta).
 *
 * Jazo chegarasi battle'da saqlanadi: keyin standart o'zgarsa ham, eski
 * duel kelishilgan qoida bo'yicha yopilsin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('battles', function (Blueprint $table) {
            $table->unsignedTinyInteger('penalty_threshold')->default(50)->after('winner_id');
        });

        Schema::table('battle_participants', function (Blueprint $table) {
            // Yakunda muhrlanadi — arxivda o'zgarmas bo'lib qolsin
            $table->unsignedInteger('habits_done')->nullable()->after('score');
            $table->unsignedInteger('habits_expected')->nullable()->after('habits_done');
            $table->boolean('penalized')->nullable()->after('habits_expected');
        });

        Schema::create('battle_stakes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained('battles')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->foreignId('proposed_by')->constrained('users')->cascadeOnDelete();
            $table->string('text', 200);
            $table->string('chosen_via', 16)->nullable();
            $table->timestamps();

            $table->unique(['battle_id', 'kind', 'proposed_by']);
        });

        Schema::create('battle_stake_votes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained('battles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->foreignId('stake_id')->constrained('battle_stakes')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['battle_id', 'user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_stake_votes');
        Schema::dropIfExists('battle_stakes');

        Schema::table('battle_participants', function (Blueprint $table) {
            $table->dropColumn(['habits_done', 'habits_expected', 'penalized']);
        });

        Schema::table('battles', function (Blueprint $table) {
            $table->dropColumn('penalty_threshold');
        });
    }
};
