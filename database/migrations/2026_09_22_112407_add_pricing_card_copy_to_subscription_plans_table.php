<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->string('bonus_label')->nullable()->after('tagline');
            $table->text('action_hint')->nullable()->after('action_limit');
            $table->string('featured_label')->nullable()->after('is_featured');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_plans', function (Blueprint $table): void {
            $table->dropColumn(['bonus_label', 'action_hint', 'featured_label']);
        });
    }
};
