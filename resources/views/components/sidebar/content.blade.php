<x-perfect-scrollbar
    as="nav"
    aria-label="{{ __('Main navigation') }}"
    class="flex flex-1 flex-col gap-1 overflow-y-auto px-3 py-4"
>
    <div>
        <p
            class="sidebar-section-label"
            x-show="isSidebarOpen || isSidebarHovered"
            x-cloak
        >
            {{ __('Main') }}
        </p>

        @if (auth()->user()->canAccessRoute('dashboard'))
            <x-sidebar.link
                :title="__('Dashboard')"
                href="{{ route('dashboard') }}"
                :isActive="request()->routeIs('dashboard')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-home class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif
    </div>

    <div class="mt-4">
        <p
            class="sidebar-section-label"
            x-show="isSidebarOpen || isSidebarHovered"
            x-cloak
        >
            {{ __('Operations') }}
        </p>

        @if (auth()->user()->canAccessRoute('master.charge-points'))
            <x-sidebar.link
                :title="__('Charge Points')"
                href="{{ route('master.charge-points') }}"
                :isActive="request()->routeIs('master.charge-points')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-lightning-bolt class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('master.sessions'))
            <x-sidebar.link
                :title="__('Sessions')"
                href="{{ route('master.sessions') }}"
                :isActive="request()->routeIs('master.sessions')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-play class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('master.transactions'))
            <x-sidebar.link
                :title="__('Transactions')"
                href="{{ route('master.transactions') }}"
                :isActive="request()->routeIs('master.transactions')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-document-text class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('ocpp.commands.index'))
            <x-sidebar.link
                :title="__('OCPP Commands')"
                href="{{ route('ocpp.commands.index') }}"
                :isActive="request()->routeIs('ocpp.commands.*')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-terminal class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif
    </div>

    <div class="mt-4">
        <p
            class="sidebar-section-label"
            x-show="isSidebarOpen || isSidebarHovered"
            x-cloak
        >
            {{ __('Administration') }}
        </p>

        @if (auth()->user()->canAccessRoute('master.companies'))
            <x-sidebar.link
                :title="__('Companies')"
                href="{{ route('master.companies') }}"
                :isActive="request()->routeIs('master.companies')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-office-building class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('master.users'))
            <x-sidebar.link
                :title="__('Users')"
                href="{{ route('master.users') }}"
                :isActive="request()->routeIs('master.users')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-user-group class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('access-control.roles.index'))
            <x-sidebar.link
                :title="__('Roles')"
                href="{{ route('access-control.roles.index') }}"
                :isActive="request()->routeIs('access-control.roles.*')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-shield-check class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>

            @if (auth()->user()->canAccessRoute('access-control.permissions.index'))
                <x-sidebar.link
                    :title="__('Permissions')"
                    href="{{ route('access-control.permissions.index') }}"
                    :isActive="request()->routeIs('access-control.permissions.*')"
                >
                    <x-slot name="icon">
                        <x-heroicon-o-lock-closed class="h-5 w-5 shrink-0" aria-hidden="true" />
                    </x-slot>
                </x-sidebar.link>
            @endif
        @endif
    </div>

    <div class="mt-4">
        <p
            class="sidebar-section-label"
            x-show="isSidebarOpen || isSidebarHovered"
            x-cloak
        >
            {{ __('Master Data') }}
        </p>

        @if (auth()->user()->canAccessRoute('master.connector-types'))
            <x-sidebar.link
                :title="__('Connector Types')"
                href="{{ route('master.connector-types') }}"
                :isActive="request()->routeIs('master.connector-types')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-chip class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('master.stop-reasons'))
            <x-sidebar.link
                :title="__('Stop Reasons')"
                href="{{ route('master.stop-reasons') }}"
                :isActive="request()->routeIs('master.stop-reasons')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-stop class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('master.ocpp-versions'))
            <x-sidebar.link
                :title="__('OCPP Versions')"
                href="{{ route('master.ocpp-versions') }}"
                :isActive="request()->routeIs('master.ocpp-versions')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-code class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('master.connector-statuses'))
            <x-sidebar.link
                :title="__('Connector Statuses')"
                href="{{ route('master.connector-statuses') }}"
                :isActive="request()->routeIs('master.connector-statuses')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-status-online class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('master.transaction-statuses'))
            <x-sidebar.link
                :title="__('Transaction Statuses')"
                href="{{ route('master.transaction-statuses') }}"
                :isActive="request()->routeIs('master.transaction-statuses')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-switch-horizontal class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('master.ocpp-actions'))
            <x-sidebar.link
                :title="__('OCPP Actions')"
                href="{{ route('master.ocpp-actions') }}"
                :isActive="request()->routeIs('master.ocpp-actions')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-collection class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('master.meter-measurands'))
            <x-sidebar.link
                :title="__('Meter Measurands')"
                href="{{ route('master.meter-measurands') }}"
                :isActive="request()->routeIs('master.meter-measurands')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-chart-square-bar class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('master.reservation-statuses'))
            <x-sidebar.link
                :title="__('Reservation Statuses')"
                href="{{ route('master.reservation-statuses') }}"
                :isActive="request()->routeIs('master.reservation-statuses')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-calendar class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif

        @if (auth()->user()->canAccessRoute('master.diagnostics-statuses'))
            <x-sidebar.link
                :title="__('Diagnostics Statuses')"
                href="{{ route('master.diagnostics-statuses') }}"
                :isActive="request()->routeIs('master.diagnostics-statuses')"
            >
                <x-slot name="icon">
                    <x-heroicon-o-document-report class="h-5 w-5 shrink-0" aria-hidden="true" />
                </x-slot>
            </x-sidebar.link>
        @endif
    </div>
</x-perfect-scrollbar>
