@props(['title' => null])

{{--
    Management layout. Sidebar: static and collapsible from 1024px (lg), off-canvas drawer below.
    Collapsed state is remembered per device; the page works without it.
--}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-layouts.head :title="$title" />
</head>
<body class="min-h-screen" data-layout="app">
<a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-control focus:bg-accent focus:px-4 focus:py-3 focus:text-on-accent">{{ __('Skip to content') }}</a>

<div x-data="{
        drawer: false,
        collapsed: (() => { try { return localStorage.getItem('yangs.sidebar') === 'collapsed' } catch { return false } })(),
        toggle() { this.collapsed = ! this.collapsed; try { localStorage.setItem('yangs.sidebar', this.collapsed ? 'collapsed' : 'expanded') } catch {} },
     }"
     x-on:keydown.escape.window="drawer = false"
     class="flex min-h-screen">

    {{-- Drawer scrim (below lg) --}}
    <div x-cloak x-show="drawer" x-on:click="drawer = false" class="fixed inset-0 z-30 bg-black/70 lg:hidden" aria-hidden="true"></div>

    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-40 flex w-64 -translate-x-full flex-col border-r border-line bg-surface transition-transform duration-150 lg:sticky lg:top-0 lg:h-screen lg:translate-x-0"
           x-bind:class="{ 'translate-x-0': drawer, 'lg:w-20': collapsed }"
           aria-label="{{ __('Main navigation') }}">
        <div class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-line px-4">
            <a href="{{ route('dashboard') }}" class="flex min-h-touch items-center" x-bind:class="{ 'lg:hidden': collapsed }">
                <x-layouts.wordmark />
            </a>
            <button type="button" x-on:click="drawer = false" class="inline-flex size-touch items-center justify-center rounded-control text-muted hover:bg-raised hover:text-fg lg:hidden" aria-label="{{ __('Close menu') }}">
                <x-ui.icon name="close" />
            </button>
        </div>

        <nav class="flex-1 overflow-y-auto p-3">
            <ul class="space-y-1">
                @foreach (\App\Support\Navigation::items(auth()->user()) as $item)
                    @php($active = request()->routeIs($item['active']))
                    <li>
                        <a href="{{ route($item['route']) }}"
                           @if ($active) aria-current="page" @endif
                           x-bind:title="collapsed ? @js($item['label']) : null"
                           @class([
                               'flex min-h-touch items-center gap-3 rounded-control px-3 text-base font-medium transition-colors duration-150',
                               'bg-accent/15 text-accent' => $active,
                               'text-muted hover:bg-raised hover:text-fg' => ! $active,
                           ])
                           x-bind:class="{ 'lg:justify-center lg:px-0': collapsed }">
                            <x-ui.icon :name="$item['icon']" />
                            <span x-bind:class="{ 'lg:sr-only': collapsed }">{{ __($item['label']) }}</span>
                        </a>
                    </li>
                @endforeach
            </ul>
        </nav>

        <div class="hidden border-t border-line p-3 lg:block">
            <button type="button" x-on:click="toggle()"
                    class="flex min-h-touch w-full items-center gap-3 rounded-control px-3 text-muted hover:bg-raised hover:text-fg"
                    x-bind:class="{ 'justify-center px-0': collapsed }"
                    x-bind:aria-label="collapsed ? @js(__('Expand sidebar')) : @js(__('Collapse sidebar'))"
                    x-bind:aria-expanded="(! collapsed).toString()" aria-controls="sidebar">
                <x-ui.icon name="collapse" x-show="! collapsed" />
                <x-ui.icon name="expand" x-cloak x-show="collapsed" />
                <span x-show="! collapsed">{{ __('Collapse') }}</span>
            </button>
        </div>
    </aside>

    <div class="flex min-w-0 flex-1 flex-col">
        <header class="sticky top-0 z-20 flex h-16 shrink-0 items-center gap-3 border-b border-line bg-bg/95 px-4 sm:px-6">
            <button type="button" x-on:click="drawer = true" class="inline-flex size-touch items-center justify-center rounded-control text-fg hover:bg-raised lg:hidden"
                    aria-label="{{ __('Open menu') }}" aria-controls="sidebar" x-bind:aria-expanded="drawer.toString()">
                <x-ui.icon name="menu" />
            </button>
            <h1 class="min-w-0 flex-1 truncate font-display text-2xl font-semibold">{{ $title }}</h1>

            <a href="{{ route('profile.edit') }}" class="hidden min-h-touch items-center gap-2 rounded-control px-3 text-right hover:bg-raised sm:flex">
                <span class="leading-tight">
                    <span class="block text-sm font-medium text-fg">{{ auth()->user()->name }}</span>
                    <span class="block text-sm text-muted">{{ auth()->user()->roles->first()?->name ?? __('No role') }}</span>
                </span>
                <x-ui.icon name="user" class="size-7 text-muted" />
            </a>
            <a href="{{ route('profile.edit') }}" class="inline-flex size-touch items-center justify-center rounded-control text-muted hover:bg-raised hover:text-fg sm:hidden" aria-label="{{ __('My profile') }}">
                <x-ui.icon name="user" />
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="inline-flex size-touch items-center justify-center rounded-control text-muted hover:bg-raised hover:text-fg" aria-label="{{ __('Sign out') }}" title="{{ __('Sign out') }}">
                    <x-ui.icon name="logout" />
                </button>
            </form>
        </header>

        <main id="main" class="flex-1 space-y-6 px-4 py-6 sm:px-6">
            <x-ui.flash />
            {{ $slot }}
        </main>
    </div>
</div>
</body>
</html>
