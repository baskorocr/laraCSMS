@props(['errors'])

@if ($errors->any())
    <div
        {{ $attributes->merge(['class' => 'mb-6 rounded-lg border border-red-200 bg-red-50 p-4 dark:border-red-900/50 dark:bg-red-950/30']) }}
        role="alert"
    >
        <div class="flex gap-3">
            <x-heroicon-o-exclamation-circle class="h-5 w-5 flex-shrink-0 text-red-600 dark:text-red-400" aria-hidden="true" />
            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-red-800 dark:text-red-300">
                    {{ __('Please correct the following:') }}
                </p>
                <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-red-700 dark:text-red-400">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
@endif
