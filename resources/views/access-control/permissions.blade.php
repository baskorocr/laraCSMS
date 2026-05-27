<x-app-layout>
    <x-slot name="header">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold tracking-tight text-gray-900 dark:text-white">Permissions</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Tambah permission manual. Setelah simpan akan langsung diarahkan ke halaman Roles.</p>
            </div>
            <a href="{{ route('access-control.roles.index') }}" class="inline-flex rounded-lg border border-gray-300 px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50 dark:border-gray-700 dark:text-gray-200 dark:hover:bg-dark-eval-2">
                Buka Roles
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

        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-dark-eval-1">
            <form method="POST" action="{{ route('access-control.permissions.store') }}" class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-dark-eval-1">
                @csrf
                <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Tambah Permission Manual</h2>
                <input name="name" class="auth-input mt-3 w-full px-3 py-2 text-sm" placeholder="contoh: master.users" required>
                <button type="submit" class="mt-3 inline-flex rounded-lg bg-brand-700 px-4 py-2 text-sm font-medium text-white hover:bg-brand-800">Tambah Permission</button>
            </form>
        </section>

        <section class="rounded-xl border border-gray-200 bg-white p-5 dark:border-gray-700 dark:bg-dark-eval-1">
            <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Permission Tersedia</h2>
            <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($permissions as $permission)
                    <div class="rounded border border-gray-200 px-3 py-2 text-xs text-gray-700 dark:border-gray-700 dark:text-gray-300">
                        {{ $permission->name }}
                    </div>
                @endforeach
            </div>
        </section>
    </div>
</x-app-layout>
