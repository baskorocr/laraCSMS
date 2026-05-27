<div class="border-t border-gray-200 p-3 dark:border-gray-800">
    <x-sidebar.link
        :title="__('Settings')"
        href="{{ route('profile.edit') }}"
        :isActive="request()->routeIs('profile.*')"
    >
        <x-slot name="icon">
            <x-heroicon-o-cog class="h-5 w-5 shrink-0" aria-hidden="true" />
        </x-slot>
    </x-sidebar.link>
</div>
