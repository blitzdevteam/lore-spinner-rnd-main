<?php

declare(strict_types=1);

use App\Support\ScriptChapterBoundaryDetector;

it('detects chapter markers in large classic scripts', function (): void {
    $dracula = file_get_contents(database_path('stories/DRACULA_script.txt'));

    expect(ScriptChapterBoundaryDetector::detect($dracula))->toHaveCount(27);
});

it('detects pride and prejudice roman chapter headers', function (): void {
    $pride = file_get_contents(database_path('stories/PRIDE AND PREJUDICE_script.txt'));

    expect(ScriptChapterBoundaryDetector::detect($pride))->toHaveCount(61);
});

it('ignores inline table-of-contents headings and detects the actual musketeer chapters', function (): void {
    $musketeers = file_get_contents(database_path('stories/The Three Musketeers_script.txt'));

    expect(ScriptChapterBoundaryDetector::detect($musketeers))->toHaveCount(67);
});
