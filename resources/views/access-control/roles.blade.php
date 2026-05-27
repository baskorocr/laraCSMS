<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">Roles</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Kelola role dan assignment role ke user.</p>
            </div>
            <a href="{{ route('access-control.permissions.index') }}" class="inline-flex rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-dark-eval-2">
                Buka Permissions
            </a>
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

        <section class="grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('access-control.roles.store') }}" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-dark-eval-1">
                @csrf
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Buat Role</h2>
                <input name="name" class="auth-input mt-3 w-full px-3 py-2 text-sm" placeholder="company_admin" required>
                <select name="company_id" class="auth-input mt-3 w-full px-3 py-2 text-sm" required>
                    @if ($isGlobalAdmin)
                        <option value="0">Global (company: 0)</option>
                    @endif
                    @foreach ($companies as $company)
                        <option value="{{ $company->id }}">{{ $company->name }} ({{ strtoupper($company->code) }})</option>
                    @endforeach
                </select>
                <button type="submit" class="mt-3 inline-flex rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">Simpan Role</button>
            </form>

            <form method="POST" action="{{ route('access-control.users.assign-role', ['user' => 0]) }}" onsubmit="this.action=this.action.replace('/0/','/'+this.user_id.value+'/')" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-dark-eval-1">
                @csrf
                @method('PUT')
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Assign Role ke User</h2>
                <select name="user_id" class="auth-input mt-3 w-full px-3 py-2 text-sm" required>
                    <option value="">Pilih User</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</option>
                    @endforeach
                </select>
                <select name="role_id" class="auth-input mt-3 w-full px-3 py-2 text-sm" required>
                    <option value="">Pilih Role</option>
                    @foreach ($roles as $role)
                        <option value="{{ $role->id }}">{{ $role->name }} [company: {{ $role->company_id ?? 0 }}]</option>
                    @endforeach
                </select>
                <button type="submit" class="mt-3 inline-flex rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">Assign</button>
            </form>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-dark-eval-1">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Role List</h2>
            <p class="mt-1 text-xs text-gray-500">Pilih permission dengan checkbox, lalu update per role.</p>

            <div class="mt-4 grid gap-4">
                @foreach ($roles as $role)
                    <form method="POST" action="{{ route('access-control.roles.sync-permissions', ['role' => $role->id]) }}" class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
                        @csrf
                        @method('PUT')
                        <div class="mb-3">
                            <p class="text-sm font-semibold text-gray-800 dark:text-gray-200">{{ $role->name }}</p>
                            <p class="text-xs text-gray-500">company: {{ $role->company_id ?? 0 }}</p>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($compactPermissions as $permission)
                                <label class="inline-flex items-center gap-2 text-xs text-gray-700 dark:text-gray-300">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value="{{ $permission['name'] }}"
                                        class="rounded border-gray-300 text-brand-700 focus:ring-brand-600"
                                        @checked(
                                            $role->permissions->contains(function ($rolePermission) use ($permission): bool {
                                                $name = $rolePermission->name;
                                                return $name === $permission['name']
                                                    || $name === $permission['name'].'.index'
                                                    || str_starts_with($name, $permission['name'].'.');
                                            })
                                        )
                                    >
                                    <span>{{ $permission['label'] }}</span>
                                </label>
                            @endforeach
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <button type="submit" class="inline-flex rounded bg-brand-700 px-3 py-1.5 text-xs font-medium text-white hover:bg-brand-800">Update Permission Role</button>
                        </div>
                    </form>

                    @unless ($role->name === 'admin' && ($role->company_id === null || (int) $role->company_id === 0))
                        <form method="POST" action="{{ route('access-control.roles.destroy', ['role' => $role->id]) }}" onsubmit="return confirm('Hapus role {{ $role->name }}?');" class="mt-2">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="inline-flex rounded border border-red-300 px-3 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50 dark:border-red-800 dark:text-red-300 dark:hover:bg-red-950/30">
                                Hapus Role
                            </button>
                        </form>
                    @endunless
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
