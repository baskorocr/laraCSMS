<x-app-layout>
    <x-slot name="header">
        <div>
            <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">{{ $title }}</h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $subtitle }}</p>
            @if ($entity === 'charge_points')
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    <span data-csms-pusher-status class="font-medium text-amber-600 dark:text-amber-400">Pusher: menghubungkan...</span>
                    · Status connector realtime via Pusher
                </p>
            @endif
        </div>
    </x-slot>

    <div class="space-y-5">
        @if (session('status'))
            <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700 dark:border-green-900/40 dark:bg-green-950/30 dark:text-green-400">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-400">
                <ul class="list-inside list-disc space-y-1">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-dark-eval-1">
            <form method="GET" class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                <label class="space-y-1 text-sm md:col-span-2">
                    <span class="text-gray-600 dark:text-gray-300">Search</span>
                    <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" class="auth-input px-3 py-2 text-sm" placeholder="Cari data...">
                </label>

                @if ($entity === 'companies')
                    <label class="space-y-1 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">Status</span>
                        <select name="status" class="auth-input px-3 py-2 text-sm">
                            <option value="">Semua</option>
                            <option value="active" @selected(($filters['status'] ?? '') === 'active')>Active</option>
                            <option value="inactive" @selected(($filters['status'] ?? '') === 'inactive')>Inactive</option>
                        </select>
                    </label>
                @elseif ($entity === 'charge_points')
                    <label class="space-y-1 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">Company</span>
                        <select name="company_id" class="auth-input px-3 py-2 text-sm">
                            <option value="">Semua</option>
                            @foreach ($companyOptions as $company)
                                <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="space-y-1 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">Status</span>
                        <select name="status" class="auth-input px-3 py-2 text-sm">
                            <option value="">Semua</option>
                            @foreach ($chargePointStatusOptions as $status)
                                <option value="{{ $status }}" @selected(($filters['status'] ?? '') === $status)>{{ $status }}</option>
                            @endforeach
                        </select>
                    </label>
                @else
                    <label class="space-y-1 text-sm">
                        <span class="text-gray-600 dark:text-gray-300">Company</span>
                        <select name="company_id" class="auth-input px-3 py-2 text-sm">
                            <option value="">Semua</option>
                            @foreach ($companyOptions as $company)
                                <option value="{{ $company->id }}" @selected((string) ($filters['company_id'] ?? '') === (string) $company->id)>{{ $company->name }}</option>
                            @endforeach
                        </select>
                    </label>
                @endif

                <div class="flex items-end gap-2">
                    <button type="submit" class="inline-flex rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">
                        Search
                    </button>
                    <a href="{{ $entity === 'companies' ? route('master.companies') : ($entity === 'charge_points' ? route('master.charge-points') : route('master.users')) }}" class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-dark-eval-2">
                        Reset
                    </a>
                </div>
            </form>
        </section>

        @if ($canManage)
            <section class="flex justify-end">
                <button
                    type="button"
                    x-data=""
                    x-on:click.prevent="$dispatch('open-modal', 'create-master-entity')"
                    class="inline-flex rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800"
                >
                    Tambah Data
                </button>
            </section>

            <x-modal name="create-master-entity" :show="$errors->any()" focusable>
                <form method="POST" action="{{ $entity === 'companies' ? route('master.companies.store') : ($entity === 'users' ? route('master.users.store') : route('master.charge-points.store')) }}" class="p-6">
                    @csrf
                    <h2 class="text-lg font-medium text-gray-900 dark:text-white">
                        Tambah {{ $entity === 'companies' ? 'Company' : ($entity === 'users' ? 'User' : 'Charge Point') }}
                    </h2>
                    <div class="mt-4 grid gap-3 md:grid-cols-2">
                        @if ($entity === 'companies')
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Code</span>
                                <input type="text" name="code" value="{{ old('code') }}" class="auth-input px-3 py-2 text-sm" required>
                            </label>
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Name</span>
                                <input type="text" name="name" value="{{ old('name') }}" class="auth-input px-3 py-2 text-sm" required>
                            </label>
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Timezone</span>
                                <select
                                    name="timezone"
                                    class="auth-input px-3 py-2 text-sm"
                                    data-timezone-select
                                    data-selected="{{ old('timezone', 'Asia/Jakarta') }}"
                                    required
                                >
                                    <option value="{{ old('timezone', 'Asia/Jakarta') }}">{{ old('timezone', 'Asia/Jakarta') }}</option>
                                </select>
                            </label>
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Status</span>
                                <select name="is_active" class="auth-input px-3 py-2 text-sm">
                                    <option value="1">Active</option>
                                    <option value="0">Inactive</option>
                                </select>
                            </label>
                        @elseif ($entity === 'users')
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Company</span>
                                <select name="company_id" class="auth-input px-3 py-2 text-sm" @if (!($isGlobalAdmin ?? false)) required @endif>
                                    @if (($isGlobalAdmin ?? false))
                                        <option value="">Global (tanpa company)</option>
                                    @endif
                                    @foreach ($companyOptions as $company)
                                        <option value="{{ $company->id }}" @selected(((string) old('company_id') === (string) $company->id) || (!old('company_id') && $loop->first && !($isGlobalAdmin ?? false)))>{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Name</span>
                                <input type="text" name="name" value="{{ old('name') }}" class="auth-input px-3 py-2 text-sm" required>
                            </label>
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Email</span>
                                <input type="email" name="email" value="{{ old('email') }}" class="auth-input px-3 py-2 text-sm" required>
                            </label>
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Password</span>
                                <input type="password" name="password" class="auth-input px-3 py-2 text-sm" required>
                            </label>
                            <label class="space-y-1 text-sm md:col-span-2">
                                <span class="text-gray-600 dark:text-gray-300">Role</span>
                                <select name="role_id" class="auth-input px-3 py-2 text-sm" required>
                                    <option value="">Pilih role</option>
                                    @foreach ($roleOptions as $role)
                                        <option value="{{ $role->id }}" @selected((string) old('role_id') === (string) $role->id)>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>
                        @else
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Company</span>
                                <select name="company_id" class="auth-input px-3 py-2 text-sm" required>
                                    @foreach ($companyOptions as $company)
                                        <option value="{{ $company->id }}">{{ $company->name }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Charge Point ID</span>
                                <input type="text" name="charge_point_id" class="auth-input px-3 py-2 text-sm" required>
                            </label>
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Name</span>
                                <input type="text" name="name" class="auth-input px-3 py-2 text-sm" required>
                            </label>
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">OCPP Version</span>
                                <select name="ocpp_version" class="auth-input px-3 py-2 text-sm" required>
                                    @foreach ($ocppVersionOptions as $version)
                                        <option value="{{ $version }}">{{ $version }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Status</span>
                                <select name="status" class="auth-input px-3 py-2 text-sm" required>
                                    @foreach ($chargePointStatusOptions as $status)
                                        <option value="{{ $status }}">{{ $status }}</option>
                                    @endforeach
                                </select>
                            </label>
                            <label class="space-y-1 text-sm">
                                <span class="text-gray-600 dark:text-gray-300">Online</span>
                                <select name="is_online" class="auth-input px-3 py-2 text-sm">
                                    <option value="1">Online</option>
                                    <option value="0" selected>Offline</option>
                                </select>
                            </label>
                        @endif
                    </div>
                    <div class="mt-6 flex justify-end gap-2">
                        <button type="button" x-on:click="$dispatch('close')" class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-dark-eval-2">
                            Batal
                        </button>
                        <button type="submit" class="inline-flex rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">
                            Simpan
                        </button>
                    </div>
                </form>
            </x-modal>
        @endif

        <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-dark-eval-1">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-dark-eval-2">
                        <tr>
                            @if ($entity === 'companies')
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Code</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Timezone</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                            @elseif ($entity === 'users')
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Email</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Company</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Role</th>
                            @else
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Charge Point ID</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">WS OCPP URL</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Name</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Company</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Connectors</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">OCPP</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Online</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">OCPP Payload</th>
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Diagnostics</th>
                            @endif
                            <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Created</th>
                            @if ($canManage)
                                <th class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Action</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($rows as $row)
                            <tr
                                class="hover:bg-gray-50/70 dark:hover:bg-dark-eval-2/60"
                                @if ($entity === 'charge_points')
                                    data-charge-point-row="{{ $row->id }}"
                                @endif
                            >
                                @if ($entity === 'companies')
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->id }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ strtoupper($row->code) }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->name }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->timezone }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->is_active ? 'Active' : 'Inactive' }}</td>
                                @elseif ($entity === 'users')
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->id }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->name }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->email }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->company_name ?? '-' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->role_names ?: '-' }}</td>
                                @else
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->id }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->charge_point_id }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        @php
                                            $ocppWsDirect = \App\Support\OcppWebSocketUrl::directForChargePoint((string) $row->charge_point_id);
                                            $ocppWsSecure = \App\Support\OcppWebSocketUrl::secureForChargePoint((string) $row->charge_point_id);
                                            $ocppWsLocal = \App\Support\OcppWebSocketUrl::localForChargePoint((string) $row->charge_point_id);
                                        @endphp
                                        <div class="max-w-[260px] space-y-1">
                                            <div class="flex items-center gap-1">
                                                <code class="block truncate rounded bg-emerald-50 px-1.5 py-0.5 text-xs font-medium text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-300" title="{{ $ocppWsDirect }}">{{ $ocppWsDirect }}</code>
                                                <button
                                                    type="button"
                                                    class="shrink-0 rounded border border-gray-300 px-1.5 py-0.5 text-[10px] font-medium text-gray-600 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-dark-eval-2"
                                                    data-copy-ocpp-ws
                                                    data-copy-text="{{ $ocppWsDirect }}"
                                                    title="Salin URL untuk charger"
                                                >
                                                    Copy
                                                </button>
                                            </div>
                                            <div class="truncate text-[10px] text-gray-500 dark:text-gray-400" title="{{ $ocppWsSecure }}">
                                                WSS (443): {{ $ocppWsSecure }}
                                            </div>
                                            <div class="truncate text-[10px] text-gray-400 dark:text-gray-500" title="{{ $ocppWsLocal }}">
                                                Lokal: {{ $ocppWsLocal }}
                                            </div>
                                        </div>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->name }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->company_name ?? '-' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        @if ($row->connector_count > 0)
                                            <div class="flex flex-col gap-1">
                                                <span class="font-medium">{{ $row->connector_count }} connector(s)</span>
                                                @if ($row->connector_statuses)
                                                    <div class="flex flex-wrap gap-1">
                                                        @foreach (explode('|', $row->connector_statuses) as $connectorStatus)
                                                            @php
                                                                [$connectorId, $status] = explode(':', $connectorStatus);
                                                                $statusColor = match($status) {
                                                                    'Available' => 'bg-green-100 text-green-800',
                                                                    'Charging', 'Occupied' => 'bg-blue-100 text-blue-800',
                                                                    'Reserved' => 'bg-yellow-100 text-yellow-800',
                                                                    'Faulted', 'Unavailable' => 'bg-red-100 text-red-800',
                                                                    default => 'bg-gray-100 text-gray-800'
                                                                };
                                                            @endphp
                                                            <span
                                                                class="inline-flex items-center rounded px-2 py-0.5 text-xs font-medium {{ $statusColor }}"
                                                                data-connector-badge="{{ $connectorId }}"
                                                            >
                                                                #{{ $connectorId }}: {{ $status }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        @else
                                            <span class="text-gray-400">No connectors</span>
                                        @endif
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $row->ocpp_version }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300" data-charge-point-status>{{ $row->status }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300" data-charge-point-online>{{ $row->is_online ? 'Online' : 'Offline' }}</td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <button
                                            type="button"
                                            class="inline-flex rounded bg-indigo-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-indigo-700"
                                            data-open-ocpp-payload
                                            data-charge-point-id="{{ $row->id }}"
                                            data-charge-point-code="{{ $row->charge_point_id }}"
                                        >
                                            Lihat Payload
                                        </button>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">
                                        <button
                                            type="button"
                                            class="inline-flex rounded bg-amber-600 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-amber-700"
                                            data-open-diagnostics
                                            data-charge-point-id="{{ $row->id }}"
                                            data-charge-point-code="{{ $row->charge_point_id }}"
                                        >
                                            Get Diagnostics
                                        </button>
                                    </td>
                                @endif
                                <td class="whitespace-nowrap px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ (string) $row->created_at }}</td>
                                @if ($canManage)
                                    <td class="px-4 py-3 text-sm">
                                        <form method="POST" action="{{ $entity === 'companies' ? route('master.companies.destroy', $row->id) : ($entity === 'users' ? route('master.users.destroy', $row->id) : route('master.charge-points.destroy', $row->id)) }}" class="mb-2">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="rounded bg-red-600 px-2.5 py-1.5 text-xs font-medium text-white">Delete</button>
                                        </form>

                                        <button
                                            type="button"
                                            x-data=""
                                            x-on:click.prevent="$dispatch('open-modal', 'edit-master-entity-{{ $entity }}-{{ $row->id }}')"
                                            class="rounded bg-brand-700 px-2.5 py-1.5 text-xs font-medium text-white"
                                        >
                                            Edit
                                        </button>

                                        <x-modal name="edit-master-entity-{{ $entity }}-{{ $row->id }}" :show="false" focusable>
                                            <form method="POST" action="{{ $entity === 'companies' ? route('master.companies.update', $row->id) : ($entity === 'users' ? route('master.users.update', $row->id) : route('master.charge-points.update', $row->id)) }}" class="p-6">
                                                @csrf
                                                @method('PATCH')
                                                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                                    Edit {{ $entity === 'companies' ? 'Company' : ($entity === 'users' ? 'User' : 'Charge Point') }}
                                                </h3>
                                                <div class="mt-4 grid gap-3 md:grid-cols-2">
                                                    @if ($entity === 'companies')
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Code</span>
                                                            <input type="text" name="code" value="{{ $row->code }}" class="auth-input px-2 py-1.5 text-xs" required>
                                                        </label>
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Name</span>
                                                            <input type="text" name="name" value="{{ $row->name }}" class="auth-input px-2 py-1.5 text-xs" required>
                                                        </label>
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Timezone</span>
                                                            <select
                                                                name="timezone"
                                                                class="auth-input px-2 py-1.5 text-xs"
                                                                data-timezone-select
                                                                data-selected="{{ $row->timezone }}"
                                                                required
                                                            >
                                                                <option value="{{ $row->timezone }}">{{ $row->timezone }}</option>
                                                            </select>
                                                        </label>
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Status</span>
                                                            <select name="is_active" class="auth-input px-2 py-1.5 text-xs">
                                                                <option value="1" @selected((bool) $row->is_active)>Active</option>
                                                                <option value="0" @selected(! (bool) $row->is_active)>Inactive</option>
                                                            </select>
                                                        </label>
                                                    @elseif ($entity === 'users')
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Company</span>
                                                            <select name="company_id" class="auth-input px-2 py-1.5 text-xs" @if (!($isGlobalAdmin ?? false)) required @endif>
                                                                @if (($isGlobalAdmin ?? false))
                                                                    <option value="">Global (tanpa company)</option>
                                                                @endif
                                                                @foreach ($companyOptions as $company)
                                                                    <option value="{{ $company->id }}" @selected((string) $row->company_id === (string) $company->id)>{{ $company->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Name</span>
                                                            <input type="text" name="name" value="{{ $row->name }}" class="auth-input px-2 py-1.5 text-xs" required>
                                                        </label>
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Email</span>
                                                            <input type="email" name="email" value="{{ $row->email }}" class="auth-input px-2 py-1.5 text-xs" required>
                                                        </label>
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Password (opsional, min 8)</span>
                                                            <input type="password" name="password" class="auth-input px-2 py-1.5 text-xs">
                                                        </label>
                                                        <label class="space-y-1 md:col-span-2">
                                                            <span class="text-xs text-gray-500">Role</span>
                                                            <select name="role_id" class="auth-input px-2 py-1.5 text-xs" required>
                                                                @foreach ($roleOptions as $role)
                                                                    <option value="{{ $role->id }}" @selected((string) ($row->role_id ?? '') === (string) $role->id)>{{ $role->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                    @else
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Company</span>
                                                            <select name="company_id" class="auth-input px-2 py-1.5 text-xs" required>
                                                                @foreach ($companyOptions as $company)
                                                                    <option value="{{ $company->id }}" @selected((string) $row->company_id === (string) $company->id)>{{ $company->name }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Charge Point ID</span>
                                                            <input type="text" name="charge_point_id" value="{{ $row->charge_point_id }}" class="auth-input px-2 py-1.5 text-xs" required>
                                                        </label>
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Name</span>
                                                            <input type="text" name="name" value="{{ $row->name }}" class="auth-input px-2 py-1.5 text-xs" required>
                                                        </label>
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">OCPP Version</span>
                                                            <select name="ocpp_version" class="auth-input px-2 py-1.5 text-xs" required>
                                                                @foreach ($ocppVersionOptions as $version)
                                                                    <option value="{{ $version }}" @selected($row->ocpp_version === $version)>{{ $version }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Status</span>
                                                            <select name="status" class="auth-input px-2 py-1.5 text-xs" required>
                                                                @foreach ($chargePointStatusOptions as $status)
                                                                    <option value="{{ $status }}" @selected($row->status === $status)>{{ $status }}</option>
                                                                @endforeach
                                                            </select>
                                                        </label>
                                                        <label class="space-y-1">
                                                            <span class="text-xs text-gray-500">Online</span>
                                                            <select name="is_online" class="auth-input px-2 py-1.5 text-xs">
                                                                <option value="1" @selected((bool) $row->is_online)>Online</option>
                                                                <option value="0" @selected(! (bool) $row->is_online)>Offline</option>
                                                            </select>
                                                        </label>
                                                    @endif
                                                </div>
                                                <div class="mt-6 flex justify-end gap-2">
                                                    <button type="button" x-on:click="$dispatch('close')" class="inline-flex rounded-lg border border-gray-300 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-dark-eval-2">
                                                        Batal
                                                    </button>
                                                    <button type="submit" class="inline-flex rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">
                                                        Update
                                                    </button>
                                                </div>
                                            </form>
                                        </x-modal>
                                    </td>
                                @endif
                            </tr>
                        @empty
                            <tr>
                                <td colspan="{{ $entity === 'companies' ? ($canManage ? 7 : 6) : ($entity === 'users' ? ($canManage ? 7 : 6) : ($canManage ? 14 : 13)) }}" class="px-4 py-10 text-center text-sm text-gray-500 dark:text-gray-400">
                                    Data belum tersedia.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    @if ($entity === 'charge_points')
        @include('master.partials.charge-point-realtime')
        @include('master.partials.charge-point-ocpp-payload')
        @include('master.partials.charge-point-diagnostics')
    @endif
</x-app-layout>

@if ($entity === 'charge_points')
    <script>
        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-copy-ocpp-ws]');
            if (!button) {
                return;
            }

            const text = button.dataset.copyText || '';
            if (!text) {
                return;
            }

            navigator.clipboard.writeText(text).then(() => {
                const original = button.textContent;
                button.textContent = 'Copied';
                window.setTimeout(() => {
                    button.textContent = original;
                }, 1200);
            }).catch(() => {
                window.prompt('Salin URL OCPP:', text);
            });
        });
    </script>
@endif

@if ($entity === 'companies')
    <script>
        (function () {
            const endpoint = @json(route('master.timezones'));
            const selects = Array.from(document.querySelectorAll('select[data-timezone-select]'));

            if (!selects.length) {
                return;
            }

            fetch(endpoint, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
            })
                .then((response) => response.ok ? response.json() : Promise.reject(new Error('Failed to load timezones')))
                .then((payload) => {
                    const options = Array.isArray(payload.data) ? payload.data : [];
                    if (!options.length) {
                        return;
                    }

                    selects.forEach((select) => {
                        const selected = select.dataset.selected || 'Asia/Jakarta';
                        select.innerHTML = '';

                        options.forEach((timezone) => {
                            const option = document.createElement('option');
                            option.value = timezone;
                            option.textContent = timezone;
                            option.selected = timezone === selected;
                            select.appendChild(option);
                        });

                        if (!options.includes(selected)) {
                            const fallback = document.createElement('option');
                            fallback.value = selected;
                            fallback.textContent = selected;
                            fallback.selected = true;
                            select.appendChild(fallback);
                        }
                    });
                })
                .catch(() => {
                    // Keep existing fallback option when API is unavailable.
                });
        })();
    </script>
@endif
