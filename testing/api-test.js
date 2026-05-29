const axios = require('axios');

const API_BASE = 'http://localhost:3000/api';
let authToken = '';

class APITester {
  constructor() {
    this.axios = axios.create({
      baseURL: API_BASE,
      timeout: 10000
    });
  }

  async login(username = 'admin', password = 'admin123') {
    try {
      console.log('🔐 Testing login...');
      const response = await this.axios.post('/auth/login', {
        username,
        password
      });
      
      authToken = response.data.token;
      this.axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`;
      
      console.log('✅ Login successful');
      console.log('User:', response.data.user);
      return true;
    } catch (error) {
      console.error('❌ Login failed:', error.response?.data?.message);
      return false;
    }
  }

  async testStations() {
    try {
      console.log('\n📍 Testing stations API...');
      
      // Get all stations
      console.log('Getting all stations...');
      const stations = await this.axios.get('/stations');
      console.log(`✅ Found ${stations.data.length} stations`);
      
      // Create new station
      console.log('Creating new station...');
      const newStation = {
        charge_point_id: `TEST_STATION_${Date.now()}`,
        name: 'Test Station API',
        location: 'Test Location',
        connector_count: 2
      };
      
      const created = await this.axios.post('/stations', newStation);
      console.log('✅ Station created:', created.data);
      
      // Get updated stations list
      const updatedStations = await this.axios.get('/stations');
      console.log(`✅ Updated stations count: ${updatedStations.data.length}`);
      
      return true;
    } catch (error) {
      console.error('❌ Stations test failed:', error.response?.data?.message);
      return false;
    }
  }

  async testTransactions() {
    try {
      console.log('\n💳 Testing transactions API...');
      
      const transactions = await this.axios.get('/transactions');
      console.log(`✅ Found ${transactions.data.length} transactions`);
      
      if (transactions.data.length > 0) {
        console.log('Recent transaction:', transactions.data[0]);
      }
      
      return true;
    } catch (error) {
      console.error('❌ Transactions test failed:', error.response?.data?.message);
      return false;
    }
  }

  async testInvalidAuth() {
    try {
      console.log('\n🚫 Testing invalid authentication...');
      
      // Remove auth header
      delete this.axios.defaults.headers.common['Authorization'];
      
      await this.axios.get('/stations');
      console.log('❌ Should have failed but didn\'t');
      return false;
    } catch (error) {
      if (error.response?.status === 401) {
        console.log('✅ Correctly rejected unauthorized request');
        // Restore auth header
        this.axios.defaults.headers.common['Authorization'] = `Bearer ${authToken}`;
        return true;
      }
      console.error('❌ Unexpected error:', error.response?.data?.message);
      return false;
    }
  }

  async runAllTests() {
    console.log('🧪 Starting API Tests...\n');
    
    const results = {
      login: await this.login(),
      stations: false,
      transactions: false,
      invalidAuth: false
    };

    if (results.login) {
      results.stations = await this.testStations();
      results.transactions = await this.testTransactions();
      results.invalidAuth = await this.testInvalidAuth();
    }

    console.log('\n📊 Test Results:');
    console.log('================');
    Object.entries(results).forEach(([test, passed]) => {
      console.log(`${passed ? '✅' : '❌'} ${test}: ${passed ? 'PASSED' : 'FAILED'}`);
    });

    const passedCount = Object.values(results).filter(Boolean).length;
    const totalCount = Object.keys(results).length;
    
    console.log(`\n🎯 Overall: ${passedCount}/${totalCount} tests passed`);
    
    return results;
  }
}

// Health check first
async function healthCheck() {
  try {
    console.log('🏥 Checking server health...');
    const response = await axios.get(`${API_BASE.replace('/api', '')}/health`);
    console.log('✅ Server is healthy:', response.data);
    return true;
  } catch (error) {
    console.error('❌ Server health check failed. Make sure the backend is running.');
    return false;
  }
}

// Run tests
async function main() {
  const isHealthy = await healthCheck();
  
  if (!isHealthy) {
    console.log('\n💡 Start the backend server first:');
    console.log('cd backend && npm run dev');
    process.exit(1);
  }

  const tester = new APITester();
  await tester.runAllTests();
}

main().catch(console.error);