<?php

declare(strict_types=1);

use App\Enums\BattleStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Battle = konteyner (ko'p challenge, umumiy ball, 1 g'olib) — SPEC §3
        Schema::create('battles', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default(BattleStatus::Pending->value)->index();
            $table->unsignedSmallInteger('period_days')->default(7);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('timezone')->default('Asia/Tashkent');
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('winner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invite_token', 32)->unique();
            $table->timestamps();
        });

        // Pivot — 1v1 hozir, guruh uchun ochiq — SPEC §3
        Schema::create('battle_participants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('battle_id')->constrained('battles')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('accepted')->default(false);
            $table->decimal('score', 8, 1)->default(0);
            $table->timestamps();

            $table->unique(['battle_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('battle_participants');
        Schema::dropIfExists('battles');
    }
};
