@php
    $initials = collect(explode(' ', Auth::user()->name))
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<header class="sticky top-0 z-20 flex h-16 items-center justify-between gap-4 border-b border-gray-200 bg-white/95 px-4 backdrop-blur-sm dark:border-gray-800 dark:bg-dark-eval-1/95 sm:px-6 lg:px-8">
    <div class="flex min-w-0 items-center gap-3">
        <button
            type="button"
            class="inline-flex rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 lg:hidden dark:hover:bg-dark-eval-2 dark:hover:text-gray-200"
            x-on:click="isSidebarOpen = !isSidebarOpen"
            aria-label="{{ __('Toggle menu') }}"
        >
            <x-heroicon-o-menu class="h-5 w-5" aria-hidden="true" />
        </button>

        <div class="min-w-0 lg:hidden">
            <p class="truncate text-sm font-semibold text-gray-900 dark:text-white">
                {{ config('app.name', 'CSMS') }}
            </p>
        </div>
    </div>

    <div class="flex items-center gap-2 sm:gap-3">
        <button
            type="button"
            class="inline-flex rounded-lg p-2 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 dark:hover:bg-dark-eval-2 dark:hover:text-gray-200"
            x-on:click="toggleTheme"
            aria-label="{{ __('Toggle dark mode') }}"
        >
            <x-heroicon-o-moon x-show="!isDarkMode" class="h-5 w-5" aria-hidden="true" />
            <x-heroicon-o-sun x-show="isDarkMode" class="h-5 w-5" aria-hidden="true" />
        </button>

        <x-dropdown align="right" width="48">
            <x-slot name="trigger">
                <button
                    type="button"
                    class="flex items-center gap-2 rounded-lg py-1.5 pl-1.5 pr-2 text-sm font-medium text-gray-700 hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 dark:text-gray-200 dark:hover:bg-dark-eval-2"
                >
                    <span class="flex h-8 w-8 items-center justify-center rounded-full bg-brand-100 text-xs font-semibold text-brand-800 dark:bg-brand-950/60 dark:text-brand-300">
                        {{ strtoupper($initials) }}
                    </span>
                    <span class="hidden max-w-[8rem] truncate sm:inline">
                        {{ Auth::user()->name }}
                    </span>
                    <x-heroicon-o-chevron-down class="hidden h-4 w-4 text-gray-400 sm:block" aria-hidden="true" />
                </button>
            </x-slot>

            <x-slot name="content">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-gray-700">
                    <p class="truncate text-sm font-medium text-gray-900 dark:text-white">
                        {{ Auth::user()->name }}
                    </p>
                    <p class="truncate text-xs text-gray-500 dark:text-gray-400">
                        {{ Auth::user()->email }}
                    </p>
                </div>

                <x-dropdown-link :href="route('profile.edit')">
                    {{ __('Profile') }}
                </x-dropdown-link>

                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <x-dropdown-link
                        :href="route('logout')"
                        onclick="event.preventDefault(); this.closest('form').submit();"
                    >
                        {{ __('Log out') }}
                    </x-dropdown-link>
                </form>
            </x-slot>
        </x-dropdown>
    </div>
</header>
