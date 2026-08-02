<?php

declare(strict_types=1);

use App\Services\Runtime\StreamingNarrationJsonParser;

it('publishes only decoded response deltas and validates the complete envelope', function (): void {
    $json = json_encode([
        'response' => '<p>First "beat".</p>',
        'choices' => [],
        'visible_suggestions' => [],
        'moment_progress' => [],
        'session_complete' => false,
        'state_delta' => [],
        'unresolved_promises' => [],
        'emotional_ledger_entries' => [],
        'alignment_tally_delta' => ['chaotic' => 0, 'lawful' => 0, 'neutral' => 0],
        'is_climactic_choice' => false,
        'defining_choice_id' => '',
        'defining_path_label' => '',
        'defining_choice_line' => '',
        'symbolic_memory_update' => '',
        'session_memory_update' => '',
    ], JSON_THROW_ON_ERROR);
    $parser = new StreamingNarrationJsonParser;
    $deltas = '';

    foreach (mb_str_split($json, 7) as $chunk) {
        $deltas .= $parser->push($chunk);
    }

    expect($deltas)->toBe('<p>First "beat".</p>')
        ->and($parser->finish())->toBeArray();
});

it('rejects an incomplete envelope', function (): void {
    $parser = new StreamingNarrationJsonParser;

    $parser->push('{"response":"Ready","choices":[]}');

    expect($parser->finish())->toBeNull();
});
