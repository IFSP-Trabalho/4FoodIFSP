<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <script>
            // Apply saved theme before first paint to avoid a flash of light UI.
            try {
                if (localStorage.getItem('theme') === 'dark') {
                    document.documentElement.classList.add('theme-dark');
                }
            } catch (e) {}
        </script>

        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @inertiaHead
    </head>
    <body>
        @inertia
    </body>
</html>
