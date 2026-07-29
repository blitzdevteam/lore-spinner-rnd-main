<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Persist per-turn Boiler moment fields on response prompts so play evidence
 * does not depend on ephemeral narration logs.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prompts', function (Blueprint $table): void {
            $table->string('moment_key')->nullable()->after('session_number');
            $table->boolean('exit_condition_met')->nullable()->after('moment_key');
            $table->string('moment_result')->nullable()->after('exit_condition_met');
            $table->text('moment_footnote')->nullable()->after('moment_result');
        });
    }

    public function down(): void
    {
        Schema::table('prompts', function (Blueprint $table): void {
            $table->dropColumn([
                'moment_key',
                'exit_condition_met',
                'moment_result',
                'moment_footnote',
            ]);
        });
    }
};
