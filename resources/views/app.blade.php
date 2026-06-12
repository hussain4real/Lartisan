<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'system') === 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearances = ['light', 'dark', 'system'];
                const cookieAppearance = @json($appearance ?? 'system');
                let storedAppearance = null;

                try {
                    storedAppearance = window.localStorage.getItem('appearance');

                    if (storedAppearance && !appearances.includes(storedAppearance)) {
                        window.localStorage.removeItem('appearance');
                        storedAppearance = null;
                    }
                } catch (error) {
                    storedAppearance = null;
                }

                const appearance = appearances.includes(storedAppearance)
                    ? storedAppearance
                    : cookieAppearance;
                const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const resolvedAppearance = appearance === 'system'
                    ? (prefersDark ? 'dark' : 'light')
                    : appearance;

                document.documentElement.classList.toggle('dark', resolvedAppearance === 'dark');
                document.documentElement.style.colorScheme = resolvedAppearance;
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: hsl(210 24% 98%);
            }

            html.dark {
                background-color: hsl(225 48% 7%);
            }
        </style>

        <link rel="icon" href="/favicon.ico?v=lartisan" sizes="any">
        <link rel="icon" href="/favicon.svg?v=lartisan" type="image/svg+xml">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=lartisan">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Lartisan') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
