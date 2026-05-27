<x-sidebar.overlay />

<aside
    class="fixed inset-y-0 left-0 z-30 flex flex-col border-r border-gray-200 bg-white dark:border-gray-800 dark:bg-dark-eval-1"
    :class="{
        'w-64 translate-x-0': isSidebarOpen || isSidebarHovered,
        'w-64 -translate-x-full md:w-[4.5rem] md:translate-x-0': !isSidebarOpen && !isSidebarHovered,
    }"
    style="transition-property: width, transform; transition-duration: 150ms;"
    x-on:mouseenter="handleSidebarHover(true)"
    x-on:mouseleave="handleSidebarHover(false)"
>
    <x-sidebar.header />

    <x-sidebar.content />

    <x-sidebar.footer />
</aside>
