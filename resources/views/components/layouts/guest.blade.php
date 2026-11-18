@props(['title' => null])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.head :title="$title" />
</head>
<body class="min-h-screen" data-layout="guest">
<main id="main" class="flex min-h-screen flex-col items-center justify-center px-4 py-10">
    <x-layouts.wordmark class="mb-8 text-4xl" />
    <div class="w-full max-w-sm rounded-card border border-line bg-surface p-6">
        @if ($title)
            <h1 class="mb-6 font-display text-2xl font-semibold">{{ $title }}</h1>
        @endif
        {{ $slot }}
    </div>
</main>
</body>
</html>
