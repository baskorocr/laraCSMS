const WebSocket = require('ws');
const { v4: uuidv4 } = require('uuid');

class OCPP21Tester {
  constructor(chargePointId = 'CP-TEST-201', serverUrl = 'ws://localhost:9001/ocpp') {
    this.chargePointId = chargePointId;
    this.serverUrl = `${serverUrl}/${chargePointId}`;
    this.ws = null;
    this.transactionId = uuidv4();
    this.meterValue = 1000;
    this.isCharging = false;
    this.meterInterval = null;
    this.heartbeatInterval = null;
    this.testStartTime = null;
  }

  connect() {
    return new Promise((resolve, reject) => {
      console.log(`🔌 Connecting to ${this.serverUrl} (OCPP 2.1)`);
      
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
    
    if (messageType === 3) {
      console.log(`📨 Response:`, actionOrPayload);
    } else if (messageType === 4) {
      console.error(`❌ Error for ${messageId}:`, actionOrPayload, payload);
    } else if (messageType === 2) {
      console.log(`📞 Server call: ${actionOrPayload}`, payload);
      this.sendCallResult(messageId, {});
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

  // OCPP 2.1 Messages
  sendBootNotification() {
    return this.sendCall('BootNotification', {
      reason: 'PowerUp',
      chargingStation: {
        model: 'CSMS-TEST-v2.1',
        vendorName: 'LaraCSMS Tester',
        serialNumber: `SN-${this.chargePointId}`,
        firmwareVersion: '2.1.0',
        modem: {
          iccid: '',
          imsi: ''
        }
      }
    });
  }

  sendHeartbeat() {
    return this.sendCall('Heartbeat', {});
  }

  sendStatusNotification(evseId = 1, connectorStatus = 'Available') {
    return this.sendCall('StatusNotification', {
      timestamp: new Date().toISOString(),
      connectorStatus,
      evseId,
      connectorId: 1
    });
  }

  sendAuthorize(idToken) {
    return this.sendCall('Authorize', {
      idToken: {
        idToken,
        type: 'ISO14443'
      }
    });
  }

  sendTransactionEvent(eventType, triggerReason = 'Authorized') {
    const sampledValue = [];
    
    if (eventType === 'Started') {
      this.meterValue = Math.floor(Math.random() * 1000) + 500;
      sampledValue.push({
        value: this.meterValue,
        measurand: 'Energy.Active.Import.Register',
        unitOfMeasure: { unit: 'Wh' }
      });
    } else if (eventType === 'Ended') {
      sampledValue.push({
        value: this.meterValue,
        measurand: 'Energy.Active.Import.Register',
        unitOfMeasure: { unit: 'Wh' }
      });
    } else if (eventType === 'Updated') {
      this.meterValue += Math.floor(Math.random() * 10) + 5;
      
      const currentPower = (Math.random() * 20 + 5).toFixed(3);
      const voltage = (220 + Math.random() * 20).toFixed(1);
      const current = (currentPower * 1000 / voltage / Math.sqrt(3)).toFixed(2);
      const soc = Math.min(100, 20 + (Date.now() - this.testStartTime) / 1000 / 60 * 2).toFixed(1);
      
      sampledValue.push(
        {
          value: this.meterValue,
          measurand: 'Energy.Active.Import.Register',
          unitOfMeasure: { unit: 'Wh' }
        },
        {
          value: parseFloat(currentPower),
          measurand: 'Power.Active.Import',
          unitOfMeasure: { unit: 'kW' }
        },
        {
          value: parseFloat(voltage),
          measurand: 'Voltage',
          unitOfMeasure: { unit: 'V' }
        },
        {
          value: parseFloat(current),
          measurand: 'Current.Import',
          unitOfMeasure: { unit: 'A' }
        },
        {
          value: parseFloat(soc),
          measurand: 'SoC',
          unitOfMeasure: { unit: 'Percent' }
        }
      );

      const elapsed = Math.floor((Date.now() - this.testStartTime) / 1000);
      console.log(`⚡ Charging... ${elapsed}s | Energy: ${this.meterValue}Wh | Power: ${currentPower}kW | SoC: ${soc}%`);
    }

    return this.sendCall('TransactionEvent', {
      eventType,
      timestamp: new Date().toISOString(),
      triggerReason,
      seqNo: 0,
      transactionInfo: {
        transactionId: this.transactionId,
        chargingState: eventType === 'Started' ? 'Charging' : eventType === 'Ended' ? 'Idle' : 'Charging'
      },
      evse: {
        id: 1,
        connectorId: 1
      },
      idToken: {
        idToken: 'RFID-TEST-001',
        type: 'ISO14443'
      },
      meterValue: sampledValue.length > 0 ? [{
        timestamp: new Date().toISOString(),
        sampledValue
      }] : undefined
    });
  }

  sendMeterValues() {
    if (!this.isCharging) return;

    this.meterValue += Math.floor(Math.random() * 10) + 5;
    
    const currentPower = (Math.random() * 20 + 5).toFixed(3);
    const voltage = (220 + Math.random() * 20).toFixed(1);
    const current = (currentPower * 1000 / voltage / Math.sqrt(3)).toFixed(2);
    const soc = Math.min(100, 20 + (Date.now() - this.testStartTime) / 1000 / 60 * 2).toFixed(1);
    
    this.sendCall('MeterValues', {
      evseId: 1,
      meterValue: [{
        timestamp: new Date().toISOString(),
        sampledValue: [
          {
            value: this.meterValue,
            measurand: 'Energy.Active.Import.Register',
            unitOfMeasure: { unit: 'Wh' }
          },
          {
            value: parseFloat(currentPower),
            measurand: 'Power.Active.Import',
            unitOfMeasure: { unit: 'kW' }
          },
          {
            value: parseFloat(voltage),
            measurand: 'Voltage',
            unitOfMeasure: { unit: 'V' }
          },
          {
            value: parseFloat(current),
            measurand: 'Current.Import',
            unitOfMeasure: { unit: 'A' }
          },
          {
            value: parseFloat(soc),
            measurand: 'SoC',
            unitOfMeasure: { unit: 'Percent' }
          }
        ]
      }]
    });

    const elapsed = Math.floor((Date.now() - this.testStartTime) / 1000);
    console.log(`⚡ Charging... ${elapsed}s | Energy: ${this.meterValue}Wh | Power: ${currentPower}kW | SoC: ${soc}%`);
  }

  startHeartbeat() {
    this.heartbeatInterval = setInterval(() => {
      this.sendHeartbeat();
    }, 30000);
  }

  startMeterValues() {
    this.meterInterval = setInterval(() => {
      this.sendMeterValues();
    }, 10000);
  }

  stopMeterValues() {
    if (this.meterInterval) {
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

  async runAdvancedTest() {
    console.log('🧪 Starting OCPP 2.1 Advanced Test...\n');
    this.testStartTime = Date.now();

    try {
      console.log('1️⃣ Sending BootNotification...');
      this.sendBootNotification();
      await this.sleep(2000);

      console.log('2️⃣ Setting status to Available...');
      this.sendStatusNotification(1, 'Available');
      await this.sleep(1000);

      console.log('3️⃣ Authorizing user...');
      this.sendAuthorize('RFID-TEST-001');
      await this.sleep(2000);

      console.log('4️⃣ Sending TransactionEvent: Started...');
      this.sendTransactionEvent('Started', 'Authorized');
      this.isCharging = true;
      await this.sleep(2000);

      console.log('5️⃣ Setting status to Occupied...');
      this.sendStatusNotification(1, 'Occupied');
      await this.sleep(1000);

      console.log('6️⃣ Monitoring charging for 60 seconds...');
      console.log('📊 Real-time monitoring started:');
      
      this.startMeterValues();

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

      console.log('7️⃣ Sending TransactionEvent: Ended...');
      this.isCharging = false;
      this.stopMeterValues();
      this.sendTransactionEvent('Ended', 'Local');
      await this.sleep(2000);

      console.log('8️⃣ Setting status back to Available...');
      this.sendStatusNotification(1, 'Available');
      await this.sleep(1000);

      const totalTime = Math.floor((Date.now() - this.testStartTime) / 1000);
      
      console.log('\n📊 Test Summary:');
      console.log('================');
      console.log(`⏱️  Total test time: ${totalTime}s`);
      console.log(`🔋 Transaction ID: ${this.transactionId}`);
      console.log(`📈 Final meter reading: ${this.meterValue}Wh`);
      console.log('✅ OCPP 2.1 test completed successfully!');

    } catch (error) {
      console.error('❌ Test failed:', error.message);
    } finally {
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

async function main() {
  const chargePointId = process.argv[2] || 'CP-TEST-201';
  const serverUrl = process.argv[3] || 'ws://localhost:9001/ocpp';
  
  console.log('🚀 LaraCSMS OCPP 2.1 Advanced Tester');
  console.log('====================================');
  console.log(`📍 Charge Point: ${chargePointId}`);
  console.log(`🌐 Server: ${serverUrl}`);
  console.log('');
  
  const tester = new OCPP21Tester(chargePointId, serverUrl);

  try {
    await tester.connect();
    await tester.sleep(1000);
    await tester.runAdvancedTest();
  } catch (error) {
    console.error('❌ Test failed:', error.message);
    process.exit(1);
  }
}

process.on('SIGINT', () => {
  console.log('\n👋 Test interrupted by user');
  process.exit(0);
});

main().catch(console.error);
