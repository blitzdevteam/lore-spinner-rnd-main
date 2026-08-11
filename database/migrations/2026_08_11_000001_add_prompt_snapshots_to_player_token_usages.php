<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_token_usages', function (Blueprint $table): void {
            $table->longText('system_prompt')->nullable()->after('kind');
            $table->longText('generated_output')->nullable()->after('system_prompt');
        });

        DB::table('player_token_usages')
            ->whereNotNull('prompt_id')
            ->orderBy('id')
            ->chunkById(200, function ($usages): void {
                $outputs = DB::table('prompts')
                    ->whereIn('id', $usages->pluck('prompt_id')->filter()->all())
                    ->pluck('response', 'id');

                foreach ($usages as $usage) {
                    $promptId = $usage->prompt_id;
                    $usageId = $usage->id;
                    if (is_string($promptId) && is_string($usageId)) {
                        $output = $outputs->get($promptId);
                    } else {
                        $output = null;
                    }

                    if (is_string($output)) {
                        DB::table('player_token_usages')
                            ->where('id', $usageId)
                            ->update(['generated_output' => $output]);
                    }
                }
            }, 'id');
    }

    public function down(): void
    {
        Schema::table('player_token_usages', function (Blueprint $table): void {
            $table->dropColumn(['system_prompt', 'generated_output']);
        });
    }
};
