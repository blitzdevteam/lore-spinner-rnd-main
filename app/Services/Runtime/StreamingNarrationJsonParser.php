<?php

declare(strict_types=1);

namespace App\Services\Runtime;

use JsonException;

final class StreamingNarrationJsonParser
{
    private string $raw = '';

    private string $encodedResponse = '';

    private int $responseStart = -1;

    private int $scanOffset = 0;

    private string $decodedResponse = '';

    public function push(string $chunk): string
    {
        $this->raw .= $chunk;
        $this->locateResponse();

        if ($this->responseStart < 0) {
            return '';
        }

        $this->scanResponse();
        try {
            $decoded = json_decode('"'.$this->encodedResponse.'"', true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return '';
        }

        if (! is_string($decoded) || ! str_starts_with($decoded, $this->decodedResponse)) {
            return '';
        }

        $delta = mb_substr($decoded, mb_strlen($this->decodedResponse));
        $this->decodedResponse = $decoded;

        return $delta;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function finish(): ?array
    {
        $json = mb_trim($this->raw);
        $json = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $json) ?? $json;

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return null;
        }

        if (! is_array($decoded) || ! isset($decoded['response']) || ! is_string($decoded['response'])) {
            return null;
        }

        foreach ([
            'choices', 'visible_suggestions', 'moment_progress', 'session_complete',
            'state_delta', 'alignment_tally_delta', 'is_climactic_choice',
            'defining_choice_id', 'defining_path_label', 'defining_choice_line',
            'symbolic_memory_update', 'session_memory_update', 'unresolved_promises',
            'emotional_ledger_entries',
        ] as $field) {
            if (! array_key_exists($field, $decoded)) {
                return null;
            }
        }

        return $decoded;
    }

    private function locateResponse(): void
    {
        if ($this->responseStart >= 0) {
            return;
        }

        if (preg_match('/"response"\s*:\s*"/', $this->raw, $match, PREG_OFFSET_CAPTURE) !== 1) {
            return;
        }

        $this->responseStart = $match[0][1] + mb_strlen($match[0][0]);
        $this->scanOffset = $this->responseStart;
    }

    private function scanResponse(): void
    {
        $escaped = false;
        $length = mb_strlen($this->raw);

        for ($index = $this->scanOffset; $index < $length; $index++) {
            $character = $this->raw[$index];
            if ($escaped) {
                $escaped = false;
                $this->encodedResponse .= $character;

                continue;
            }
            if ($character === '\\') {
                $escaped = true;
                $this->encodedResponse .= $character;

                continue;
            }
            if ($character === '"') {
                $this->scanOffset = $index;

                return;
            }
            $this->encodedResponse .= $character;
        }

        $this->scanOffset = $length;
    }
}
