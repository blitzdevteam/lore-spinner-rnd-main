<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->deleteExistingInvestorUsers();

        Schema::table('users', function (Blueprint $table): void {
            $table->string('type')->default('user')->after('id');
            $table->foreignId('subscription_plan_id')
                ->nullable()
                ->after('is_active')
                ->constrained('subscription_plans')
                ->nullOnDelete();
            $table->unsignedTinyInteger('duration_months')->default(1)->after('subscription_plan_id');
            $table->boolean('access_enabled')->default(false)->after('duration_months');
            $table->text('notes')->nullable()->after('access_enabled');
            $table->timestamp('access_granted_at')->nullable()->after('notes');
            $table->timestamp('expires_at')->nullable()->after('access_granted_at');
            $table->timestamp('access_blocked_at')->nullable()->after('expires_at');
            $table->timestamp('credentials_set_at')->nullable()->after('access_blocked_at');
            $table->index('type');
        });

        Schema::dropIfExists('investors');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['type']);
            $table->dropConstrainedForeignId('subscription_plan_id');
            $table->dropColumn([
                'type',
                'duration_months',
                'access_enabled',
                'notes',
                'access_granted_at',
                'expires_at',
                'access_blocked_at',
                'credentials_set_at',
            ]);
        });

        Schema::create('investors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->boolean('access_enabled')->default(true);
            $table->text('notes')->nullable();
            $table->timestamp('access_granted_at')->nullable();
            $table->timestamp('access_blocked_at')->nullable();
            $table->timestamps();
        });
    }

    private function deleteExistingInvestorUsers(): void
    {
        if (! Schema::hasTable('investors')) {
            return;
        }

        /** @var Collection<int, int> $userIds */
        $userIds = DB::table('investors')->pluck('user_id');

        if ($userIds->isEmpty()) {
            return;
        }

        $this->deleteUserSubscriptions($userIds);
        $this->deleteUserGames($userIds);
        $this->deleteUserLobbies($userIds);

        DB::table('investors')->whereIn('user_id', $userIds)->delete();
        DB::table('users')->whereIn('id', $userIds)->delete();
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function deleteUserGames(Collection $userIds): void
    {
        if (! Schema::hasTable('games')) {
            return;
        }

        $gameIds = DB::table('games')->whereIn('user_id', $userIds)->pluck('id');

        if ($gameIds->isEmpty()) {
            return;
        }

        if (Schema::hasTable('multiplayer_lobbies')) {
            DB::table('multiplayer_lobbies')
                ->whereIn('game_id', $gameIds)
                ->update([
                    'game_id' => null,
                    'current_source_prompt_id' => null,
                ]);
        }

        if (Schema::hasTable('prompts')) {
            DB::table('prompts')->whereIn('game_id', $gameIds)->delete();
        }

        DB::table('games')->whereIn('id', $gameIds)->delete();
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function deleteUserLobbies(Collection $userIds): void
    {
        if (! Schema::hasTable('multiplayer_lobbies')) {
            return;
        }

        $lobbyIds = DB::table('multiplayer_lobbies')->whereIn('host_user_id', $userIds)->pluck('id');

        if ($lobbyIds->isEmpty()) {
            return;
        }

        DB::table('multiplayer_lobbies')
            ->whereIn('id', $lobbyIds)
            ->update([
                'game_id' => null,
                'current_source_prompt_id' => null,
                'current_arbiter_member_id' => null,
            ]);

        DB::table('multiplayer_lobbies')->whereIn('id', $lobbyIds)->delete();
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function deleteUserSubscriptions(Collection $userIds): void
    {
        if (! Schema::hasTable('subscriptions')) {
            return;
        }

        $subscriptionIds = DB::table('subscriptions')->whereIn('user_id', $userIds)->pluck('id');

        if (Schema::hasTable('subscription_usage_events')) {
            DB::table('subscription_usage_events')->whereIn('user_id', $userIds)->delete();
        }

        if (Schema::hasTable('subscription_entitlements')) {
            DB::table('subscription_entitlements')->whereIn('user_id', $userIds)->delete();
        }

        if (Schema::hasTable('subscription_items') && $subscriptionIds->isNotEmpty()) {
            DB::table('subscription_items')->whereIn('subscription_id', $subscriptionIds)->delete();
        }

        DB::table('subscriptions')->whereIn('user_id', $userIds)->delete();
    }
};
