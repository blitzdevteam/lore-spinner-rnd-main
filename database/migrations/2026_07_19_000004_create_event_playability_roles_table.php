<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_playability_roles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('playable_session_cut_id')->constrained()->cascadeOnDelete();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('primary_role');
            $table->string('included_moment_key')->nullable();
            $table->text('why');
            $table->text('reintroduction_note')->nullable();
            $table->timestamps();

            $table->unique(['playable_session_cut_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_playability_roles');
    }
};
