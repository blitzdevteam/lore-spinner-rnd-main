<?php

declare(strict_types=1);

namespace App\Services;

use App\Ai\Agents\Chaos\NativeObjectSchema;
use App\Models\Game;
use App\Models\Prompt;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Cache;
use Prism\Prism\Facades\Prism;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use RuntimeException;
use Throwable;

final class GameRecapService
{
    private const PRIMARY_MODEL = 'gpt-5-nano';

    private const FALLBACK_MODEL = 'claude-haiku-4-5';

    /**
     * @return array{recap:string}
     */
    public function generate(Game $game): array
    {
        $latestPrompt = $this->latestPrompt($game);

        if ($latestPrompt === null) {
            return ['recap' => 'The story is just beginning. Your first choice has not been made yet.'];
        }

        $cacheKey = "game-recap:{$game->id}:{$latestPrompt->id}:".((int) $game->current_session_number);

        /** @var array{recap:string}|null $cached */
        $cached = Cache::get($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        return Cache::lock("{$cacheKey}:lock", 45)->block(10, function () use ($game, $cacheKey): array {
            /** @var array{recap:string}|null $cachedAfterLock */
            $cachedAfterLock = Cache::get($cacheKey);
            if ($cachedAfterLock !== null) {
                return $cachedAfterLock;
            }

            try {
                $result = ['recap' => $this->callModel($game, self::PRIMARY_MODEL)];
            } catch (Throwable $primaryFailure) {
                report($primaryFailure);

                try {
                    $result = ['recap' => $this->callModel($game, self::FALLBACK_MODEL)];
                } catch (Throwable $fallbackFailure) {
                    report($fallbackFailure);

                    throw new RuntimeException('The recap could not be generated.', previous: $fallbackFailure);
                }
            }

            Cache::put($cacheKey, $result, now()->addMinutes(30));

            return $result;
        });
    }

    private function latestPrompt(Game $game): ?Prompt
    {
        return $game->prompts()
            ->whereNotNull('response')
            ->where('response', '!=', '')
            ->orderByDesc('id')
            ->first();
    }

    private function callModel(Game $game, string $model): string
    {
        $provider = $model === self::FALLBACK_MODEL ? 'anthropic' : 'openai';
        $schema = (new JsonSchemaTypeFactory)
            ->string()
            ->required()
            ->description('One plain-text story re-entry paragraph, 80 to 130 words.');
        $request = Prism::structured()
            ->using($provider, $model)
            ->withSchema(new NativeObjectSchema(['recap' => $schema], 'game_recap'))
            ->withMessages([
                new SystemMessage($this->systemPrompt()),
                new UserMessage($this->context($game)),
            ])
            ->withClientOptions(['timeout' => 45, 'connect_timeout' => 10])
            ->withMaxTokens(300);

        if ($provider === 'openai') {
            $request = $request->withProviderOptions(['schema' => ['strict' => true]]);
        }

        /** @var array<string, mixed> $structured */
        $structured = $request->asStructured()->structured ?? [];
        $recap = trim((string) ($structured['recap'] ?? ''));

        if ($recap === '') {
            throw new RuntimeException('The recap model returned empty text.');
        }

        return $recap;
    }

    private function systemPrompt(): string
    {
        return <<<'PROMPT'
You help a player re-enter an interactive story after they may have been away or mentally zoned out.
Write one compact, coherent recap in second person. Arrange the facts naturally so the player feels
present again: orient them in the current place and moment, remind them what they did that still matters,
restore the important people, objects, discoveries, and unresolved pressure, then end at what is live now.
Use only the supplied story state. Do not invent facts, reveal future events, mention unplayed choices,
give advice, ask what the player will do, or describe recap generation. Do not use headings, bullets,
labels, markdown, options, or a database-like sequence. This is a re-entry paragraph, not a plot dump.

VOICE BAN LIST:
No em dashes or double hyphens. No ellipses. No emoji.
Do not use “It’s not X, it’s Y.”, “No X. No Y. Just Z.”, balanced rule-of-three semicolon rhetoric,
mid-sentence rhetorical check-ins, trailing “like [metaphor]” similes, contrast framing, or generic uplift.
Do not use tapestry, delve, underscore, highlight, showcase, intricate, swift, meticulous, adept,
just as a softener, resonates, tracks, matters, lands, “And honestly”, look, really, woven, weaving,
wove, meaningful, nestled, tucked away, etch, etched, or navigate for emotional situations.
Do not default to ghosts, spectral, shadow, whisper, quiet, hum, echo, liminal, or phantom as atmosphere.
Do not use “Something shifted”, “Something clicked”, “Something broke”, “something in”,
breath-they-didn’t-know,
eyes-searching-faces, silence-stretches, hearts-hammer, or mood-mirroring weather.
Do not use interior/cognitive narration such as realized, noticed, became aware, found oneself,
or couldn’t help but. Do not use meta-references, “the kind of moment”, “the kind of”,
“she would later remember”,
“little did she know”, “a reminder that”, “a testament to”, “it was clear that”, or declarative
emotion summaries such as “She was devastated”.
PROMPT;
    }

    private function context(Game $game): string
    {
        $game->loadMissing(['story', 'currentPlayableMoment']);
        $prompts = $game->prompts()
            ->whereNotNull('response')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['prompt', 'response', 'session_number'])
            ->reverse();

        $history = $prompts->map(function (Prompt $prompt): string {
            $player = trim((string) $prompt->prompt);
            $narration = $this->limit(strip_tags((string) $prompt->response), 600);

            return ($player !== '' ? "PLAYER STORY ACTION: {$player}\n" : '')."NARRATION: {$narration}";
        })->implode("\n\n");

        $state = (array) ($game->world_state ?? []);
        $state = array_intersect_key($state, array_flip([
            'location', 'conditions', 'items', 'object_states', 'relationship_updates',
            'world_flags', 'knowledge', 'unresolved_promises', 'player_style',
        ]));

        $moment = $game->currentPlayableMoment;
        $sceneCard = (array) ($moment?->scene_card ?? []);

        return implode("\n", [
            'STORY: '.($game->story?->title ?? 'Interactive story'),
            'SESSION: '.((int) ($game->current_session_number ?? 1)),
            'CURRENT MOMENT: '.($moment?->moment_key ?? 'current scene'),
            'CURRENT MOMENT TITLE: '.((string) ($sceneCard['title'] ?? '')),
            'MEANINGFUL CHOICE: '.((string) ($game->defining_choice_line ?? '')),
            'SYMBOLIC MEMORY: '.$this->limit((string) ($game->symbolic_memory ?? ''), 1200),
            'WORLD STATE: '.json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            'RECENT STORY HISTORY:',
            $history,
        ]);
    }

    private function limit(string $value, int $length): string
    {
        return mb_strlen($value) > $length ? mb_substr($value, 0, $length).' [truncated]' : $value;
    }
}
