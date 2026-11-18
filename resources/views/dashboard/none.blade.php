<x-layouts.app :title="__('Dashboard')">
    <x-ui.empty-state :title="__('Your role has no dashboard yet')" icon="shield-check">
        {{ __('Ask the owner to give your role the permissions you need. Pages appear in the menu as soon as you have access.') }}
    </x-ui.empty-state>
</x-layouts.app>
