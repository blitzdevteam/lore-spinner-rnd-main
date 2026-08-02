<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use Illuminate\Support\Facades\Cache;

final class GameTurnStreamStore
{
    private const TTL = 180;

    public function key(string $gameId, string $sourcePromptId): string
    {
        return "game-turn-stream:{$gameId}:{$sourcePromptId}";
    }

    public function start(string $gameId, string $sourcePromptId): void
    {
        $this->update($gameId, $sourcePromptId, static fn (array $state): array => [
            'start' => true,
            'text' => '',
            'completed' => false,
            'final' => false,
            'error' => null,
            'updated_at' => microtime(true),
        ]);
    }

    public function append(string $gameId, string $sourcePromptId, string $text): void
    {
        if ($text === '') {
            return;
        }

        $this->update($gameId, $sourcePromptId, static function (array $state) use ($text): array {
            $state['text'] = (string) ($state['text'] ?? '').$text;
            $state['updated_at'] = microtime(true);

            return $state;
        });
    }

    public function complete(string $gameId, string $sourcePromptId): void
    {
        $this->update($gameId, $sourcePromptId, static function (array $state): array {
            $state['completed'] = true;
            $state['final'] = true;
            $state['updated_at'] = microtime(true);

            return $state;
        });
    }

    public function fail(string $gameId, string $sourcePromptId, string $message): void
    {
        $this->update($gameId, $sourcePromptId, static function (array $state) use ($message): array {
            $state['completed'] = true;
            $state['final'] = false;
            $state['error'] = $message;
            $state['updated_at'] = microtime(true);

            return $state;
        });
    }

    /**
     * @return array{start:bool,text:string,completed:bool,final:bool,error:?string,updated_at:float}|null
     */
    public function snapshot(string $gameId, string $sourcePromptId): ?array
    {
        $state = Cache::get($this->key($gameId, $sourcePromptId));

        return is_array($state) ? $state : null;
    }

    /**
     * @param  callable(array<string,mixed>):array<string,mixed>  $callback
     */
    private function update(string $gameId, string $sourcePromptId, callable $callback): void
    {
        $key = $this->key($gameId, $sourcePromptId);
        $lock = Cache::lock("{$key}:lock", 10);

        try {
            $lock->block(2);
            $state = Cache::get($key, [
                'start' => true,
                'text' => '',
                'completed' => false,
                'final' => false,
                'error' => null,
                'updated_at' => microtime(true),
            ]);
            Cache::put($key, $callback(is_array($state) ? $state : []), self::TTL);
        } finally {
            $lock->release();
        }
    }
}
