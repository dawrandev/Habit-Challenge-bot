<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('completions', function (Blueprint $table) {
            $table->string('note', 280)->nullable()->after('file_id');
        });
    }

    public function down(): void
    {
        Schema::table('completions', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
