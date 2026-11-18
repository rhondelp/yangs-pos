<x-layouts.app :title="__('Edit table :label', ['label' => $table->label])">
    <x-ui.card class="max-w-xl">
        <form method="POST" action="{{ route('tables.update', $table) }}">
            @method('PUT')
            @include('tables._form')
        </form>
    </x-ui.card>
</x-layouts.app>
