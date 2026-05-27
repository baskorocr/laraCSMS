@props([
    'title' => '',
    'description' => '',
])

<div class="mx-auto w-full max-w-md">
    @if ($title)
        <header class="mb-8">
            <h1 class="text-2xl font-semibold tracking-tight text-gray-900 dark:text-white sm:text-3xl">
                {{ $title }}
            </h1>
            @if ($description)
                <p class="mt-2 text-sm leading-relaxed text-gray-500 dark:text-gray-400">
                    {{ $description }}
                </p>
            @endif
        </header>
    @endif

    <div class="rounded-xl border border-gray-200 bg-white p-6 shadow-card sm:p-8 dark:border-gray-700 dark:bg-dark-eval-1">
        {{ $slot }}
    </div>
</div>
