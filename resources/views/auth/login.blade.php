<x-layouts.guest :title="__('Sign in')">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <x-ui.input name="username" :label="__('Username')" required autofocus autocapitalize="none" autocomplete="username" />
        <x-ui.input name="password" type="password" :label="__('Password')" required autocomplete="current-password" />

        <label for="remember_me" class="flex min-h-touch items-center gap-3">
            <input id="remember_me" type="checkbox" name="remember" class="size-6 rounded border-edge bg-raised text-accent focus:ring-accent">
            <span class="text-base text-fg">{{ __('Keep me signed in on this device') }}</span>
        </label>

        <x-ui.button type="submit" size="lg" class="w-full">{{ __('Sign in') }}</x-ui.button>
    </form>
</x-layouts.guest>
