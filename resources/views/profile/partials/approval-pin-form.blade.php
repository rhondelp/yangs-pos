<x-ui.card :title="__('Approval PIN')">
    @if (session('status') === 'approval-pin-updated')
        <x-ui.alert variant="success" class="mb-5">{{ __('Approval PIN saved.') }}</x-ui.alert>
    @endif

    <p class="mb-5 text-base text-muted">
        {{ $user->approval_pin ? __('Change the PIN you enter to approve voids, refunds and corrections.') : __('Set a PIN so you can approve voids, refunds and corrections on a cashier\'s tablet.') }}
    </p>

    @php($bag = $errors->approvalPin)
    <form method="POST" action="{{ route('profile.approval-pin') }}" class="space-y-5">
        @csrf
        @method('PUT')

        @foreach (['approval_pin' => __('New PIN (4 to 6 digits)'), 'approval_pin_confirmation' => __('Confirm PIN')] as $field => $label)
            <x-ui.field :id="$field" :label="$label" :error="$bag->first($field)" :required="true">
                <input id="{{ $field }}" name="{{ $field }}" type="password" inputmode="numeric" pattern="\d{4,6}" maxlength="6" required autocomplete="off"
                       @if ($bag->has($field)) aria-invalid="true" aria-describedby="{{ $field }}-error" @endif
                       class="block min-h-touch w-full rounded-control border bg-raised px-3 text-base text-fg focus:border-accent focus:ring-accent {{ $bag->has($field) ? 'border-danger' : 'border-edge' }}">
            </x-ui.field>
        @endforeach

        <x-ui.button type="submit">{{ __('Save PIN') }}</x-ui.button>
    </form>
</x-ui.card>
