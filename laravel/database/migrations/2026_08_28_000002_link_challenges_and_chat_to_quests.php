<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Challenge va chat endi ikki konteynerdan birortasiga tegishli:
 * battle (duel) YOKI quest (missiya). Aynan bittasi to'ldiriladi —
 * invariant App\Models\Challenge::context() da kuchga kiradi.
 *
 * Isbot quvuri (completions → verifications → disputes) o'zgarishsiz qoladi:
 * u faqat challenge_id ga bog'langan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->foreignId('battle_id')->nullable()->change();
            $table->foreignId('quest_id')->nullable()->after('battle_id')
                ->constrained('quests')->cascadeOnDelete();
        });

        Schema::table('chat_messages', function (Blueprint $table) {
            $table->foreignId('battle_id')->nullable()->change();
            $table->foreignId('quest_id')->nullable()->after('battle_id')
                ->constrained('quests')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('chat_messages', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quest_id');
        });

        Schema::table('challenges', function (Blueprint $table) {
            $table->dropConstrainedForeignId('quest_id');
        });
    }
};
