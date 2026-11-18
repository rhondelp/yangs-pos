@csrf

<div class="space-y-5">
    <div>
        <x-input-label for="name" :value="__('Full name')" />
        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name', $user->name)" required autofocus />
        <x-input-error :messages="$errors->get('name')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="username" :value="__('Username')" />
        <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username', $user->username)" required autocapitalize="none" autocomplete="off" />
        <x-input-error :messages="$errors->get('username')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="email" :value="__('E-mail (optional)')" />
        <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $user->email)" />
        <x-input-error :messages="$errors->get('email')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="role" :value="__('Role')" />
        @php($currentRole = old('role', $user->roles->first()?->name))
        <select id="role" name="role" required class="mt-1 block min-h-12 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500">
            <option value="" disabled @selected(! $currentRole)>{{ __('Choose a role') }}</option>
            @foreach ($roles as $role)
                <option value="{{ $role }}" @selected($currentRole === $role)>{{ $role }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get('role')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="password" :value="$user->exists ? __('New password (leave blank to keep the current one)') : __('Password')" />
        <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" :required="! $user->exists" />
        <x-input-error :messages="$errors->get('password')" class="mt-2" />
    </div>

    <div>
        <x-input-label for="password_confirmation" :value="__('Confirm password')" />
        <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" autocomplete="new-password" />
    </div>

    <div>
        <input type="hidden" name="is_active" value="0">
        <label for="is_active" class="inline-flex min-h-12 items-center gap-3">
            <input id="is_active" name="is_active" type="checkbox" value="1" class="h-6 w-6 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" @checked(old('is_active', $user->is_active))>
            <span class="text-sm text-gray-700">{{ __('Active (can sign in)') }}</span>
        </label>
        <x-input-error :messages="$errors->get('is_active')" class="mt-2" />
    </div>

    <div class="flex items-center gap-4">
        <x-primary-button>{{ __('Save') }}</x-primary-button>
        <a href="{{ route('users.index') }}" class="inline-flex min-h-12 items-center px-3 text-sm text-gray-700 hover:underline">{{ __('Cancel') }}</a>
    </div>
</div>
