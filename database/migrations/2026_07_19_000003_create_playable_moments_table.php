<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playable_moments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('playable_session_cut_id')->constrained()->cascadeOnDelete();
            $table->string('moment_key');
            $table->unsignedInteger('moment_order');
            $table->foreignId('anchor_event_id')->nullable()->constrained('events')->nullOnDelete();
            $table->json('source_event_ids');
            $table->json('scene_card');
            $table->timestamps();

            $table->unique(['playable_session_cut_id', 'moment_key']);
            $table->index(['playable_session_cut_id', 'moment_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playable_moments');
    }
};
