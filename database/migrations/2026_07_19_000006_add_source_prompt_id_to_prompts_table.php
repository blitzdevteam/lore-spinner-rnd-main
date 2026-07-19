<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table): void {
            $table->ulid('source_prompt_id')->nullable()->after('game_id');
            $table->unique(['game_id', 'source_prompt_id']);
        });
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table): void {
            $table->dropUnique(['game_id', 'source_prompt_id']);
            $table->dropColumn('source_prompt_id');
        });
    }
};
