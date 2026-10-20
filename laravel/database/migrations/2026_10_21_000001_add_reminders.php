<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Eslatmalar (SPEC §18).
 *
 *   challenges.remind_at — odat vaqti 'HH:MM' (Toshkent), ixtiyoriy.
 *   reminder_logs        — kimga, qaysi eslatma, qaysi kun uchun yuborilgan.
 *                          Unique kalit bir xil eslatma ikki marta ketmasligini
 *                          ta'minlaydi (cron ikki marta yugursa ham).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->string('remind_at', 5)->nullable()->after('weekdays');
        });

        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('kind', 16);     // habit | evening | late
            $table->string('ref', 32);      // habit: challenge id; boshqalar: '-'
            $table->date('day');
            $table->timestamp('created_at')->nullable();

            $table->unique(['user_id', 'kind', 'ref', 'day']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');

        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn('remind_at');
        });
    }
};
