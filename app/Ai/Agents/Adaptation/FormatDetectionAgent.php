<?php

declare(strict_types=1);

namespace App\Ai\Agents\Adaptation;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Attributes\Timeout;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;
use Stringable;
use Throwable;

#[Model('gpt-5.4')]
#[Temperature(0.4)]
#[Timeout(120)]
final class FormatDetectionAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @throws Throwable
     */
    public function instructions(): Stringable|string
    {
        return view('ai.agents.adaptation.format-detection.system-prompt')->render();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'detected_format' => $schema
                ->string()
                ->required()
                ->title('Detected Format')
                ->description('SCREENPLAY or NOVEL.'),
            'evidence' => $schema
                ->string()
                ->required()
                ->title('Evidence')
                ->description('Two sentences citing specific formatting features found on page 1.'),
            'narrative_tense' => $schema
                ->string()
                ->required()
                ->title('Narrative Tense')
                ->description('First person / Third person limited / Third person omniscient / Second person / Screenplay present.'),
            'protagonist_name' => $schema
                ->string()
                ->required()
                ->title('Protagonist Name')
                ->description('Name as it appears in source.'),
            'genre_signals' => $schema
                ->array()
                ->required()
                ->title('Genre Signals')
                ->description('2-3 genre markers visible in first 5 pages.')
                ->items($schema->string()->required()),
            'estimated_reading_time_per_page' => $schema
                ->string()
                ->required()
                ->title('Estimated Reading Time Per Page')
                ->description('Prose average ~2 min/page. Screenplay average ~1 min/page.'),
            'total_estimated_source_duration' => $schema
                ->string()
                ->required()
                ->title('Total Estimated Source Duration')
                ->description('Pages x reading time.'),
            'estimated_session_count' => $schema
                ->number()
                ->required()
                ->title('Estimated Session Count')
                ->description('At approximately 40-60 source pages per session, estimated number of Lorespinner sessions.'),
        ];
    }
}
