<x-layouts.app :title="__('My profile')">
    <div class="max-w-xl space-y-6">
        <x-ui.card :title="__('Account')">
            <dl class="grid gap-4 text-base sm:grid-cols-2">
                <div><dt class="text-sm text-muted">{{ __('Name') }}</dt><dd>{{ $user->name }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('Username') }}</dt><dd>{{ $user->username }}</dd></div>
                <div><dt class="text-sm text-muted">{{ __('Role') }}</dt><dd>{{ $user->roles->first()?->name ?? '—' }}</dd></div>
            </dl>
            <p class="mt-4 text-sm text-muted">{{ __('Ask the owner to change your name, username or role.') }}</p>
        </x-ui.card>

        @include('profile.partials.update-password-form')
    </div>
</x-layouts.app>
