# CSMS Testing Tools

Tools untuk testing CSMS (Charging Station Management System) dengan protokol OCPP 1.6.

## Setup Testing

```bash
cd testing
npm install
```

## Testing Tools

### 1. API Testing
Test semua REST API endpoints:

```bash
npm run api-test
```

**Yang ditest:**
- ✅ Login authentication
- ✅ Stations CRUD operations
- ✅ Transactions retrieval
- ✅ Authorization validation

### 2. OCPP Message Testing
Test individual OCPP messages:

```bash
node ocpp-test.js [CHARGE_POINT_ID]
```

**OCPP Messages yang ditest:**
- ✅ BootNotification
- ✅ Heartbeat
- ✅ StatusNotification
- ✅ Authorize
- ✅ StartTransaction
- ✅ StopTransaction
- ✅ MeterValues

### 3. Charging Station Simulator
Simulasi charging station lengkap:

```bash
npm run test-station [CHARGE_POINT_ID]
```

**Fitur Simulator:**
- 🔌 Auto-connect ke OCPP server
- 💓 Heartbeat otomatis
- 🔄 Transaction simulation
- 📊 Status notifications
- ⚡ Meter values

### 4. Advanced Test (NEW)
Test lengkap dengan monitoring 1 menit dan disconnect:

```bash
npm run advanced-test [CHARGE_POINT_ID]
```

**Fitur Advanced Test:**
- 🔌 Full transaction lifecycle
- ⏱️ 60 detik monitoring real-time
- ⚡ Meter values setiap 10 detik
- 📊 Real-time power, voltage, current
- 🔌 Disconnect test
- 📈 Summary report

### 5. Load Test (NEW)
Test multiple charging stations sekaligus:

```bash
npm run load-test [STATION_COUNT]
```

**Fitur Load Test:**
- 🚀 Multiple stations (default: 5)
- 🔄 Concurrent transactions
- 🔌 Random disconnect/reconnect
- 📊 Performance metrics
- 📈 Success rate calculation

## Contoh Penggunaan

### 1. Test API
```bash
cd testing
npm run api-test
```

Output:
```
🏥 Checking server health...
✅ Server is healthy
🔐 Testing login...
✅ Login successful
📍 Testing stations API...
✅ Found 0 stations
✅ Station created
💳 Testing transactions API...
✅ Found 0 transactions
🚫 Testing invalid authentication...
✅ Correctly rejected unauthorized request

📊 Test Results:
================
✅ login: PASSED
✅ stations: PASSED
✅ transactions: PASSED
✅ invalidAuth: PASSED

🎯 Overall: 4/4 tests passed
```

### 2. Test OCPP Messages
```bash
cd testing
node ocpp-test.js TEST_STATION_01
```

### 4. Advanced Test - Monitoring 1 Menit
```bash
cd testing
npm run advanced-test STATION_ADVANCED_01
```

Output:
```
🧪 Starting Advanced OCPP Test...

1️⃣ Sending BootNotification...
2️⃣ Setting status to Available...
3️⃣ Authorizing user...
4️⃣ Setting status to Preparing...
5️⃣ Starting transaction...
🔋 Transaction started: 123456
6️⃣ Setting status to Charging...
7️⃣ Monitoring charging for 60 seconds...
📊 Real-time monitoring started:
⚡ Charging... 10s | Energy: 1015Wh | Power: 15.2kW
⚡ Charging... 20s | Energy: 1028Wh | Power: 18.7kW
⏱️ Time remaining: 40s
8️⃣ Stopping transaction...
9️⃣ Setting status back to Available...
🔟 Testing disconnect...

📊 Test Summary:
================
⏱️ Total test time: 75s
🔋 Transaction ID: 123456
⚡ Energy consumed: ~45Wh
📈 Final meter reading: 1045Wh
✅ Advanced test completed successfully!
```

### 5. Load Test - Multiple Stations
```bash
cd testing
npm run load-test 10
```

Output:
```
🚀 Starting Load Test with 10 stations...

🔌 Connecting all stations...
✅ All 10 stations connected

📊 Starting load test sequence...

Phase 1: Boot notifications...
Phase 2: Starting transactions...
Phase 3: Monitoring (30s)...
⏱️ Monitoring: 15s remaining | Messages: 245 | Errors: 2
Phase 4: Stopping transactions...
Phase 5: Disconnect test...
🔌 Disconnecting 5 stations randomly...
🔌 Reconnecting 5 stations...

📊 Load Test Results:
====================
🔌 Stations: 10
✅ Connected: 10
🔋 Transactions: 10
📨 Messages sent: 287
❌ Errors: 3
📈 Success rate: 98.9%
```

## Prerequisites

Pastikan CSMS backend sudah berjalan:

```bash
# Terminal 1 - Backend
cd backend
npm run dev

# Terminal 2 - Testing
cd testing
npm install
npm run api-test
```

## Custom Testing

### Test dengan Charge Point ID berbeda:
```bash
node ocpp-test.js STATION_ABC_123
npm run test-station STATION_XYZ_456
```

### Test dengan server berbeda:
Edit file `ocpp-test.js` atau `ocpp-simulator.js`:
```javascript
const serverUrl = 'ws://your-server:8080';
```

## Troubleshooting

### Connection Failed
- ✅ Pastikan backend server berjalan di port 3000
- ✅ Pastikan OCPP server berjalan di port 8080
- ✅ Check firewall settings

### API Tests Failed
- ✅ Pastikan MySQL database berjalan
- ✅ Check database connection di backend
- ✅ Verify default admin user exists

### OCPP Tests Failed
- ✅ Check WebSocket connection
- ✅ Verify OCPP server implementation
- ✅ Check message format compliance