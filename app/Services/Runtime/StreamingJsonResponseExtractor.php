<?php

declare(strict_types=1);

namespace App\Services\Runtime;

/**
 * Extracts decoded characters from a JSON object's "response" string while
 * the object is still streaming.
 */
final class StreamingJsonResponseExtractor
{
    private string $buffer = '';

    private bool $started = false;

    private bool $finished = false;

    private bool $escaping = false;

    private ?string $unicodeEscape = null;

    public function push(string $chunk): string
    {
        if ($this->finished || $chunk === '') {
            return '';
        }

        $this->buffer .= $chunk;

        if (! $this->started) {
            if (! preg_match('/"response"\s*:\s*"/', $this->buffer, $matches, PREG_OFFSET_CAPTURE)) {
                $this->buffer = mb_substr($this->buffer, max(0, mb_strlen($this->buffer) - 32));

                return '';
            }

            $this->started = true;
            $matchStart = $matches[0][1];
            $matchLength = mb_strlen($matches[0][0]);
            $this->buffer = mb_substr($this->buffer, $matchStart + $matchLength);
        }

        $out = '';
        $length = mb_strlen($this->buffer);
        $consumed = 0;

        for ($i = 0; $i < $length; $i++) {
            $char = $this->buffer[$i];
            $consumed = $i + 1;

            if ($this->unicodeEscape !== null) {
                $this->unicodeEscape .= $char;
                if (mb_strlen($this->unicodeEscape) === 4) {
                    $decoded = json_decode('"\\u'.$this->unicodeEscape.'"');
                    $out .= is_string($decoded) ? $decoded : '';
                    $this->unicodeEscape = null;
                    $this->escaping = false;
                }

                continue;
            }

            if ($this->escaping) {
                $out .= match ($char) {
                    '"', '\\', '/' => $char,
                    'b' => "\b",
                    'f' => "\f",
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'u' => '',
                    default => $char,
                };

                if ($char === 'u') {
                    $this->unicodeEscape = '';
                } else {
                    $this->escaping = false;
                }

                continue;
            }

            if ($char === '\\') {
                $this->escaping = true;

                continue;
            }

            if ($char === '"') {
                $this->finished = true;
                break;
            }

            $out .= $char;
        }

        $this->buffer = mb_substr($this->buffer, $consumed);

        return $out;
    }
}
