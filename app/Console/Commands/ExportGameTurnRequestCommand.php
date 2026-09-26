<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Prompt;
use App\Support\GameTurnRequestAssembler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class ExportGameTurnRequestCommand extends Command
{
    protected $signature = 'game:export-turn-request
        {game : Game ULID}
        {--turn= : 1-based index among response rows (orderBy id) in session}
        {--response-prompt-id= : Response prompt ULID (overrides --turn)}
        {--session= : Session number when using --turn (defaults to game current session)}
        {--disk=public : Filesystem disk for upload}
        {--stdout : Print JSON to stdout instead of uploading}
        {--path= : Local path instead of disk (file or directory)}';

    protected $description = 'Export the assembled narration LLM request for one committed turn (history, messages, system prompt)';

    public function __construct(private readonly GameTurnRequestAssembler $assembler)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $game = Game::query()->with('story')->findOrFail((string) $this->argument('game'));
            $responsePrompt = $this->resolveResponsePrompt($game);
            $pack = $this->assembler->assemble($game, $responsePrompt);
            $json = json_encode(
                $pack,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE,
            );

            if ($json === false) {
                throw new RuntimeException('json_encode failed: '.json_last_error_msg());
            }

            $slug = $game->story?->slug ?? 'story';
            $filename = sprintf(
                '%s-%s-turn-%s-assembled.json',
                $slug,
                $game->id,
                $responsePrompt->id,
            );

            if ($this->option('path')) {
                $path = $this->resolveLocalPath((string) $this->option('path'), $filename);
                file_put_contents($path, $json);
                $this->info("Wrote {$path} (".mb_strlen($json).' bytes)');

                return self::SUCCESS;
            }

            if ($this->option('stdout')) {
                $this->line($json);

                return self::SUCCESS;
            }

            $dest = 'exports/'.$filename;
            Storage::disk((string) $this->option('disk'))->put($dest, $json);
            $this->info("Uploaded {$dest} (".mb_strlen($json).' bytes)');

            return self::SUCCESS;
        } catch (Throwable $throwable) {
            $this->error($throwable->getMessage());

            return self::FAILURE;
        }
    }

    private function resolveResponsePrompt(Game $game): Prompt
    {
        $responsePromptId = $this->option('response-prompt-id');
        if (is_string($responsePromptId) && $responsePromptId !== '') {
            $prompt = Prompt::query()
                ->where('game_id', $game->id)
                ->whereKey($responsePromptId)
                ->first();

            if ($prompt === null || ! filled($prompt->response)) {
                throw new RuntimeException("Response prompt {$responsePromptId} not found for this game.");
            }

            return $prompt;
        }

        $turn = (int) $this->option('turn');
        if ($turn < 1) {
            throw new RuntimeException('Pass --turn=N or --response-prompt-id=ULID.');
        }

        $sessionNumber = $this->option('session') !== null
            ? (int) $this->option('session')
            : (int) ($game->current_session_number ?? 1);

        $responses = $this->assembler->responsePromptsInOrder($game, $sessionNumber);

        if (! isset($responses[$turn - 1])) {
            throw new RuntimeException(
                "Turn {$turn} not found in session {$sessionNumber} (".count($responses).' response rows).'
            );
        }

        return $responses[$turn - 1];
    }

    private function resolveLocalPath(string $path, string $filename): string
    {
        $path = mb_rtrim($path, '/');
        if (is_dir($path)) {
            return $path.'/'.$filename;
        }

        return $path;
    }
}
