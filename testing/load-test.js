const WebSocket = require('ws');
const { v4: uuidv4 } = require('uuid');

class LoadTester {
  constructor(stationCount = 5, serverUrl = 'ws://localhost:8080') {
    this.stationCount = stationCount;
    this.serverUrl = serverUrl;
    this.stations = [];
    this.results = {
      connected: 0,
      transactions: 0,
      messages: 0,
      errors: 0
    };
  }

  async runLoadTest() {
    console.log(`🚀 Starting Load Test with ${this.stationCount} stations...\n`);
    
    // Create multiple charging stations
    for (let i = 1; i <= this.stationCount; i++) {
      const stationId = `LOAD_TEST_${i.toString().padStart(2, '0')}`;
      const station = new LoadTestStation(stationId, this.serverUrl, this);
      this.stations.push(station);
    }

    // Connect all stations
    console.log('🔌 Connecting all stations...');
    const connectionPromises = this.stations.map(station => station.connect());
    
    try {
      await Promise.all(connectionPromises);
      console.log(`✅ All ${this.stationCount} stations connected\n`);
      
      // Start load test
      await this.executeLoadTest();
      
    } catch (error) {
      console.error('❌ Load test failed:', error.message);
    }
  }

  async executeLoadTest() {
    console.log('📊 Starting load test sequence...\n');
    
    // Phase 1: Boot all stations
    console.log('Phase 1: Boot notifications...');
    await this.executePhase(station => station.sendBootNotification(), 1000);
    
    // Phase 2: Start transactions on all stations
    console.log('Phase 2: Starting transactions...');
    await this.executePhase(station => station.startTransaction(), 2000);
    
    // Phase 3: Monitor for 30 seconds
    console.log('Phase 3: Monitoring (30s)...');
    await this.monitorPhase(30);
    
    // Phase 4: Stop all transactions
    console.log('Phase 4: Stopping transactions...');
    await this.executePhase(station => station.stopTransaction(), 1000);
    
    // Phase 5: Disconnect test
    console.log('Phase 5: Disconnect test...');
    await this.disconnectTest();
    
    this.printResults();
  }

  async executePhase(action, delay) {
    const promises = this.stations.map(async (station, index) => {
      await this.sleep(index * 200); // Stagger by 200ms
      return action(station);
    });
    
    await Promise.all(promises);
    await this.sleep(delay);
  }

  async monitorPhase(seconds) {
    for (let i = seconds; i > 0; i--) {
      process.stdout.write(`\r⏱️  Monitoring: ${i}s remaining | Messages: ${this.results.messages} | Errors: ${this.results.errors}`);
      await this.sleep(1000);
    }
    console.log('');
  }

  async disconnectTest() {
    // Disconnect half the stations randomly
    const toDisconnect = Math.floor(this.stationCount / 2);
    const shuffled = [...this.stations].sort(() => 0.5 - Math.random());
    
    console.log(`🔌 Disconnecting ${toDisconnect} stations randomly...`);
    
    for (let i = 0; i < toDisconnect; i++) {
      shuffled[i].disconnect();
      await this.sleep(500);
    }
    
    await this.sleep(2000);
    
    // Reconnect them
    console.log(`🔌 Reconnecting ${toDisconnect} stations...`);
    for (let i = 0; i < toDisconnect; i++) {
      await shuffled[i].reconnect();
      await this.sleep(300);
    }
  }

  printResults() {
    console.log('\n📊 Load Test Results:');
    console.log('====================');
    console.log(`🔌 Stations: ${this.stationCount}`);
    console.log(`✅ Connected: ${this.results.connected}`);
    console.log(`🔋 Transactions: ${this.results.transactions}`);
    console.log(`📨 Messages sent: ${this.results.messages}`);
    console.log(`❌ Errors: ${this.results.errors}`);
    console.log(`📈 Success rate: ${((this.results.messages - this.results.errors) / this.results.messages * 100).toFixed(1)}%`);
    
    setTimeout(() => {
      console.log('\n👋 Load test completed');
      process.exit(0);
    }, 2000);
  }

  sleep(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

class LoadTestStation {
  constructor(stationId, serverUrl, loadTester) {
    this.stationId = stationId;
    this.serverUrl = `${serverUrl}/${stationId}`;
    this.loadTester = loadTester;
    this.ws = null;
    this.transactionId = null;
    this.meterValue = Math.floor(Math.random() * 1000) + 500;
    this.isConnected = false;
  }

  connect() {
    return new Promise((resolve, reject) => {
      this.ws = new WebSocket(this.serverUrl);

      this.ws.on('open', () => {
        this.isConnected = true;
        this.loadTester.results.connected++;
        resolve();
      });

      this.ws.on('message', (data) => {
        try {
          const message = JSON.parse(data.toString());
          this.handleMessage(message);
        } catch (error) {
          this.loadTester.results.errors++;
        }
      });

      this.ws.on('error', (error) => {
        this.loadTester.results.errors++;
        reject(error);
      });

      this.ws.on('close', () => {
        this.isConnected = false;
        if (this.loadTester.results.connected > 0) {
          this.loadTester.results.connected--;
        }
      });
    });
  }

  async reconnect() {
    if (!this.isConnected) {
      await this.connect();
    }
  }

  handleMessage(message) {
    const [messageType, messageId, actionOrPayload, payload] = message;
    
    if (messageType === 3 && actionOrPayload.transactionId) {
      this.transactionId = actionOrPayload.transactionId;
      this.loadTester.results.transactions++;
    }
  }

  sendMessage(action, payload) {
    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
      const messageId = uuidv4();
      const message = [2, messageId, action, payload];
      this.ws.send(JSON.stringify(message));
      this.loadTester.results.messages++;
      return messageId;
    }
    this.loadTester.results.errors++;
    return null;
  }

  sendBootNotification() {
    return this.sendMessage('BootNotification', {
      chargePointVendor: 'Load Tester',
      chargePointModel: 'LT-1000',
      chargePointSerialNumber: `SN-${this.stationId}`,
      firmwareVersion: '1.0.0'
    });
  }

  startTransaction() {
    this.sendMessage('StatusNotification', {
      connectorId: 1,
      status: 'Preparing',
      errorCode: 'NoError',
      timestamp: new Date().toISOString()
    });

    setTimeout(() => {
      this.sendMessage('StartTransaction', {
        connectorId: 1,
        idTag: `TAG_${this.stationId}`,
        meterStart: this.meterValue,
        timestamp: new Date().toISOString()
      });

      this.sendMessage('StatusNotification', {
        connectorId: 1,
        status: 'Charging',
        errorCode: 'NoError',
        timestamp: new Date().toISOString()
      });

      // Send meter values periodically
      this.startMeterValues();
    }, 1000);
  }

  startMeterValues() {
    this.meterInterval = setInterval(() => {
      if (this.isConnected && this.transactionId) {
        this.meterValue += Math.floor(Math.random() * 10) + 5;
        
        this.sendMessage('MeterValues', {
          connectorId: 1,
          transactionId: this.transactionId,
          meterValue: [{
            timestamp: new Date().toISOString(),
            sampledValue: [{
              value: this.meterValue.toString(),
              measurand: 'Energy.Active.Import.Register',
              unit: 'Wh'
            }]
          }]
        });
      }
    }, 15000); // Every 15 seconds
  }

  stopTransaction() {
    if (this.meterInterval) {
      clearInterval(this.meterInterval);
      this.meterInterval = null;
    }

    if (this.transactionId) {
      this.sendMessage('StopTransaction', {
        transactionId: this.transactionId,
        meterStop: this.meterValue,
        timestamp: new Date().toISOString(),
        reason: 'Local'
      });

      this.sendMessage('StatusNotification', {
        connectorId: 1,
        status: 'Available',
        errorCode: 'NoError',
        timestamp: new Date().toISOString()
      });
    }
  }

  disconnect() {
    if (this.meterInterval) {
      clearInterval(this.meterInterval);
    }
    if (this.ws) {
      this.ws.close();
    }
  }
}

// CLI Usage
async function main() {
  const stationCount = parseInt(process.argv[2]) || 5;
  const loadTester = new LoadTester(stationCount);
  
  console.log(`🧪 OCPP Load Testing Tool`);
  console.log(`📊 Testing with ${stationCount} charging stations\n`);
  
  await loadTester.runLoadTest();
}

process.on('SIGINT', () => {
  console.log('\n👋 Load test interrupted');
  process.exit(0);
});

main().catch(console.error);