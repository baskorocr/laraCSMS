const WebSocket = require('ws');
const { v4: uuidv4 } = require('uuid');

class OCPPChargePointSimulator {
  constructor(chargePointId, serverUrl = 'ws://localhost:8080') {
    this.chargePointId = chargePointId;
    this.serverUrl = `${serverUrl}/${chargePointId}`;
    this.ws = null;
    this.messageQueue = [];
    this.transactionId = null;
    this.connectorStatus = 'Available';
  }

  connect() {
    console.log(`Connecting to ${this.serverUrl}`);
    this.ws = new WebSocket(this.serverUrl);

    this.ws.on('open', () => {
      console.log(`✅ Connected as ${this.chargePointId}`);
      this.sendBootNotification();
      this.startHeartbeat();
    });

    this.ws.on('message', (data) => {
      const message = JSON.parse(data.toString());
      this.handleMessage(message);
    });

    this.ws.on('close', () => {
      console.log(`❌ Disconnected from server`);
    });

    this.ws.on('error', (error) => {
      console.error('WebSocket error:', error.message);
    });
  }

  handleMessage(message) {
    const [messageType, messageId, action, payload] = message;
    
    console.log(`📨 Received: ${action}`, payload);

    if (messageType === 2) { // Call
      this.handleCall(messageId, action, payload);
    } else if (messageType === 3) { // CallResult
      console.log(`✅ CallResult for ${messageId}:`, payload);
    }
  }

  handleCall(messageId, action, payload) {
    let response = {};

    switch (action) {
      case 'RemoteStartTransaction':
        response = { status: 'Accepted' };
        setTimeout(() => this.simulateStartTransaction(payload.idTag, payload.connectorId), 1000);
        break;
      
      case 'RemoteStopTransaction':
        response = { status: 'Accepted' };
        setTimeout(() => this.simulateStopTransaction(), 1000);
        break;
      
      case 'GetConfiguration':
        response = {
          configurationKey: [
            { key: 'HeartbeatInterval', readonly: false, value: '300' },
            { key: 'MeterValueSampleInterval', readonly: false, value: '60' }
          ]
        };
        break;
      
      default:
        response = { status: 'Accepted' };
    }

    this.sendCallResult(messageId, response);
  }

  sendMessage(message) {
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      this.ws.send(JSON.stringify(message));
      console.log(`📤 Sent:`, message[2], message[3]);
    }
  }

  sendCall(action, payload) {
    const messageId = uuidv4();
    this.sendMessage([2, messageId, action, payload]);
    return messageId;
  }

  sendCallResult(messageId, payload) {
    this.sendMessage([3, messageId, payload]);
  }

  sendBootNotification() {
    this.sendCall('BootNotification', {
      chargePointVendor: 'Test Vendor',
      chargePointModel: 'Test Model CP-1',
      chargePointSerialNumber: `SN-${this.chargePointId}`,
      firmwareVersion: '1.0.0'
    });
  }

  sendHeartbeat() {
    this.sendCall('Heartbeat', {});
  }

  sendStatusNotification(connectorId = 1, status = 'Available') {
    this.connectorStatus = status;
    this.sendCall('StatusNotification', {
      connectorId,
      status,
      errorCode: 'NoError',
      timestamp: new Date().toISOString()
    });
  }

  sendAuthorize(idTag) {
    this.sendCall('Authorize', { idTag });
  }

  simulateStartTransaction(idTag = 'TEST_TAG_001', connectorId = 1) {
    console.log(`🔌 Starting transaction for ${idTag}`);
    
    this.sendStatusNotification(connectorId, 'Preparing');
    
    setTimeout(() => {
      this.sendCall('StartTransaction', {
        connectorId,
        idTag,
        meterStart: Math.floor(Math.random() * 1000),
        timestamp: new Date().toISOString()
      });
      
      this.sendStatusNotification(connectorId, 'Charging');
      this.startMeterValues(connectorId);
    }, 2000);
  }

  simulateStopTransaction() {
    if (!this.transactionId) {
      console.log('❌ No active transaction to stop');
      return;
    }

    console.log(`🛑 Stopping transaction ${this.transactionId}`);
    
    this.sendCall('StopTransaction', {
      transactionId: this.transactionId,
      meterStop: Math.floor(Math.random() * 2000) + 1000,
      timestamp: new Date().toISOString(),
      reason: 'Local'
    });

    this.sendStatusNotification(1, 'Available');
    this.transactionId = null;
  }

  startMeterValues(connectorId) {
    const interval = setInterval(() => {
      if (this.connectorStatus !== 'Charging') {
        clearInterval(interval);
        return;
      }

      this.sendCall('MeterValues', {
        connectorId,
        transactionId: this.transactionId,
        meterValue: [{
          timestamp: new Date().toISOString(),
          sampledValue: [{
            value: (Math.random() * 50 + 10).toFixed(2),
            measurand: 'Energy.Active.Import.Register',
            unit: 'kWh'
          }]
        }]
      });
    }, 30000); // Send every 30 seconds
  }

  startHeartbeat() {
    setInterval(() => {
      this.sendHeartbeat();
    }, 300000); // Every 5 minutes
  }

  // Manual testing methods
  testSequence() {
    console.log('\n🧪 Starting test sequence...\n');
    
    setTimeout(() => {
      console.log('1️⃣ Testing Authorization...');
      this.sendAuthorize('TEST_TAG_001');
    }, 2000);

    setTimeout(() => {
      console.log('2️⃣ Starting transaction...');
      this.simulateStartTransaction('TEST_TAG_001');
    }, 4000);

    setTimeout(() => {
      console.log('3️⃣ Stopping transaction...');
      this.simulateStopTransaction();
    }, 15000);
  }
}

// CLI Usage
const chargePointId = process.argv[2] || 'TEST_STATION_01';
const simulator = new OCPPChargePointSimulator(chargePointId);

simulator.connect();

// Start test sequence after connection
setTimeout(() => {
  simulator.testSequence();
}, 3000);

// Keep process alive
process.on('SIGINT', () => {
  console.log('\n👋 Shutting down simulator...');
  process.exit(0);
});