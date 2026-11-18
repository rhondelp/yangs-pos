@props(['title' => null])

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#1a1512">
<title>{{ $title ? $title.' · ' : '' }}{{ config('app.name', 'YANGS') }}</title>

{{-- Falls back to system fonts when the bar is offline. --}}
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=barlow:400,500,600|barlow-semi-condensed:600,700&display=swap" rel="stylesheet">

@vite(['resources/css/app.css', 'resources/js/app.js'])
