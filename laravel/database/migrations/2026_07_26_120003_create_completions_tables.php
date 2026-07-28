<?php

declare(strict_types=1);

use App\Enums\CompletionStatus;
use App\Enums\DisputeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Bir kun, bir challenge, bir user hisoboti — SPEC §4, §5
        Schema::create('completions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('challenge_id')->constrained('challenges')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->date('day')->index();
            $table->string('status')->default(CompletionStatus::Pending->value);
            $table->string('file_id')->nullable();      // Telegram file_id (rasm Telegram'da)
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['challenge_id', 'user_id', 'day']);
        });

        // Har tekshiruv qarori log qilinadi (audit) — SPEC §5
        Schema::create('verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('completion_id')->constrained('completions')->cascadeOnDelete();
            $table->foreignId('verifier_id')->constrained('users')->cascadeOnDelete();
            $table->boolean('approve');
            $table->boolean('is_dispute_review')->default(false);
            $table->timestamps();
        });

        // Nizo — SPEC §12–13
        Schema::create('disputes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('completion_id')->constrained('completions')->cascadeOnDelete();
            $table->foreignId('opened_by')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default(DisputeStatus::Open->value);
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disputes');
        Schema::dropIfExists('verifications');
        Schema::dropIfExists('completions');
    }
};
