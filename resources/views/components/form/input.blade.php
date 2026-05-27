@props([
    'disabled' => false,
    'withicon' => false
])

@php
    $inputClass = $withicon ? 'auth-input-with-icon' : 'auth-input px-4 py-2.5';
@endphp

<input
    {{ $disabled ? 'disabled' : '' }}
    {!! $attributes->merge(['class' => $inputClass . ' disabled:cursor-not-allowed disabled:opacity-60']) !!}
>
