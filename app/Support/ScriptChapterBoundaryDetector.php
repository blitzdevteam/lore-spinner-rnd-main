<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Detect chapter boundaries from plain-text markers without a model call.
 */
final class ScriptChapterBoundaryDetector
{
    /**
     * @return list<array{position: int, title: string, teaser: string, start_line: int, end_line: int}>|null
     */
    public static function detect(string $rawScript): ?array
    {
        $lines = explode("\n", str_replace("\r\n", "\n", $rawScript));
        $totalLines = count($lines);
        $patterns = [
            '/^CHAPTER\s+[IVXLC]+\.?\s*$/iu',
            '/^(?:Chapter|CHAPTER)\s+[IVXLC]+\.?\s*$/iu',
            '/^Chapter\s+\d+\.?\s*$/iu',
            '/^CHAPTER\s+[IVXLC]+(?:\.|\s|$)/iu',
            '/^(?:Chapter|CHAPTER)\s+[IVXLC]+(?:\.|\s|$)/iu',
            '/^Chapter\s+\d+(?:\.|\s|$)/iu',
        ];

        if ($totalLines < 10) {
            return null;
        }

        $best = null;

        foreach (array_slice($patterns, 0, 3) as $pattern) {
            $markers = self::findMarkers($lines, $pattern);

            if (count($markers) >= 2 && ($best === null || count($markers) > count($best))) {
                $best = $markers;
            }
        }

        if ($best !== null) {
            return self::buildChapters($lines, $best, $totalLines);
        }

        foreach (array_slice($patterns, 3) as $pattern) {
            $markers = self::findMarkers($lines, $pattern);

            if (count($markers) >= 2 && ($best === null || count($markers) > count($best))) {
                $best = $markers;
            }
        }

        return $best === null ? null : self::buildChapters($lines, $best, $totalLines);
    }

    /**
     * @param  list<string>  $lines
     */
    public static function extractPlainSegment(array $lines, int $startLine, int $endLine): string
    {
        return implode("\n", array_slice($lines, $startLine - 1, $endLine - $startLine + 1));
    }

    /**
     * @param  list<string>  $lines
     * @return list<int>
     */
    private static function findMarkers(array $lines, string $pattern): array
    {
        $markers = [];

        foreach ($lines as $index => $line) {
            if (preg_match($pattern, mb_trim($line)) === 1) {
                $markers[] = $index + 1;
            }
        }

        return $markers;
    }

    /**
     * @param  list<string>  $lines
     * @param  list<int>  $markerLines
     * @return list<array{position: int, title: string, teaser: string, start_line: int, end_line: int}>
     */
    private static function buildChapters(array $lines, array $markerLines, int $totalLines): array
    {
        $chapters = [];

        foreach ($markerLines as $index => $startLine) {
            $endLine = ($markerLines[$index + 1] ?? ($totalLines + 1)) - 1;

            $chapters[] = [
                'position' => $index + 1,
                'title' => self::resolveTitle($lines, $startLine),
                'teaser' => self::resolveTeaser($lines, $startLine, $endLine),
                'start_line' => $startLine,
                'end_line' => $endLine,
            ];
        }

        return $chapters;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function resolveTitle(array $lines, int $startLine): string
    {
        $header = mb_trim($lines[$startLine - 1] ?? 'Chapter');

        if (preg_match('/^(?:CHAPTER|Chapter)\s+[IVXLC]+\.?\s*(.*)$/iu', $header, $matches) === 1) {
            $inlineTitle = mb_trim($matches[1] ?? '');

            if ($inlineTitle !== '' && mb_strlen($inlineTitle) < 160) {
                return $inlineTitle;
            }

            $next = mb_trim($lines[$startLine] ?? '');

            if ($next !== '' && mb_strlen($next) < 160) {
                return $next;
            }
        }

        if (preg_match('/^Chapter\s+(\d+)/iu', $header, $matches) === 1) {
            return 'Chapter '.$matches[1];
        }

        return mb_strlen($header) > 80 ? mb_substr($header, 0, 77).'…' : $header;
    }

    /**
     * @param  list<string>  $lines
     */
    private static function resolveTeaser(array $lines, int $startLine, int $endLine): string
    {
        $slice = array_slice($lines, $startLine, min(12, $endLine - $startLine));

        foreach ($slice as $line) {
            $trimmed = mb_trim($line);

            if ($trimmed === '' || preg_match('/^(CHAPTER|Chapter)\s+/iu', $trimmed) === 1) {
                continue;
            }

            if (mb_strlen($trimmed) >= 40) {
                $sentence = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;

                return mb_strlen($sentence) > 200
                    ? mb_substr($sentence, 0, 197).'…'
                    : $sentence;
            }
        }

        return 'The story continues.';
    }
}
