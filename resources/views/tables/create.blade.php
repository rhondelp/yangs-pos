<x-layouts.app :title="__('New table')">
    <x-ui.card class="max-w-xl">
        <form method="POST" action="{{ route('tables.store') }}">
            @include('tables._form')
        </form>
    </x-ui.card>
</x-layouts.app>
