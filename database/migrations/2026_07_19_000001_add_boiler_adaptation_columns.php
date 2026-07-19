<?php

declare(strict_types=1);

use App\Enums\BoilerStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('story_adaptations', function (Blueprint $table): void {
            $table->boolean('boiler_enabled')->default(false)->after('adaptation_status');
        });

        Schema::table('session_adaptations', function (Blueprint $table): void {
            $table->longText('boiler_draft')->nullable()->after('entry_point_diagnosis');
            $table->longText('boiler_final_cut')->nullable()->after('session_close_design');
            $table->string('boiler_status')->default(BoilerStatus::PENDING->value)->after('session_status');
            $table->text('boiler_error')->nullable()->after('boiler_status');
            $table->timestamp('boiler_generated_at')->nullable()->after('boiler_error');
            $table->timestamp('boiler_finalized_at')->nullable()->after('boiler_generated_at');
        });
    }

    public function down(): void
    {
        Schema::table('session_adaptations', function (Blueprint $table): void {
            $table->dropColumn([
                'boiler_draft',
                'boiler_final_cut',
                'boiler_status',
                'boiler_error',
                'boiler_generated_at',
                'boiler_finalized_at',
            ]);
        });

        Schema::table('story_adaptations', function (Blueprint $table): void {
            $table->dropColumn('boiler_enabled');
        });
    }
};
