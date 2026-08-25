<?php

declare(strict_types=1);

it('uses plaintext websockets for local reverb', function () {
    expect(config('broadcasting.connections.reverb.options.scheme'))->toBe('http')
        ->and(config('broadcasting.connections.reverb.options.useTLS'))->toBeFalse()
        ->and((int) config('broadcasting.connections.reverb.options.port'))->toBe(8080);
});
