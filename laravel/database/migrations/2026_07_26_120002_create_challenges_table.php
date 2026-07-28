<?php

declare(strict_types=1);

use App\Enums\Cadence;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Simmetrik challenge (ikkalasi bajaradi) — SPEC §3
        Schema::create('challenges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained('battles')->cascadeOnDelete();
            $table->string('template_key')->nullable();  // 'read'|'sport'|... yoki null (erkin)
            $table->string('name')->default('');
            $table->string('icon', 16)->default('🎯');
            $table->string('cadence')->default(Cadence::Daily->value);
            $table->json('weekdays')->nullable();          // 0=Du..6=Ya
            $table->date('start_date');                    // har challenge o'z boshlanishi
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenges');
    }
};
