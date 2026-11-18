@csrf

<div class="space-y-5">
    <x-ui.input name="name" :label="__('Full name')" :value="$user->name" required autofocus />
    <x-ui.input name="username" :label="__('Username')" :value="$user->username" :hint="__('Lowercase letters, numbers, dots, dashes or underscores.')" required autocapitalize="none" autocomplete="off" />
    <x-ui.input name="email" type="email" :label="__('E-mail (optional)')" :value="$user->email" />
    <x-ui.select name="role" :label="__('Role')" :options="array_combine($roles, $roles)" :selected="$user->roles->first()?->name" :placeholder="__('Choose a role')" required />
    <x-ui.input name="password" type="password" :label="$user->exists ? __('New password') : __('Password')"
                :hint="$user->exists ? __('Leave blank to keep the current password.') : __('At least 8 characters.')"
                autocomplete="new-password" :required="! $user->exists" />
    <x-ui.input name="password_confirmation" type="password" :label="__('Confirm password')" autocomplete="new-password" errorKey="password_confirmation" />

    <div>
        <input type="hidden" name="is_active" value="0">
        <label for="is_active" class="flex min-h-touch items-center gap-3">
            <input id="is_active" name="is_active" type="checkbox" value="1" class="size-6 rounded border-edge bg-raised text-accent focus:ring-accent" @checked(old('is_active', $user->is_active))>
            <span class="text-base text-fg">{{ __('Active (can sign in)') }}</span>
        </label>
        @error('is_active')<p class="mt-1.5 text-sm font-medium text-danger">{{ $message }}</p>@enderror
    </div>

    <div class="flex flex-wrap items-center gap-3 pt-2">
        <x-ui.button type="submit">{{ __('Save user') }}</x-ui.button>
        <x-ui.button :href="route('users.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
    </div>
</div>
