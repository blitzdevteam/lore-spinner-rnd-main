<?php

declare(strict_types=1);

namespace App\Ai\Adaptation;

use App\Support\StoryPlayability;

/**
 * Approved title-specific outputs consumed by the generalized repair command.
 *
 * A reference is returned exactly as reviewed. Titles without one continue
 * through ColdOpenRepairAgent and Tom's universal contract.
 */
final class ApprovedColdOpenReference
{
    public function for(string $storySlug, int $sessionNumber): ?string
    {
        $key = StoryPlayability::canonicalSlug($storySlug).':'.$sessionNumber;

        $view = match ($key) {
            'peter-pan:1' => 'ai.agents.adaptation.cold-open-repair.examples.peter-pan',
            default => null,
        };

        return $view === null ? null : mb_trim(view($view)->render());
    }
}
