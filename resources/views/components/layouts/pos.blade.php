@props(['title' => null])

{{-- POS layout: full screen, one 64px strip, no sidebar. Built for 768×1024 and 1024×768 tablets. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.head :title="$title" />
</head>
<body class="h-dvh overflow-hidden" data-layout="pos">
<div class="flex h-dvh flex-col">
    <header class="flex h-16 shrink-0 items-center gap-3 border-b border-line bg-surface px-3 sm:px-4">
        <x-layouts.wordmark />
        @if ($title)
            <h1 class="min-w-0 flex-1 truncate font-display text-xl font-semibold text-fg">{{ $title }}</h1>
        @else
            <div class="flex-1"></div>
        @endif

        <span class="hidden text-right leading-tight sm:block">
            <span class="block text-sm font-medium text-fg">{{ auth()->user()->name }}</span>
            <span class="block text-sm text-muted">{{ auth()->user()->roles->first()?->name }}</span>
        </span>

        @can('orders.view-own')
            <a href="{{ route('transactions.index') }}" aria-label="{{ __('Transactions') }}" title="{{ __('Transactions') }}"
               class="inline-flex min-h-touch min-w-touch items-center justify-center gap-2 rounded-control px-3 font-semibold text-fg transition-colors duration-150 hover:bg-raised">
                <x-ui.icon name="receipt" class="size-5" /><span class="hidden lg:inline">{{ __('Transactions') }}</span>
            </a>
        @endcan

        @if (\App\Support\Navigation::hasManagementPages(auth()->user()))
            <x-ui.button :href="route('dashboard')" variant="ghost" icon="squares">{{ __('Management') }}</x-ui.button>
        @endif

        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="inline-flex size-touch items-center justify-center rounded-control text-muted hover:bg-raised hover:text-fg" aria-label="{{ __('Sign out') }}" title="{{ __('Sign out') }}">
                <x-ui.icon name="logout" />
            </button>
        </form>
    </header>

    <main id="main" class="min-h-0 flex-1 overflow-y-auto p-3 sm:p-4">
        <x-ui.flash class="mb-4" />
        {{ $slot }}
    </main>
</div>
</body>
</html>
