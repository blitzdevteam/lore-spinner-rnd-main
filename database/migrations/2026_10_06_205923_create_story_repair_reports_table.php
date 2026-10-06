<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('story_repair_reports', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('session_adaptation_id')->constrained()->cascadeOnDelete();
            $table->string('source');
            $table->string('status');
            $table->longText('findings');
            $table->longText('patches');
            $table->longText('rebinds');
            $table->longText('rejected');
            $table->longText('backup')->nullable();
            $table->timestamp('applied_at')->nullable();
            $table->timestamp('reverted_at')->nullable();
            $table->timestamps();

            $table->index(['session_adaptation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('story_repair_reports');
    }
};
