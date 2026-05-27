@props([
    'isActive' => false,
    'title' => '',
    'collapsible' => false,
    'disabled' => false,
])

@php
    if ($disabled) {
        $stateClass = 'sidebar-nav-link--disabled';
    } elseif ($isActive) {
        $stateClass = 'sidebar-nav-link--active';
    } else {
        $stateClass = 'sidebar-nav-link--inactive';
    }

    $classes = 'sidebar-nav-link w-full ' . $stateClass;
@endphp

@if ($collapsible)
    <button
        type="button"
        {{ $attributes->merge(['class' => $classes]) }}
        @if($disabled) disabled @endif
    >
        @if ($icon ?? false)
            {{ $icon }}
        @endif

        <span class="truncate" x-show="isSidebarOpen || isSidebarHovered" x-cloak>
            {{ $title }}
        </span>
    </button>
@elseif ($disabled)
    <span {{ $attributes->merge(['class' => $classes]) }} aria-disabled="true">
        @if ($icon ?? false)
            {{ $icon }}
        @endif

        <span class="flex min-w-0 flex-1 items-center justify-between gap-2 truncate" x-show="isSidebarOpen || isSidebarHovered" x-cloak>
            <span>{{ $title }}</span>
            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:bg-dark-eval-2 dark:text-gray-500">
                {{ __('Soon') }}
            </span>
        </span>
    </span>
@else
    <a {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon ?? false)
            {{ $icon }}
        @endif

        <span class="truncate" x-show="isSidebarOpen || isSidebarHovered" x-cloak>
            {{ $title }}
        </span>
    </a>
@endif
