<x-layouts.app :title="__('Shift templates')">
    <div class="mb-4 flex flex-wrap items-center gap-3">
        <x-ui.button :href="route('schedule.week')" variant="ghost" icon="chevron-left">{{ __('Schedule') }}</x-ui.button>
        <p class="text-base text-muted">{{ __('Templates fill in times on the schedule. Changing one never moves shifts already assigned. An end time earlier than the start ends the next morning.') }}</p>
    </div>

    <div class="grid max-w-4xl gap-4">
        @foreach ($shifts as $shift)
            @php $mine = old('_form') === "shift-{$shift->id}"; @endphp
            <x-ui.card>
                <form method="POST" action="{{ route('shifts.update', $shift) }}" class="grid items-end gap-4 sm:grid-cols-[1fr_8rem_8rem_auto_auto]">
                    @csrf @method('PUT')
                    <input type="hidden" name="_form" value="shift-{{ $shift->id }}">
                    <x-ui.input :id="'shift-'.$shift->id.'-name'" name="name" :label="__('Name')" :value="$shift->name" maxlength="40" :old="$mine" required />
                    <x-ui.input :id="'shift-'.$shift->id.'-start'" name="start_time" type="time" :label="__('Starts')" :value="$shift->start_time" :old="$mine" required />
                    <x-ui.input :id="'shift-'.$shift->id.'-end'" name="end_time" type="time" :label="__('Ends')" :value="$shift->end_time" :old="$mine" required />
                    <label for="shift-{{ $shift->id }}-active" class="flex min-h-touch items-center gap-3 text-base text-fg">
                        <input type="hidden" name="is_active" value="0">
                        <input id="shift-{{ $shift->id }}-active" name="is_active" type="checkbox" value="1" class="size-6 rounded border-edge bg-raised text-accent focus:ring-accent" @checked($mine ? old('is_active') : $shift->is_active)>
                        {{ __('In use') }}
                    </label>
                    <x-ui.button type="submit" variant="secondary">{{ __('Save') }}<span class="sr-only"> {{ $shift->name }}</span></x-ui.button>
                </form>
            </x-ui.card>
        @endforeach
    </div>
</x-layouts.app>
