const WebSocket = require('ws');

const ws = new WebSocket('ws://cgs-csms.dharmap.com:7865/ADVANCED_TEST_01');

ws.on('open', () => {
  console.log('✅ Connected');
  
  // Test StartTransaction with WRONG ID tag
  setTimeout(() => {
    console.log('📤 Testing StartTransaction with WRONG ID tag: ADVANCED_TAG_001');
    const message = [2, "12345", "StartTransaction", {
      connectorId: 1,
      idTag: "ADVANCED_TAG_001",
      meterStart: 1000,
      timestamp: new Date().toISOString()
    }];
    ws.send(JSON.stringify(message));
  }, 1000);
  
  // Test StartTransaction with CORRECT reserved ID tag
  setTimeout(() => {
    console.log('📤 Testing StartTransaction with CORRECT ID tag: FINAL_TEST_TAG');
    const message = [2, "12346", "StartTransaction", {
      connectorId: 1,
      idTag: "FINAL_TEST_TAG",
      meterStart: 1000,
      timestamp: new Date().toISOString()
    }];
    ws.send(JSON.stringify(message));
  }, 3000);
  
  setTimeout(() => {
    ws.close();
  }, 5000);
});

ws.on('message', (data) => {
  const message = JSON.parse(data.toString());
  console.log('📨 Response:', message);
});

ws.on('error', (error) => {
  console.error('❌ Error:', error);
});

ws.on('close', () => {
  console.log('🔌 Connection closed');
});
