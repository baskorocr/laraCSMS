const WebSocket = require('ws');

const ws = new WebSocket('ws://cgs-csms.dharmap.com:7865/ADVANCED_TEST_01');

ws.on('open', () => {
  console.log('✅ Connected');
  
  // Test DataTransfer message
  setTimeout(() => {
    console.log('📤 Testing DataTransfer message');
    const message = [2, "dt001", "DataTransfer", {
      vendorId: "TestVendor",
      messageId: "TestMessage",
      data: "Test data payload"
    }];
    ws.send(JSON.stringify(message));
  }, 1000);
  
  setTimeout(() => {
    ws.close();
  }, 3000);
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
