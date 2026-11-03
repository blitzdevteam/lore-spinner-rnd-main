<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Discord test bot
    |--------------------------------------------------------------------------
    |
    | The solo test answers Discord at POST /discord/interactions.
    | DISCORD_APPLICATION_ID and DISCORD_PUBLIC_KEY come from the test application.
    | A bot token is used only to register the /play command.
    |
    */

    'bot_token' => env('DISCORD_BOT_TOKEN'),

    'client_id' => env('DISCORD_CLIENT_ID'),

    'client_secret' => env('DISCORD_CLIENT_SECRET'),

    'public_key' => env('DISCORD_PUBLIC_KEY'),

    'application_id' => env('DISCORD_APPLICATION_ID'),

    'guild_id' => env('DISCORD_GUILD_ID'),

    'redirect_uri' => env('DISCORD_REDIRECT_URI'),

    /*
    |--------------------------------------------------------------------------
    | Discord Activity
    |--------------------------------------------------------------------------
    |
    | The voice channel activity runs the platform multiplayer lobby inside
    | Discord. Players sign in with Discord OAuth (DISCORD_CLIENT_SECRET) and
    | the bot token checks they are really in the activity instance.
    | The origin defaults to https://{application_id}.discordsays.com.
    |
    */

    'activity_origin' => env('DISCORD_ACTIVITY_ORIGIN'),

];
