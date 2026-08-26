<?php

declare(strict_types=1);

it('uses plaintext websockets for local reverb', function () {
    expect(config('broadcasting.connections.reverb.options.scheme'))->toBe('http')
        ->and(config('broadcasting.connections.reverb.options.useTLS'))->toBeFalse()
        ->and((int) config('broadcasting.connections.reverb.options.port'))->toBe(8080);
});

it('runs Reverb as a dedicated Sail service', function () {
    $compose = file_get_contents(base_path('compose.yaml'));

    expect($compose)
        ->toContain('    reverb:')
        ->toContain('- \'${FORWARD_REVERB_PORT:-8080}:8080\'')
        ->toContain('command: php artisan reverb:start --host=0.0.0.0 --port=8080');
});
