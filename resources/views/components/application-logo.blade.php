@props(['variant' => 'default'])

@php
    $isInverse = $variant === 'inverse';
@endphp

<svg
    {{ $attributes->merge(['class' => 'h-10 w-10']) }}
    viewBox="0 0 40 40"
    fill="none"
    xmlns="http://www.w3.org/2000/svg"
    aria-hidden="true"
>
    <rect
        width="40"
        height="40"
        rx="10"
        class="{{ $isInverse ? 'fill-white' : 'fill-brand-700 dark:fill-brand-600' }}"
    />
    <path
        d="M22.5 10L14 22h6.5l-2 10 10.5-14H22l2.5-8z"
        class="{{ $isInverse ? 'fill-brand-700' : 'fill-white' }}"
    />
</svg>
