<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content">

        <title inertia>{{ config('app.display_name', 'LoreSpinner') }}</title>

        <!-- Google tag (gtag.js) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=G-SYP9BDH337"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());

            gtag('config', 'G-SYP9BDH337');
        </script>

        <!-- Favicon — versioned to bust browser/Google cache -->
        <link rel="icon" href="/favicon.ico?v=3" sizes="any">
        <link rel="icon" href="/favicon.png?v=3" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=3">
        <link rel="manifest" href="/site.webmanifest">

        <!-- Google Search Console verification -->
        <meta name="google-site-verification" content="google3e64d38c504450d9">

        <!-- Microsoft Clarity -->
        <script type="text/javascript">
            (function (w, d, n, projectId) {
                var userAgent = n.userAgent || '';
                var isSafari = /Safari/i.test(userAgent) && !/Chrome|Chromium|CriOS|FxiOS|EdgiOS|OPiOS/i.test(userAgent);

                // Clarity's session recorder is a global DOM observer. Keep it
                // off Safari pages because it can make WebKit unresponsive on
                // the site's animation-heavy surfaces.
                if (isSafari) {
                    return;
                }

                w.clarity = w.clarity || function () {
                    (w.clarity.q = w.clarity.q || []).push(arguments);
                };

                var script = d.createElement('script');
                script.async = 1;
                script.src = 'https://www.clarity.ms/tag/' + projectId;

                var firstScript = d.getElementsByTagName('script')[0];
                if (firstScript && firstScript.parentNode) {
                    firstScript.parentNode.insertBefore(script, firstScript);
                }
            })(window, document, navigator, 'x2x3tavro1');
        </script>

        <!-- SEO / Open Graph -->
        <meta name="application-name" content="LoreSpinner">
        <meta property="og:site_name" content="LoreSpinner">
        <meta name="twitter:site" content="@lorespinner">

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Marcellus+SC&display=swap" rel="stylesheet">

        @php
            $pageComponent = $page['component'];
            $pageEntry = match (true) {
                str_starts_with($pageComponent, 'VoiceLab/') =>
                    'resources/js/voice-lab/pages/' . substr($pageComponent, strlen('VoiceLab/')) . '.vue',
                str_starts_with($pageComponent, 'XenRnd/') =>
                    'resources/js/xen-rnd/pages/' . substr($pageComponent, strlen('XenRnd/')) . '.vue',
                default =>
                    'resources/js/pages/' . $pageComponent . '.vue',
            };
        @endphp
        @vite(['resources/js/app.ts', $pageEntry])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
