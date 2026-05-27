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

