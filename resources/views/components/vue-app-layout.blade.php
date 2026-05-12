@props([
    'bodyClass' => 'flex min-h-screen flex-col bg-slate-50 dark:bg-slate-900',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sondages - {{ config('app.name') }}</title>

    @isset($scripts)
        {{ $scripts }}
    @endisset
</head>

<body {{ $attributes->class([$bodyClass]) }}>
    <header class="bg-teal-600 text-white dark:bg-slate-800">
        <nav class="container mx-auto px-4 sm:px-6 lg:px-8">
            <div class="h-16 flex items-center justify-between">
                <div class="flex items-center gap-4">
                    <a href="{{ url('/') }}" class="block hover:opacity-80 transition font-bold">
                        {{ config('app.name') }}
                    </a>
                    <a href="{{ url('/posts') }}" class="block hover:opacity-80 transition">
                        Retour aux posts
                    </a>
                    @auth
                        <a href="{{ url('/polls/dashboard') }}" class="block hover:opacity-80 transition font-bold">
                            Mes Sondages
                        </a>
                    @endauth
                </div>
            </div>
        </nav>
    </header>

    <main class="container mx-auto px-4 py-8 sm:px-6 lg:px-8 flex-grow dark:text-white max-w-4xl">
        {{ $slot }}
    </main>
</body>

</html>
