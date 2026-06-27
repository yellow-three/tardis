<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? 'Login' }} - TARDIS</title>

    @vite(['packages/Tardis/Core/resources/css/app.css', 'packages/Tardis/Core/resources/js/app.js'])
    @livewireStyles
</head>
<body class="min-h-screen bg-base-200 flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-primary rounded-2xl mb-4">
                <span class="text-primary-content text-2xl font-bold">T</span>
            </div>
            <h1 class="text-2xl font-bold">TARDIS Admin</h1>
        </div>

        <div class="card bg-base-100 shadow-xl">
            <div class="card-body">
                {{ $slot }}
            </div>
        </div>

        <p class="text-center text-sm text-base-content/50 mt-6">
            Powered by TARDIS Framework
        </p>
    </div>

    @livewireScripts
</body>
</html>
