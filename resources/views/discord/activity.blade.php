<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>LoreSpinner</title>
        <style>
            :root {
                color-scheme: dark;
                --ink: #080a0b;
                --text: #e7ebe8;
                --muted: #89918e;
                --cyan: #82c4ca;
                --gold: #e5ad5d;
                --line: rgb(193 216 211 / 0.16);
            }

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                background: var(--ink);
                color: var(--text);
                font-family: Inter, ui-sans-serif, system-ui, sans-serif;
            }

            main {
                width: min(40rem, 100%);
                margin: 0 auto;
                padding: 1.5rem 1.25rem 2.5rem;
            }

            header {
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 1rem;
                margin-bottom: 1.5rem;
            }

            h1 {
                margin: 0;
                font-size: 1.15rem;
                font-weight: 650;
            }

            #player {
                margin: 0;
                color: var(--cyan);
                font-size: 0.78rem;
            }

            #status {
                margin: 0 0 1.25rem;
                color: var(--muted);
                font-size: 0.9rem;
                line-height: 1.5;
            }

            #status[data-tone="error"] {
                color: var(--gold);
            }

            #picker[hidden] {
                display: none;
            }

            .label {
                margin: 1.5rem 0 0.75rem;
                color: var(--muted);
                font-size: 0.72rem;
                font-weight: 700;
                letter-spacing: 0.14em;
                text-transform: uppercase;
            }

            #stories {
                display: grid;
                gap: 0.6rem;
            }

            #stories label,
            #modes label {
                display: block;
                padding: 0.9rem 1rem;
                border: 1px solid rgb(130 196 202 / 0.28);
                border-radius: 0.75rem;
                background: rgb(130 196 202 / 0.06);
                cursor: pointer;
            }

            #stories label:has(input:checked),
            #modes label:has(input:checked) {
                border-color: rgb(130 196 202 / 0.85);
            }

            #stories input,
            #modes input {
                margin-right: 0.5rem;
            }

            .story-title {
                font-family: Georgia, "Iowan Old Style", Palatino, serif;
                font-size: 1.1rem;
            }

            .story-teaser {
                display: block;
                margin-top: 0.35rem;
                color: var(--muted);
                font-size: 0.82rem;
                line-height: 1.45;
            }

            #modes {
                display: grid;
                gap: 0.6rem;
            }

            #open-lobby {
                width: 100%;
                margin-top: 1.5rem;
                padding: 0.9rem 1rem;
                border: 0;
                border-radius: 0.75rem;
                background: var(--gold);
                color: var(--ink);
                font: inherit;
                font-weight: 700;
                cursor: pointer;
            }

            #open-lobby:disabled {
                opacity: 0.5;
                cursor: wait;
            }
        </style>
    </head>
    <body
        data-client-id="{{ $clientId }}"
        data-session-url="{{ route('discord.activity.session', [], false) }}"
        data-lobby-url="{{ route('discord.activity.lobby.show', [], false) }}"
    >
        <main>
            <header>
                <h1>LoreSpinner</h1>
                <p id="player"></p>
            </header>

            <p id="status" aria-live="polite">Open this activity from a Discord voice channel to play together.</p>

            <form id="picker" hidden>
                <p>No story is running in this voice channel yet. Pick one and everyone here joins your lobby.</p>

                <p class="label">Story</p>
                <div id="stories">
                    @forelse ($stories as $story)
                        <label>
                            <input type="radio" name="story" value="{{ $story['slug'] }}" @checked($loop->first)>
                            <span class="story-title">{{ $story['title'] }}</span>
                            @if ($story['teaser'])
                                <span class="story-teaser">{{ \Illuminate\Support\Str::limit($story['teaser'], 160) }}</span>
                            @endif
                        </label>
                    @empty
                        <p>No story is ready for multiplayer yet.</p>
                    @endforelse
                </div>

                <p class="label">Mode</p>
                <div id="modes">
                    @foreach ($modes as $mode => $label)
                        <label>
                            <input type="radio" name="mode" value="{{ $mode }}" @checked($loop->first)>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>

                <button id="open-lobby" type="submit" @disabled($stories === [])>Open the lobby</button>
            </form>
        </main>
        <script type="module" src="/discord/activity.js?v=7"></script>
    </body>
</html>
