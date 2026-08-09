<?php

declare(strict_types=1);

use App\Services\Runtime\StreamingJsonResponseExtractor;

it('extracts decoded response text from chunked json', function (): void {
    $extractor = new StreamingJsonResponseExtractor;
    $chunks = [
        '{"res',
        'ponse":"<p>Hello',
        '\\nthere \\"Alice\\".</p>",',
        '"choices":["Go"],"session_complete":false}',
    ];

    $out = '';
    foreach ($chunks as $chunk) {
        $out .= $extractor->push($chunk);
    }

    expect($out)->toBe("<p>Hello\nthere \"Alice\".</p>");
});

it('stops extracting when the response field ends', function (): void {
    $extractor = new StreamingJsonResponseExtractor;

    $out = $extractor->push('{"response":"Visible","choices":["Hidden"]}');

    expect($out)->toBe('Visible');
});
