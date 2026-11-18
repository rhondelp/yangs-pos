<x-layouts.app :title="__('Edit :name', ['name' => $user->username])">
    <x-ui.card class="max-w-xl">
        <form method="POST" action="{{ route('users.update', $user) }}">
            @method('PUT')
            @include('users._form')
        </form>
    </x-ui.card>
</x-layouts.app>
