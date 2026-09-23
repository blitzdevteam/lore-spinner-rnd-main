<?php

declare(strict_types=1);

it('uses the display name for browser titles and inertia sharing', function (): void {
    config()->set('app.name', 'lore-spinner-rnd');
    config()->set('app.display_name', 'LoreSpinner');

    $appTemplate = file_get_contents(resource_path('views/app.blade.php'));
    $handleInertia = file_get_contents(app_path('Http/Middleware/HandleInertiaRequests.php'));

    expect($appTemplate)
        ->toContain("config('app.display_name'")
        ->toContain('<title inertia>{{ config(\'app.display_name\', \'LoreSpinner\') }}</title>');

    expect($handleInertia)
        ->toContain("'appName' => config('app.display_name')");
});

it('ships favicon assets on an opaque black backdrop for Safari', function (): void {
    $public = public_path();

    foreach (['favicon.png', 'apple-touch-icon.png'] as $filename) {
        $path = $public.'/'.$filename;

        expect($path)->toBeReadableFile();

        $image = imagecreatefrompng($path);
        expect($image)->not->toBeFalse();

        $width = imagesx($image);
        $height = imagesy($image);

        expect($width)->toBeGreaterThan(0);
        expect($height)->toBeGreaterThan(0);

        $corner = imagecolorat($image, 0, 0);
        $red = ($corner >> 16) & 0xFF;
        $green = ($corner >> 8) & 0xFF;
        $blue = $corner & 0xFF;

        expect($red)->toBeLessThan(8);
        expect($green)->toBeLessThan(8);
        expect($blue)->toBeLessThan(8);
    }

    $manifest = json_decode((string) file_get_contents($public.'/site.webmanifest'), true, 512, JSON_THROW_ON_ERROR);

    expect($manifest['description'])->toBe('LoreSpinner');
});
