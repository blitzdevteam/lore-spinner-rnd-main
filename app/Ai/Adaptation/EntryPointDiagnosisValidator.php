<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Models\Event;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Rejects an entry point the cards could not follow: a start outside the
 * session, a start that skips a beat the session map requires, or a cold open
 * that breaks the cold-open contract.
 */
final readonly class EntryPointDiagnosisValidator
{
    public function __construct(private ColdOpenRepairValidator $coldOpenValidator) {}

    /**
     * @param  array<string, mixed>  $diagnosis
     * @param  Collection<int, Event>  $sessionEvents
     * @param  list<int>  $mustBreathePositions
     *
     * @throws RuntimeException
     */
    public function validate(array $diagnosis, Collection $sessionEvents, array $mustBreathePositions, string $protagonist): void
    {
        $failures = [];
        $start = (int) ($diagnosis['start_event_position'] ?? 0);

        if ($sessionEvents->firstWhere('story_position', $start) === null) {
            $failures[] = "start_event_position {$start} is not one of this session's events (".$sessionEvents->pluck('story_position')->implode(', ').').';
        }

        $skipped = array_values(array_filter($mustBreathePositions, static fn (int $position): bool => $position < $start));
        if ($skipped !== []) {
            $failures[] = "The start event {$start} skips must-breathe events ".implode(', ', $skipped).'. Start at or before them.';
        }

        $nameToCheck = $protagonist === 'the protagonist' ? '' : $protagonist;
        foreach ($this->coldOpenValidator->failures((string) ($diagnosis['cold_open'] ?? ''), $nameToCheck) as $failure) {
            $failures[] = $failure;
        }

        foreach ((array) data_get($diagnosis, 'format_specific_cut.must_reintroduce', []) as $index => $item) {
            if (! in_array(data_get($item, 'marker'), ['COLD_OPEN', 'LATER'], true)) {
                $failures[] = "must_reintroduce.{$index}.marker must be COLD_OPEN or LATER.";
            }
        }

        if ($failures !== []) {
            throw new RuntimeException(implode("\n", $failures));
        }
    }
}
