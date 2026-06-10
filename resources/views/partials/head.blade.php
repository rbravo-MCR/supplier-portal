<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon_outlet.png" type="image/png">
<link rel="apple-touch-icon" href="/favicon_outlet.png">
<link rel="preconnect" href="https://fonts.bunny.net" crossorigin>

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
