<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('session_adaptations', function (Blueprint $table): void {
            $table->timestamp('boiler_drafting_started_at')
                ->nullable()
                ->after('boiler_error');
        });
    }

    public function down(): void
    {
        Schema::table('session_adaptations', function (Blueprint $table): void {
            $table->dropColumn('boiler_drafting_started_at');
        });
    }
};
