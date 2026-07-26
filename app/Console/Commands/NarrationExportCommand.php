<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Runtime\NarrationMetricsStore;
use Illuminate\Console\Command;

final class NarrationExportCommand extends Command
{
    protected $signature = 'narration:export
        {--limit=30 : Max samples to print}
        {--clear : Clear stored samples after printing}';

    protected $description = 'Export recent narration latency samples recorded by web turns (Cloud-safe)';

    public function handle(): int
    {
        $samples = NarrationMetricsStore::all();
        $limit = (int) $this->option('limit');
        if ($limit > 0) {
            $samples = array_slice($samples, 0, $limit);
        }

        $payload = [
            'exported_at' => now()->toIso8601String(),
            'cache_store' => (string) config('cache.default'),
            'sample_count' => count($samples),
            'samples' => $samples,
        ];

        $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}');

        if ($this->option('clear')) {
            NarrationMetricsStore::clear();
            $this->warn('Cleared narration latency samples.');
        }

        return self::SUCCESS;
    }
}
