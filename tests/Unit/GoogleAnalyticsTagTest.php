<?php

declare(strict_types=1);

it('loads the Google Analytics 4 tag and records inertia page views', function (): void {
    $appTemplate = file_get_contents(resource_path('views/app.blade.php'));
    $appEntry = file_get_contents(resource_path('js/app.ts'));

    expect($appTemplate)
        ->toContain('https://www.googletagmanager.com/gtag/js?id=G-SYP9BDH337')
        ->toContain("gtag('config', 'G-SYP9BDH337');");

    expect($appEntry)
        ->toContain("window.gtag?.('event', 'page_view'");
});
