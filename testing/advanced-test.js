const WebSocket = require('ws');
const { v4: uuidv4 } = require('uuid');

class AdvancedOCPPTester {
  constructor(chargePointId = 'CP-TEST-001', serverUrl = 'ws://localhost:9001/ocpp') {
    this.chargePointId = chargePointId;
    this.serverUrl = `${serverUrl}/${chargePointId}`;
    this.ws = null;
    this.transactionId = null;
    this.meterValue = 1000;
    this.isCharging = false;
    this.meterInterval = null;
    this.heartbeatInterval = null;
    this.testStartTime = null;
  }

  connect() {
    return new Promise((resolve, reject) => {
      console.log(`🔌 Connecting to ${this.serverUrl}`);
      
      this.ws = new WebSocket(this.serverUrl);

      this.ws.on('open', () => {
        console.log(`✅ Connected as ${this.chargePointId}`);
        this.startHeartbeat();
        resolve();
      });

      this.ws.on('message', (data) => {
        const message = JSON.parse(data.toString());
        this.handleMessage(message);
      });

      this.ws.on('error', (error) => {
        console.error('❌ WebSocket error:', error.message);
        reject(error);
      });

      this.ws.on('close', () => {
        console.log('🔌 Connection closed');
        this.cleanup();
      });
    });
  }

  handleMessage(message) {
    const [messageType, messageId, actionOrPayload, payload] = message;
    
    if (messageType === 3) { // CallResult
      this.handleCallResult(messageId, actionOrPayload);
    } else if (messageType === 4) { // CallError
      console.error(`❌ Error for ${messageId}:`, actionOrPayload, payload);
    } else if (messageType === 2) { // Call from server
      console.log(`📞 Server call: ${actionOrPayload}`, payload);
      
      // Handle remote commands from server
      if (actionOrPayload === 'RemoteStopTransaction') {
        console.log('🛑 Received remote stop command');
        this.handleRemoteStop(payload);
        this.sendCallResult(messageId, { status: 'Accepted' });
      } else {
        this.sendCallResult(messageId, {});
      }
    }
  }

  handleRemoteStop(payload) {
    console.log(`🛑 Handling remote stop for transaction ${payload.transactionId}`);
    
    if (this.transactionId === payload.transactionId) {
      // Stop charging immediately
      this.isCharging = false;
      this.stopMeterValues();
      
      // Send StopTransaction message
      setTimeout(() => {
        this.sendCall('StopTransaction', {
          transactionId: this.transactionId,
          meterStop: this.meterValue,
          timestamp: new Date().toISOString(),
          reason: 'Remote'
        });
        
        // Clear transaction ID
        this.transactionId = null;
        
        // Update status to Available
        this.sendStatusNotification(1, 'Available');
      }, 1000);
    }
  }

  handleCallResult(messageId, payload) {
    console.log(`📨 Response:`, payload);
    
    // Handle StartTransaction response
    if (payload.transactionId && payload.transactionId > 0) {
      this.transactionId = payload.transactionId;
      console.log(`🔋 Transaction started: ${this.transactionId}`);
      this.isCharging = true;
      this.startMeterValues();
    } else if (payload.transactionId === 0) {
      console.log(`❌ Transaction rejected - stopping test`);
      this.isCharging = false;
      this.transactionRejected = true;
    }
  }

  sendMessage(message) {
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify(message));
      return true;
    }
    return false;
  }

  sendCall(action, payload) {
    const messageId = uuidv4();
    const message = [2, messageId, action, payload];
    
    console.log(`📤 ${action}:`, payload);
    this.sendMessage(message);
    
    return messageId;
  }

  sendCallResult(messageId, payload) {
    const message = [3, messageId, payload];
    this.sendMessage(message);
  }

  // OCPP Messages
  sendBootNotification() {
    return this.sendCall('BootNotification', {
      chargePointVendor: 'LaraCSMS Tester',
      chargePointModel: 'CSMS-TEST-v1',
      chargePointSerialNumber: `SN-${this.chargePointId}`,
      firmwareVersion: '1.0.0',
      iccid: '',
      imsi: '',
      meterType: 'Energy.Active.Import.Register',
      meterSerialNumber: `METER-${this.chargePointId}`
    });
  }

  sendHeartbeat() {
    return this.sendCall('Heartbeat', {});
  }

  sendStatusNotification(connectorId = 1, status = 'Available') {
    return this.sendCall('StatusNotification', {
      connectorId,
      status,
      errorCode: 'NoError',
      timestamp: new Date().toISOString()
    });
  }

  sendAuthorize(idTag) {
    return this.sendCall('Authorize', { idTag });
  }

  sendStartTransaction(idTag = 'RFID-TEST-001', connectorId = 1) {
    this.meterValue = Math.floor(Math.random() * 1000) + 500;
    return this.sendCall('StartTransaction', {
      connectorId,
      idTag,
      meterStart: this.meterValue,
      timestamp: new Date().toISOString(),
      reservationId: null
    });
  }

  sendStopTransaction() {
    if (!this.transactionId) {
      console.log('❌ No active transaction to stop');
      return;
    }

    console.log(`🛑 Stopping transaction ${this.transactionId}`);
    
    // Stop charging immediately
    this.isCharging = false;
    this.stopMeterValues();
    
    const result = this.sendCall('StopTransaction', {
      transactionId: this.transactionId,
      meterStop: this.meterValue,
      timestamp: new Date().toISOString(),
      reason: 'Local',
      idTag: 'RFID-TEST-001',
      transactionData: []
    });
    
    // Clear transaction ID
    this.transactionId = null;
    
    return result;
  }

  sendMeterValues() {
    if (!this.isCharging || !this.transactionId) return;

    // Simulate energy consumption (increase meter value)
    this.meterValue += Math.floor(Math.random() * 10) + 5;
    
    const currentPower = (Math.random() * 20 + 5).toFixed(2); // 5-25 kW
    const voltage = (220 + Math.random() * 20).toFixed(1); // 220-240V
    const current = (currentPower * 1000 / voltage / Math.sqrt(3)).toFixed(2); // Calculate current
    const soc = Math.min(100, 20 + (Date.now() - this.testStartTime) / 1000 / 60 * 2).toFixed(1); // Simulate battery charging 2% per minute
    
    this.sendCall('MeterValues', {
      connectorId: 1,
      transactionId: this.transactionId,
      meterValue: [{
        timestamp: new Date().toISOString(),
        sampledValue: [
          {
            value: this.meterValue.toString(),
            measurand: 'Energy.Active.Import.Register',
            unit: 'Wh'
          },
          {
            value: currentPower,
            measurand: 'Power.Active.Import',
            unit: 'kW'
          },
          {
            value: voltage,
            measurand: 'Voltage',
            unit: 'V'
          },
          {
            value: current,
            measurand: 'Current.Import',
            unit: 'A'
          },
          {
            value: soc,
            measurand: 'SoC',
            unit: 'Percent'
          }
        ]
      }]
    });

    // Show progress
    const elapsed = Math.floor((Date.now() - this.testStartTime) / 1000);
    console.log(`⚡ Charging... ${elapsed}s | Energy: ${this.meterValue}Wh | Power: ${currentPower}kW | SoC: ${soc}%`);
  }

  startHeartbeat() {
    this.heartbeatInterval = setInterval(() => {
      this.sendHeartbeat();
    }, 30000); // Every 30 seconds
  }

  startMeterValues() {
    this.meterInterval = setInterval(() => {
      this.sendMeterValues();
    }, 10000); // Every 10 seconds
  }

  stopMeterValues() {
    if (this.meterInterval) {
      console.log('⏹️ Stopping meter values interval');
      clearInterval(this.meterInterval);
      this.meterInterval = null;
    }
  }

  cleanup() {
    if (this.heartbeatInterval) {
      clearInterval(this.heartbeatInterval);
      this.heartbeatInterval = null;
    }
    this.stopMeterValues();
  }

  disconnect() {
    console.log('🔌 Disconnecting...');
    this.cleanup();
    if (this.ws) {
      this.ws.close();
    }
  }

  // Advanced Test Sequence
  async runAdvancedTest() {
    console.log('🧪 Starting Advanced OCPP Test...\n');
    this.testStartTime = Date.now();

    try {
      // 1. Boot Notification
      console.log('1️⃣ Sending BootNotification...');
      this.sendBootNotification();
      await this.sleep(2000);

      // 2. Status Notification - Available
      console.log('2️⃣ Setting status to Available...');
      this.sendStatusNotification(1, 'Available');
      await this.sleep(1000);

      // 3. Authorize
      console.log('3️⃣ Authorizing user...');
      this.sendAuthorize('RFID-TEST-001');
      await this.sleep(2000);

      // 4. Status Notification - Preparing
      console.log('4️⃣ Setting status to Preparing...');
      this.sendStatusNotification(1, 'Preparing');
      await this.sleep(1000);

      // 5. Start Transaction
      console.log('5️⃣ Starting transaction...');
      this.sendStartTransaction('RFID-TEST-001');
      await this.sleep(2000);

      // Check if transaction was rejected
      if (this.transactionRejected) {
        console.log('❌ Transaction was rejected - setting status back to Available');
        this.sendStatusNotification(1, 'Available');
        console.log('🛑 Test stopped due to transaction rejection');
        return;
      }

      // 6. Status Notification - Charging (only if transaction succeeded)
      console.log('6️⃣ Setting status to Charging...');
      this.sendStatusNotification(1, 'Charging');
      await this.sleep(1000);

      // 7. Monitor for 1 minute
      console.log('7️⃣ Monitoring charging for 60 seconds...');
      console.log('📊 Real-time monitoring started:');
      
      const monitoringPromise = new Promise((resolve) => {
        let countdown = 60;
        const countdownInterval = setInterval(() => {
          process.stdout.write(`\r⏱️  Time remaining: ${countdown}s `);
          countdown--;
          
          if (countdown < 0) {
            clearInterval(countdownInterval);
            console.log('\n✅ Monitoring completed');
            resolve();
          }
        }, 1000);
      });

      await monitoringPromise;

      // 8. Stop Transaction
      console.log('8️⃣ Stopping transaction...');
      this.sendStopTransaction();
      await this.sleep(2000);

      // 9. Status Notification - Available
      console.log('9️⃣ Setting status back to Available...');
      this.sendStatusNotification(1, 'Available');
      await this.sleep(1000);

      // 10. Test Disconnect
      console.log('🔟 Testing disconnect...');
      await this.sleep(2000);

      const totalTime = Math.floor((Date.now() - this.testStartTime) / 1000);
      const energyConsumed = this.meterValue - (this.meterValue - 100); // Rough calculation
      
      console.log('\n📊 Test Summary:');
      console.log('================');
      console.log(`⏱️  Total test time: ${totalTime}s`);
      console.log(`🔋 Transaction ID: ${this.transactionId}`);
      console.log(`⚡ Energy consumed: ~${energyConsumed}Wh`);
      console.log(`📈 Final meter reading: ${this.meterValue}Wh`);
      console.log('✅ Advanced test completed successfully!');

    } catch (error) {
      console.error('❌ Test failed:', error.message);
    } finally {
      // Disconnect after 3 seconds
      setTimeout(() => {
        this.disconnect();
        process.exit(0);
      }, 3000);
    }
  }

  sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

// CLI Usage
async function main() {
  const chargePointId = process.argv[2] || 'CP-TEST-001';
  const serverUrl = process.argv[3] || 'ws://localhost:9001/ocpp';
  
  console.log('🚀 LaraCSMS OCPP Advanced Tester');
  console.log('================================');
  console.log(`📍 Charge Point: ${chargePointId}`);
  console.log(`🌐 Server: ${serverUrl}`);
  console.log('');
  
  const tester = new AdvancedOCPPTester(chargePointId, serverUrl);

  try {
    await tester.connect();
    await tester.sleep(1000); // Wait for connection to stabilize
    await tester.runAdvancedTest();
  } catch (error) {
    console.error('❌ Advanced test failed:', error.message);
    process.exit(1);
  }
}

// Handle Ctrl+C gracefully
process.on('SIGINT', () => {
  console.log('\n👋 Test interrupted by user');
  process.exit(0);
});

main().catch(console.error);