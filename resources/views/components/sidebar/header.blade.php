<div class="flex h-16 flex-shrink-0 items-center justify-between border-b border-gray-200 px-4 dark:border-gray-800">
    <a
        href="{{ route('dashboard') }}"
        class="flex min-w-0 items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600"
    >
        <x-application-logo class="h-9 w-9 shrink-0" />
        <span
            class="truncate text-sm font-semibold text-gray-900 dark:text-white"
            x-show="isSidebarOpen || isSidebarHovered"
            x-cloak
        >
            {{ config('app.name', 'CSMS') }}
        </span>
    </a>

    <button
        type="button"
        class="hidden rounded-lg p-1.5 text-gray-500 hover:bg-gray-100 hover:text-gray-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-600 lg:inline-flex dark:hover:bg-dark-eval-2 dark:hover:text-gray-200"
        x-show="isSidebarOpen || isSidebarHovered"
        x-on:click="isSidebarOpen = !isSidebarOpen"
        aria-label="{{ __('Collapse sidebar') }}"
    >
        <x-heroicon-o-menu-alt-2 class="h-5 w-5" aria-hidden="true" />
    </button>
</div>
