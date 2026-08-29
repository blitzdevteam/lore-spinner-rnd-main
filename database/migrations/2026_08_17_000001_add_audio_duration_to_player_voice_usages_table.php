<?php

declare(strict_types=1);

use App\Services\Runtime\Mp3DurationCalculator;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_voice_usages', function (Blueprint $table): void {
            $table->unsignedInteger('audio_duration_ms')->default(0);
        });

        $duration = app(Mp3DurationCalculator::class);

        DB::table('player_voice_usages')
            ->where('audio_duration_ms', 0)
            ->orderBy('id')
            ->chunkById(100, function ($usages) use ($duration): void {
                foreach ($usages as $usage) {
                    if (! is_string($usage->provider) || ! is_string($usage->prompt_reference_id)) {
                        continue;
                    }

                    $path = "tts/{$usage->provider}/{$usage->prompt_reference_id}.mp3";
                    try {
                        if (! Storage::disk('local')->exists($path)) {
                            continue;
                        }

                        $milliseconds = $duration->milliseconds(Storage::disk('local')->get($path));
                    } catch (\Throwable) {
                        continue;
                    }

                    if ($milliseconds === 0) {
                        continue;
                    }

                    DB::table('player_voice_usages')
                        ->where('id', $usage->id)
                        ->update(['audio_duration_ms' => $milliseconds]);
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('player_voice_usages', function (Blueprint $table): void {
            $table->dropColumn('audio_duration_ms');
        });
    }
};
