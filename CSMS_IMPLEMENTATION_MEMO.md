# CSMS Implementation Memo

Memo kerja untuk migrasi dan implementasi CSMS dari `E:\project\csms\CIMORINGS-master` ke `E:\project\csms\charging` (Laravel), mengikuti aturan di `.cursor/rules/ocpp-csms.md`.

## 1) Tujuan Utama

- Rapikan bisnis proses CSMS agar maintainable di Laravel.
- Pertahankan compatibility OCPP 1.6.
- Siapkan arsitektur untuk OCPP 2.1 (adapter-based).
- Pastikan multi-tenant + RBAC ketat di backend (`company_id` scoped).

## 2) Source of Truth

- Rules utama: `charging/.cursor/rules/ocpp-csms.md`
- Legacy referensi bisnis proses: `CIMORINGS-master/backend/*`, `CIMORINGS-master/frontend/*`
- Target implementasi: `charging/app/*`, `charging/database/*`, `charging/resources/*`

## 3) Ringkasan Legacy (CIMORINGS) yang Perlu Dipertahankan

- Flow penting yang sudah berjalan:
  - Station onboarding/lifecycle
  - Authorize, StartTransaction, MeterValues, StopTransaction
  - Monitoring realtime
  - Remote command (start/stop/reset/unlock/reservation/diagnostics)
- Kelemahan legacy yang ingin diperbaiki di Laravel:
  - Bisnis logic tersebar & coupling tinggi
  - Kontrak data/dokumen tidak konsisten
  - Beberapa gap security & schema drift

## 4) Struktur Target Laravel (Saat Ini)

- Auth + UI dasar: Breeze + Blade
- RBAC: Spatie Permission, team scope via `company_id`
- Tabel fondasi sudah ada:
  - `companies`
  - `charge_points`
  - `connectors`
  - `transactions`
  - `meter_values`
  - `ocpp_messages_log`
  - `master_connector_types`
  - `master_transaction_stop_reasons`
- Master tambahan sinkron legacy/rules:
  - `master_ocpp_versions`
  - `master_connector_statuses`
  - `master_transaction_statuses`
  - `master_ocpp_actions`
  - `master_meter_measurands`
  - `master_reservation_statuses`
  - `master_diagnostics_statuses`
- Sidebar/menu master data sudah terhubung ke route list.

## 5) Prinsip Arsitektur Wajib (sesuai rules)

- Semua business logic harus di service layer.
- Semua query tenant data harus scope `company_id`.
- Frontend role check tidak boleh jadi sumber otorisasi.
- OCPP message raw wajib tersimpan untuk audit.
- Role company bersifat tenant-scoped, bukan global.
- Admin global boleh bypass tenant scope sesuai aturan.

## 6) Desain Modul yang Harus Dibangun

### 6.1 Core Services (wajib dibuat)

- `OcppMessageService`
- `TransactionService`
- `ChargingService`
- `TenantService`
- `AuthorizationService`

### 6.2 OCPP Adapter

- `Ocpp16Adapter`
- `Ocpp21Adapter`
- Internal abstraction: `ChargePoint -> EVSE -> Connector`

### 6.3 Queue Jobs

- `LogOcppMessageJob`
- `ProcessMeterValuesJob`
- `UpdateChargingStatusJob`
- `FinalizeTransactionJob`

## 7) Roadmap Implementasi (Urutan Kerja)

### Phase 1 - OCPP Ingress Foundation

- Endpoint WebSocket: `/ocpp/{charge_point_id}`
- Validasi CP + mapping `company_id`
- Simpan semua message ke `ocpp_messages_log`
- Dispatcher message type 2/3/4 + idempotency key (`message_uid`)

Status implementasi saat ini:
- `php artisan ocpp:serve` sudah tersedia sebagai server ingress WS.
- Fondasi adapter `Ocpp16Adapter` dan `Ocpp21Adapter` sudah dibuat.
- Service `OcppMessageService` sudah menangani parsing frame, logging incoming/outgoing, dan response dasar CALLRESULT/CALLERROR.
- Unik indeks idempotency untuk log sudah ditambahkan (`charge_point_id + direction + message_uid`).
- `ChargingService` dan `TransactionService` sudah ditambahkan untuk flow action inti.
- Queue job dasar action inti sudah tersedia:
  - `ProcessStatusNotificationJob`
  - `ProcessMeterValuesJob`
  - `ProcessStopTransactionJob`
  - `ProcessStartTransactionJob` (placeholder untuk async extension)
- Command simulator lokal sudah tersedia: `php artisan ocpp:simulate`.
- Outbound command orchestration sudah mulai tersedia:
  - tabel `ocpp_command_requests`
  - command queue manual: `php artisan ocpp:command`
  - correlation CALLRESULT/CALLERROR berdasarkan `message_uid`
  - dispatch pending command otomatis ketika charge point terhubung dan mengirim frame.

Tambahan terbaru:
- Halaman monitoring command queue tersedia di web: `ocpp/commands`.
- Pengiriman command dari UI ke queue tersedia (dengan preset RemoteStart/Stop/Reset/Unlock/ReserveNow/GetDiagnostics).
- Command maintenance retry/timeout tersedia:
  - `php artisan ocpp:commands:reconcile --timeout=30 --max-attempts=3`

### Phase 2 - Domain Service + Queue

- Pindahkan logic dari controller ke service.
- Process OCPP via queue (non-blocking).
- Implement state transition validator transaksi.

### Phase 3 - Billing & Policy

- Tambah model pricing per company/charger.
- Kalkulasi `total_kWh`, `total_cost`, `currency`.
- Enforcement permission per action backend.

### Phase 4 - Realtime Dashboard

- Event broadcasting per tenant:
  - `company.{company_id}.chargers`
  - `company.{company_id}.transactions`
- Dashboard admin global + dashboard company scoped.

## 8) Konvensi Data Multi-Tenant

- Semua tabel domain tenant harus punya `company_id`.
- Kecuali data global (`permissions`, dan data admin global tertentu).
- Query pattern wajib:
  - user biasa/company admin: `where company_id = auth()->user()->company_id`
  - admin global: bisa all tenant

## 9) Catatan RBAC

- Role global:
  - `admin` (global super admin)
- Role tenant:
  - `company_admin`
  - `company_user`
- Permission catalog global, assignment role per tenant.

## 10) Checklist Sebelum Masuk Fitur Besar

- [ ] WebSocket OCPP endpoint aktif dan stabil
- [ ] Message logging raw berjalan
- [ ] Idempotency untuk message duplicate
- [ ] Transaction state machine tervalidasi
- [ ] Queue worker aktif untuk proses async
- [ ] Tenant scope test pass
- [ ] RBAC test pass

## 11) Cara Pakai Memo Ini

- Buka file ini sebelum mulai task baru.
- Cocokkan task ke phase roadmap.
- Pastikan keputusan teknis tidak melanggar rules.
- Update memo setiap selesai milestone besar.

## 12) Command Artisan yang Harus Dijalankan

### Setup Awal (Sekali Saja)

```bash
# 1. Install dependencies
composer install
npm install

# 2. Setup environment
cp .env.example .env
php artisan key:generate

# 3. Setup database
php artisan migrate
php artisan db:seed

# 4. Sync permissions dari routes
php artisan permissions:sync-routes

# 5. Buat permission tambahan untuk CRUD operations
php artisan tinker --execute="
\$missingPermissions = [
    'access-control.roles.sync-permissions',
    'access-control.roles.store',
    'access-control.roles.destroy',
    'access-control.permissions.store',
    'access-control.permissions.sync-routes',
    'access-control.users.assign-role',
    'master.catalog.store',
    'master.catalog.update',
    'master.catalog.destroy',
    'master.companies.store',
    'master.companies.update',
    'master.companies.destroy',
    'master.charge-points.store',
    'master.charge-points.update',
    'master.charge-points.destroy',
    'master.users.store',
    'master.users.update',
    'master.users.destroy',
    'ocpp.commands.store',
];
foreach (\$missingPermissions as \$name) {
    \Spatie\Permission\Models\Permission::firstOrCreate(['name' => \$name, 'guard_name' => 'web']);
}
\$admin = \Spatie\Permission\Models\Role::where('name', 'admin')->first();
\$allPermissions = \Spatie\Permission\Models\Permission::all();
\$admin->syncPermissions(\$allPermissions);
echo 'Admin role synced with all permissions';
"

# 6. Clear cache
php artisan cache:clear
php artisan config:clear

# 7. Build frontend assets
npm run build
```

### Development (Setiap Hari)

**Opsi 1: Satu Command (Recommended)**
```bash
# Terminal 1: Jalankan semua service Laravel
composer run dev
# Ini akan menjalankan:
# - php artisan serve (port 8000)
# - php artisan queue:listen
# - php artisan pail (log viewer)
# - npm run dev (Vite)

# Terminal 2: OCPP WebSocket Server
php artisan ocpp:serve
```

**Opsi 2: Manual (Jika perlu kontrol lebih)**
```bash
# Terminal 1: Web server
php artisan serve

# Terminal 2: Queue worker
php artisan queue:listen

# Terminal 3: OCPP server
php artisan ocpp:serve

# Terminal 4: Frontend dev server
npm run dev

# Terminal 5 (optional): Log viewer
php artisan pail
```

### Broadcasting Setup

**Jika pakai Pusher (Cloud):**
```bash
# Update .env
BROADCAST_CONNECTION=pusher
VITE_ENABLE_PUSHER=true
VITE_ENABLE_REVERB=false

# Rebuild frontend
npm run build
```

**Jika pakai Reverb (Self-hosted):**
```bash
# Update .env
BROADCAST_CONNECTION=reverb
VITE_ENABLE_REVERB=true
VITE_ENABLE_PUSHER=false

# Jalankan Reverb server (Terminal tambahan)
php artisan reverb:start
```

### Testing OCPP

```bash
# Test dengan simulator
php artisan ocpp:simulate CP-acme-001

# Send command manual
php artisan ocpp:command CP-acme-001 Reset '{"type":"Soft"}'

# Reconcile timeout commands
php artisan ocpp:commands:reconcile --timeout=30 --max-attempts=3
```

### Maintenance

```bash
# Kill stale OCPP server
php artisan ocpp:kill --port=9001

# Sync permissions setelah tambah route baru
php artisan permissions:sync-routes

# Test realtime event
php artisan realtime:test CP-acme-001
```

### Troubleshooting

```bash
# Clear semua cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Rebuild autoload
composer dump-autoload

# Check queue jobs
php artisan queue:work --once

# Check database connection
php artisan tinker --execute="DB::connection()->getPdo();"
```

