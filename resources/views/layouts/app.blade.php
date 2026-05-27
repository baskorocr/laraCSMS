<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'CSMS') }}</title>

    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap"
        rel="stylesheet" />

    <style>
        [x-cloak] {
            display: none;
        }
    </style>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="font-sans antialiased">
    <div
        x-data="mainState"
        :class="{ dark: isDarkMode }"
        x-on:resize.window="handleWindowResize"
        x-cloak
    >
        <div class="min-h-screen bg-gray-50 text-gray-900 dark:bg-dark-eval-0 dark:text-gray-100">
            <x-sidebar.sidebar />

            <div
                class="flex min-h-screen flex-col transition-[margin] duration-150"
                :class="{ 'lg:ml-64': isSidebarOpen, 'lg:ml-[4.5rem]': !isSidebarOpen }"
            >
                <x-navbar />

                <div class="flex flex-1 flex-col">
                    @isset($header)
                        <header class="border-b border-gray-200 bg-white px-4 py-5 dark:border-gray-800 dark:bg-dark-eval-1 sm:px-6 lg:px-8">
                            {{ $header }}
                        </header>
                    @endisset

                    <main class="flex-1 px-4 py-6 sm:px-6 lg:px-8">
                        <div class="mx-auto max-w-7xl">
                            {{ $slot }}
                        </div>
                    </main>

                    <x-footer />
                </div>
            </div>
        </div>
    </div>
</body>
</html>
