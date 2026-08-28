<?php

declare(strict_types=1);

use App\Enums\QuestStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Missiya = yakka odat yo'li: egasi bajaradi, guvoh tekshiradi (asimmetrik).
        // Battle'dan farqi: g'olib yo'q, maqsad foizi bor.
        Schema::create('quests', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('status')->default(QuestStatus::Active->value)->index();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            // Guvoh taklif qabul qilgunicha null — missiya bunda ham ishlaydi
            // (tekshirilmagan isbot 24s dan keyin avto-tasdiq, SPEC §5).
            $table->foreignId('witness_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('period_days')->default(30);
            $table->date('start_date');
            $table->date('end_date');
            $table->string('timezone')->default('Asia/Tashkent');
            // Maqsad: davr oxirida shu foizga yetsa — muvaffaqiyat
            $table->unsignedTinyInteger('goal_percent')->default(80);
            $table->string('outcome')->nullable();   // achieved | missed (yakunlanganda)
            $table->string('invite_token', 32)->unique();
            $table->timestamps();

            $table->index(['owner_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quests');
    }
};
