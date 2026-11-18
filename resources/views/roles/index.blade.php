<x-layouts.app :title="__('Roles and permissions')">
    @if ($errors->any())
        <x-ui.alert variant="error">{{ $errors->first() }}</x-ui.alert>
    @endif

    <p class="max-w-3xl text-base text-muted">
        {{ __('Tick what each role may do. The Owner role always has every permission. Changes apply on each person\'s next page load.') }}
    </p>

    <form method="POST" action="{{ route('roles.update') }}" class="space-y-4">
        @csrf
        @method('PUT')

        <div class="max-h-[calc(100dvh-16rem)] overflow-auto rounded-card border border-line bg-surface">
            <table class="min-w-full text-sm">
                <thead class="sticky top-0 z-10 bg-raised">
                    <tr>
                        <th scope="col" class="sticky left-0 z-10 bg-raised px-4 py-3 text-left font-medium text-muted">{{ __('Permission') }}</th>
                        @foreach ($roles as $role)
                            <th scope="col" class="min-w-20 px-2 py-3 text-center font-medium text-fg">{{ $role->name }}</th>
                        @endforeach
                    </tr>
                </thead>
                @foreach ($groups as $group => $permissions)
                    <tbody>
                        <tr class="border-t border-line">
                            <th scope="colgroup" colspan="{{ $roles->count() + 1 }}" class="bg-bg/60 px-4 py-2 text-left font-display text-base font-semibold text-accent">{{ $group }}</th>
                        </tr>
                        @foreach ($permissions as $permission)
                            <tr class="border-t border-line hover:bg-raised/60">
                                <th scope="row" class="sticky left-0 bg-surface px-4 py-1 text-left font-normal">
                                    <span class="block text-base text-fg">{{ $permission->label() }}</span>
                                    <span class="block text-sm text-muted">{{ $permission->value }}</span>
                                </th>
                                @foreach ($roles as $role)
                                    @php($locked = $role->name === \App\Enums\RoleName::Owner->value)
                                    <td class="px-2 py-1 text-center">
                                        <label class="inline-flex size-touch items-center justify-center">
                                            <span class="sr-only">{{ $role->name }}: {{ $permission->label() }}</span>
                                            <input type="checkbox" class="size-6 rounded border-edge bg-raised text-accent focus:ring-accent disabled:opacity-60"
                                                   @if ($locked)
                                                       checked disabled
                                                   @else
                                                       name="permissions[{{ $role->id }}][]"
                                                       value="{{ $permission->value }}"
                                                       @checked($role->permissions->contains('name', $permission->value))
                                                   @endif
                                            >
                                        </label>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                @endforeach
            </table>
        </div>

        <x-ui.button type="submit">{{ __('Save permissions') }}</x-ui.button>
    </form>
</x-layouts.app>
