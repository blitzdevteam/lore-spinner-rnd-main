<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'writers', 'creators', 'managers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->unsignedTinyInteger('failed_login_attempts')->default(0);
                $table->timestamp('locked_until')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'writers', 'creators', 'managers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->dropColumn(['failed_login_attempts', 'locked_until']);
            });
        }
    }
};
