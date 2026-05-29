const WebSocket = require('ws');
const { v4: uuidv4 } = require('uuid');

class OCPPTester {
  constructor(chargePointId = 'TEST_CP_001', serverUrl = 'ws://localhost:8082') {
    this.chargePointId = chargePointId;
    this.serverUrl = `${serverUrl}/${chargePointId}`;
    this.ws = null;
    this.responses = new Map();
  }

  connect() {
    return new Promise((resolve, reject) => {
      console.log(`🔌 Connecting to ${this.serverUrl}`);
      
      this.ws = new WebSocket(this.serverUrl);

      this.ws.on('open', () => {
        console.log('✅ Connected to OCPP server');
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
      });
    });
  }

  handleMessage(message) {
    const [messageType, messageId, actionOrPayload, payload] = message;
    
    if (messageType === 3) { // CallResult
      console.log(`📨 Response for ${messageId}:`, actionOrPayload);
      this.responses.set(messageId, actionOrPayload);
    } else if (messageType === 4) { // CallError
      console.error(`❌ Error for ${messageId}:`, actionOrPayload, payload);
    } else if (messageType === 2) { // Call from server
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
    
    console.log(`📤 Sending ${action}:`, payload);
    this.sendMessage(message);
    
    return messageId;
  }

  sendCallResult(messageId, payload) {
    const message = [3, messageId, payload];
    this.sendMessage(message);
  }

  async waitForResponse(messageId, timeout = 5000) {
    return new Promise((resolve, reject) => {
      const checkResponse = () => {
        if (this.responses.has(messageId)) {
          const response = this.responses.get(messageId);
          this.responses.delete(messageId);
          resolve(response);
        } else {
          setTimeout(checkResponse, 100);
        }
      };

      setTimeout(() => {
        reject(new Error(`Timeout waiting for response to ${messageId}`));
      }, timeout);

      checkResponse();
    });
  }

  // Test individual OCPP messages
  async testBootNotification() {
    console.log('\n🥾 Testing BootNotification...');
    
    const messageId = this.sendCall('BootNotification', {
      chargePointVendor: 'Test Vendor',
      chargePointModel: 'Test Model',
      chargePointSerialNumber: 'TEST123',
      firmwareVersion: '1.0.0'
    });

    try {
      const response = await this.waitForResponse(messageId);
      console.log('✅ BootNotification response:', response);
      return response.status === 'Accepted';
    } catch (error) {
      console.error('❌ BootNotification failed:', error.message);
      return false;
    }
  }

  async testHeartbeat() {
    console.log('\n💓 Testing Heartbeat...');
    
    const messageId = this.sendCall('Heartbeat', {});

    try {
      const response = await this.waitForResponse(messageId);
      console.log('✅ Heartbeat response:', response);
      return !!response.currentTime;
    } catch (error) {
      console.error('❌ Heartbeat failed:', error.message);
      return false;
    }
  }

  async testStatusNotification() {
    console.log('\n📊 Testing StatusNotification...');
    
    const messageId = this.sendCall('StatusNotification', {
      connectorId: 1,
      status: 'Available',
      errorCode: 'NoError',
      timestamp: new Date().toISOString()
    });

    try {
      const response = await this.waitForResponse(messageId);
      console.log('✅ StatusNotification response:', response);
      return true;
    } catch (error) {
      console.error('❌ StatusNotification failed:', error.message);
      return false;
    }
  }

  async testAuthorize() {
    console.log('\n🔐 Testing Authorize...');
    
    const messageId = this.sendCall('Authorize', {
      idTag: 'TEST_TAG_001'
    });

    try {
      const response = await this.waitForResponse(messageId);
      console.log('✅ Authorize response:', response);
      return response.idTagInfo?.status === 'Accepted';
    } catch (error) {
      console.error('❌ Authorize failed:', error.message);
      return false;
    }
  }

  async testStartTransaction() {
    console.log('\n🔌 Testing StartTransaction...');
    
    const messageId = this.sendCall('StartTransaction', {
      connectorId: 1,
      idTag: 'TEST_TAG_001',
      meterStart: 1000,
      timestamp: new Date().toISOString()
    });

    try {
      const response = await this.waitForResponse(messageId);
      console.log('✅ StartTransaction response:', response);
      this.transactionId = response.transactionId;
      return !!response.transactionId;
    } catch (error) {
      console.error('❌ StartTransaction failed:', error.message);
      return false;
    }
  }

  async testStopTransaction() {
    console.log('\n🛑 Testing StopTransaction...');
    
    if (!this.transactionId) {
      console.log('⚠️ No transaction ID available, skipping...');
      return false;
    }

    const messageId = this.sendCall('StopTransaction', {
      transactionId: this.transactionId,
      meterStop: 1500,
      timestamp: new Date().toISOString(),
      reason: 'Local'
    });

    try {
      const response = await this.waitForResponse(messageId);
      console.log('✅ StopTransaction response:', response);
      return true;
    } catch (error) {
      console.error('❌ StopTransaction failed:', error.message);
      return false;
    }
  }

  async testMeterValues() {
    console.log('\n⚡ Testing MeterValues...');
    
    const messageId = this.sendCall('MeterValues', {
      connectorId: 1,
      transactionId: this.transactionId || 12345,
      meterValue: [{
        timestamp: new Date().toISOString(),
        sampledValue: [{
          value: '25.5',
          measurand: 'Energy.Active.Import.Register',
          unit: 'kWh'
        }]
      }]
    });

    try {
      const response = await this.waitForResponse(messageId);
      console.log('✅ MeterValues response:', response);
      return true;
    } catch (error) {
      console.error('❌ MeterValues failed:', error.message);
      return false;
    }
  }

  async runAllTests() {
    console.log('🧪 Starting OCPP Message Tests...\n');
    
    const tests = [
      { name: 'BootNotification', test: () => this.testBootNotification() },
      { name: 'Heartbeat', test: () => this.testHeartbeat() },
      { name: 'StatusNotification', test: () => this.testStatusNotification() },
      { name: 'Authorize', test: () => this.testAuthorize() },
      { name: 'StartTransaction', test: () => this.testStartTransaction() },
      { name: 'MeterValues', test: () => this.testMeterValues() },
      { name: 'StopTransaction', test: () => this.testStopTransaction() }
    ];

    const results = {};
    
    for (const { name, test } of tests) {
      try {
        results[name] = await test();
        await new Promise(resolve => setTimeout(resolve, 1000)); // Wait between tests
      } catch (error) {
        console.error(`❌ ${name} test error:`, error.message);
        results[name] = false;
      }
    }

    console.log('\n📊 OCPP Test Results:');
    console.log('====================');
    Object.entries(results).forEach(([test, passed]) => {
      console.log(`${passed ? '✅' : '❌'} ${test}: ${passed ? 'PASSED' : 'FAILED'}`);
    });

    const passedCount = Object.values(results).filter(Boolean).length;
    const totalCount = Object.keys(results).length;
    
    console.log(`\n🎯 Overall: ${passedCount}/${totalCount} OCPP tests passed`);
    
    return results;
  }

  disconnect() {
    if (this.ws) {
      this.ws.close();
    }
  }
}

// Run OCPP tests
async function main() {
  const chargePointId = process.argv[2] || 'TEST_CP_001';
  const tester = new OCPPTester(chargePointId);

  try {
    await tester.connect();
    await new Promise(resolve => setTimeout(resolve, 1000)); // Wait for connection to stabilize
    await tester.runAllTests();
  } catch (error) {
    console.error('❌ Test failed:', error.message);
  } finally {
    tester.disconnect();
    process.exit(0);
  }
}

main().catch(console.error);