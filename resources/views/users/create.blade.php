<x-layouts.app :title="__('New user')">
    <x-ui.card class="max-w-xl">
        <form method="POST" action="{{ route('users.store') }}">
            @include('users._form')
        </form>
    </x-ui.card>
</x-layouts.app>
