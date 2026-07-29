<?php

declare(strict_types=1);

use App\Enums\ProofType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            // Taklif (mid-battle) — do'st qabul qilmaguncha pending (SPEC §19–20)
            $table->boolean('pending')->default(false)->after('active');
            $table->unsignedBigInteger('proposed_by')->nullable()->after('pending');
            // Isbot turi — kamera yoki skrinshot (SPEC §9)
            $table->string('proof_type')->default(ProofType::Camera->value)->after('proposed_by');
        });
    }

    public function down(): void
    {
        Schema::table('challenges', function (Blueprint $table) {
            $table->dropColumn(['pending', 'proposed_by', 'proof_type']);
        });
    }
};
