<x-ui.card :title="__('Change password')">
    @if (session('status') === 'password-updated')
        <x-ui.alert variant="success" class="mb-5">{{ __('Password changed.') }}</x-ui.alert>
    @endif

    @php($bag = $errors->updatePassword)
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        @method('PUT')

        @foreach ([
            'current_password' => __('Current password'),
            'password' => __('New password'),
            'password_confirmation' => __('Confirm new password'),
        ] as $field => $label)
            <x-ui.field :id="'update_'.$field" :label="$label" :error="$bag->first($field)" :required="true">
                <input id="update_{{ $field }}" name="{{ $field }}" type="password" required
                       autocomplete="{{ $field === 'current_password' ? 'current-password' : 'new-password' }}"
                       @if ($bag->has($field)) aria-invalid="true" aria-describedby="update_{{ $field }}-error" @endif
                       class="block min-h-touch w-full rounded-control border bg-raised px-3 text-base text-fg focus:border-accent focus:ring-accent {{ $bag->has($field) ? 'border-danger' : 'border-edge' }}">
            </x-ui.field>
        @endforeach

        <x-ui.button type="submit">{{ __('Change password') }}</x-ui.button>
    </form>
</x-ui.card>
