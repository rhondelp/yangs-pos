@csrf
<div class="space-y-5">
    <x-ui.input name="label" :label="__('Table name')" :value="$table->label" :hint="__('What staff call it, e.g. T1, Booth 2, Bar 3.')" required autofocus maxlength="30" />
    <x-ui.input name="capacity" type="number" min="1" max="99" inputmode="numeric" :label="__('Seats (optional)')" :value="$table->capacity" />
    <x-ui.input name="sort_order" type="number" min="0" inputmode="numeric" :label="__('Position on the floor (optional)')" :value="$table->exists ? $table->sort_order : null"
                :hint="__('Lower comes first. New tables go to the end.')" />
    <div class="flex flex-wrap gap-3">
        <x-ui.button type="submit">{{ $table->exists ? __('Save table') : __('Add table') }}</x-ui.button>
        <x-ui.button :href="route('tables.index')" variant="ghost">{{ __('Cancel') }}</x-ui.button>
    </div>
</div>
