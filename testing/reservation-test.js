const WebSocket = require('ws');

class ReservationTestClient {
  constructor(chargePointId, serverUrl = 'ws://cgs-csms.dharmap.com:7865') {
    this.chargePointId = chargePointId;
    this.serverUrl = `${serverUrl}/${chargePointId}`;
    this.messageId = 1;
    this.transactionId = null;
    this.meterValue = 1000;
  }

  connect() {
    return new Promise((resolve, reject) => {
      this.ws = new WebSocket(this.serverUrl);
      
      this.ws.on('open', () => {
        console.log(`✅ Connected to OCPP server as ${this.chargePointId}`);
        resolve();
      });

      this.ws.on('message', (data) => {
        try {
          const message = JSON.parse(data.toString());
          this.handleMessage(message);
        } catch (error) {
          console.error('❌ Message parsing error:', error);
        }
      });

      this.ws.on('error', (error) => {
        console.error('❌ WebSocket error:', error);
        reject(error);
      });

      this.ws.on('close', () => {
        console.log('🔌 Connection closed');
      });
    });
  }

  handleMessage(message) {
    const [messageType, messageId, action, payload] = message;
    
    if (messageType === 3) { // CALLRESULT
      console.log(`📨 Response for ${action || 'unknown'}:`, payload);
      
      if (payload && payload.transactionId) {
        this.transactionId = payload.transactionId;
        console.log(`🔋 Transaction ID: ${this.transactionId}`);
      }
    } else if (messageType === 4) { // CALLERROR
      console.log(`❌ Error response:`, payload);
    }
  }

  sendCall(action, payload) {
    const message = [2, this.messageId++, action, payload];
    console.log(`📤 Sending ${action}:`, payload);
    this.ws.send(JSON.stringify(message));
    return new Promise(resolve => setTimeout(resolve, 1000));
  }

  async sendBootNotification() {
    return this.sendCall('BootNotification', {
      chargePointVendor: 'Test Vendor',
      chargePointModel: 'Test Model'
    });
  }

  async sendStatusNotification(connectorId = 1, status = 'Available') {
    return this.sendCall('StatusNotification', {
      connectorId,
      status,
      errorCode: 'NoError'
    });
  }

  async sendStartTransaction(idTag, connectorId = 1) {
    this.meterValue = Math.floor(Math.random() * 1000) + 500;
    return this.sendCall('StartTransaction', {
      connectorId,
      idTag,
      meterStart: this.meterValue,
      timestamp: new Date().toISOString()
    });
  }

  async testWithReservation(reservedIdTag) {
    try {
      console.log('🚀 Starting reservation validation test...\n');

      // 1. Boot notification
      console.log('1️⃣ Sending BootNotification...');
      await this.sendBootNotification();
      await this.sleep(1000);

      // 2. Status notification - Available
      console.log('2️⃣ Setting connector to Available...');
      await this.sendStatusNotification(1, 'Available');
      await this.sleep(1000);

      // 3. Try with wrong ID tag (should be rejected)
      console.log('3️⃣ Testing with WRONG ID tag (should be rejected)...');
      await this.sendStartTransaction('WRONG_TAG_123', 1);
      await this.sleep(2000);

      // 4. Try with correct reserved ID tag (should be accepted)
      console.log('4️⃣ Testing with CORRECT reserved ID tag (should be accepted)...');
      await this.sendStartTransaction(reservedIdTag, 1);
      await this.sleep(2000);

      console.log('✅ Test completed!');
      
    } catch (error) {
      console.error('❌ Test failed:', error);
    } finally {
      this.ws.close();
    }
  }

  async testWithoutReservation() {
    try {
      console.log('🚀 Starting test without reservation...\n');

      // 1. Boot notification
      console.log('1️⃣ Sending BootNotification...');
      await this.sendBootNotification();
      await this.sleep(1000);

      // 2. Status notification - Available
      console.log('2️⃣ Setting connector to Available...');
      await this.sendStatusNotification(1, 'Available');
      await this.sleep(1000);

      // 3. Try charging on available connector (should work)
      console.log('3️⃣ Testing charging on available connector...');
      await this.sendStartTransaction('ANY_TAG_123', 1);
      await this.sleep(2000);

      console.log('✅ Test completed!');
      
    } catch (error) {
      console.error('❌ Test failed:', error);
    } finally {
      this.ws.close();
    }
  }

  sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

// Usage
const args = process.argv.slice(2);
const stationId = args[0] || 'TEST_STATION_001';
const testType = args[1] || 'without-reservation'; // 'with-reservation' or 'without-reservation'
const reservedIdTag = args[2] || 'RESERVED_TAG_001';

console.log(`🧪 Testing station: ${stationId}`);
console.log(`📋 Test type: ${testType}`);

const client = new ReservationTestClient(stationId);

client.connect().then(() => {
  if (testType === 'with-reservation') {
    console.log(`🔒 Testing with reserved ID tag: ${reservedIdTag}`);
    client.testWithReservation(reservedIdTag);
  } else {
    console.log('🔓 Testing without reservation');
    client.testWithoutReservation();
  }
}).catch(console.error);
