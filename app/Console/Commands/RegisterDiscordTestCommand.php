<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Discord\SoloTestStory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

final class RegisterDiscordTestCommand extends Command
{
    protected $signature = 'discord:register-test-command';

    protected $description = 'Register the /play slash command on the Discord test application';

    public function handle(SoloTestStory $story): int
    {
        $applicationId = (string) config('discord.application_id');
        $token = (string) config('discord.bot_token');
        $guildId = (string) config('discord.guild_id');
        $definition = $story->commandDefinition();

        if ($applicationId === '' || $token === '') {
            $this->line((string) json_encode($definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->warn('Add DISCORD_APPLICATION_ID and DISCORD_BOT_TOKEN to register /play. Without a bot token, create that command in the Discord developer portal.');

            return self::FAILURE;
        }

        $url = $guildId === ''
            ? "https://discord.com/api/v10/applications/{$applicationId}/commands"
            : "https://discord.com/api/v10/applications/{$applicationId}/guilds/{$guildId}/commands";

        $response = Http::withHeaders([
            'Authorization' => 'Bot '.$token,
        ])->acceptJson()->post($url, $definition);

        if ($response->failed()) {
            $this->error('Discord rejected the command: '.$response->body());

            return self::FAILURE;
        }

        $target = $guildId === '' ? 'globally' : "on guild {$guildId}";
        $this->info("Registered /play {$target}.");

        return self::SUCCESS;
    }
}
