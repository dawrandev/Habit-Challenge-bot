<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Va'da bajarilganining isboti — foto yoki video (SPEC §17).
 *
 * Duel tugagach yutqazgan g'olibning yutug'ini (durangda — ikkalasi bir-
 * birinikini), jazoga tortilgan esa jazoni bajaradi va isbot yuklaydi.
 * Bu tarix: keyin "o'sha kuni nima bo'lgan edi" deb eslash uchun.
 *
 * Qarzning o'zi saqlanmaydi — u yakuniy natijadan hisoblanadi; bu yerda
 * faqat bajarilgani yoziladi (qarzdor × tur bo'yicha bitta).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stake_fulfillments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained('battles')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->foreignId('debtor_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_id');
            $table->string('media_type', 8); // photo | video
            $table->string('note', 280)->nullable();
            $table->timestamps();

            $table->unique(['battle_id', 'kind', 'debtor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stake_fulfillments');
    }
};
