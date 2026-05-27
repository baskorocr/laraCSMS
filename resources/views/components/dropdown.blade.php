@props([
    'align' => 'right',
    'width' => '48',
    'contentClasses' => 'py-1 bg-white dark:bg-dark-eval-1 border border-gray-200 dark:border-gray-700',
])

@php
    switch ($align) {
        case 'left':
            $alignmentClasses = 'origin-top-left left-0';
            break;
        case 'top':
            $alignmentClasses = 'origin-top';
            break;
        case 'right':
        default:
            $alignmentClasses = 'origin-top-right right-0';
            break;
    }

    switch ($width) {
        case '48':
            $width = 'w-56';
            break;
    }
@endphp

<div
    class="relative"
    x-data="{ open: false }"
    x-on:click.away="open = false"
    x-on:close.stop="open = false"
>
    <div x-on:click="open = ! open">
        {{ $trigger }}
    </div>

    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute z-50 mt-2 {{ $width }} rounded-xl shadow-lg {{ $alignmentClasses }}"
        style="display: none;"
        x-on:click="open = false"
    >
        <div class="overflow-hidden rounded-xl {{ $contentClasses }}">
            {{ $content }}
        </div>
    </div>
</div>
