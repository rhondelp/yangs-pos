{{-- Session flash messages: redirect()->with('status', '...') or ->with('error', '...'). --}}
@if (session('status'))
    <x-ui.alert variant="success" {{ $attributes }}>{{ session('status') }}</x-ui.alert>
@endif
@if (session('error'))
    <x-ui.alert variant="error" {{ $attributes }}>{{ session('error') }}</x-ui.alert>
@endif
