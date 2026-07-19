<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('playable_session_cuts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('story_id')->constrained()->cascadeOnDelete();
            $table->foreignId('session_adaptation_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('session_number');
            $table->string('cut_key')->default('standard');
            $table->string('status')->default('finalized');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('playable_session_cuts');
    }
};
