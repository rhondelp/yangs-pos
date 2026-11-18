<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">{{ __('New user') }}</h2>
    </x-slot>

    <div class="py-8">
        <div class="max-w-xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('users.store') }}" class="bg-white p-6 shadow-sm sm:rounded-lg">
                @include('users._form')
            </form>
        </div>
    </div>
</x-app-layout>
