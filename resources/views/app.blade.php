<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, interactive-widget=resizes-content">

        <title inertia>{{ config('app.name', 'LoreSpinner') }}</title>

        <!-- Favicon — versioned to bust browser/Google cache -->
        <link rel="icon" href="/favicon.ico?v=2" sizes="any">
        <link rel="icon" href="/favicon.png?v=2" type="image/png">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">
        <link rel="manifest" href="/site.webmanifest">

        <!-- Google Search Console verification -->
        <meta name="google-site-verification" content="google3e64d38c504450d9">

        <!-- Microsoft Clarity -->
        <script type="text/javascript">
            (function(c,l,a,r,i,t,y){
                c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};
                t=l.createElement(r);t.async=1;t.src="https://www.clarity.ms/tag/"+i;
                y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);
            })(window, document, "clarity", "script", "x2x3tavro1");
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
