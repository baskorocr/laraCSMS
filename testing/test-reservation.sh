#!/bin/bash

# Reservation Testing Script
# Usage: ./test-reservation.sh [STATION_ID] [ID_TAG]

STATION_ID=${1:-"TEST_STATION_001"}
ID_TAG=${2:-"RESERVED_TAG_001"}
API_URL="https://cgs-csms.dharmap.com/api"

echo "🧪 CIMORINGS Reservation Testing"
echo "================================"
echo "Station ID: $STATION_ID"
echo "ID Tag: $ID_TAG"
echo ""

# Function to make API calls
make_api_call() {
    local method=$1
    local endpoint=$2
    local data=$3
    local token=$4
    
    if [ -n "$data" ]; then
        curl -s -X $method \
             -H "Content-Type: application/json" \
             -H "Authorization: Bearer $token" \
             -d "$data" \
             "$API_URL$endpoint"
    else
        curl -s -X $method \
             -H "Authorization: Bearer $token" \
             "$API_URL$endpoint"
    fi
}

# Step 1: Login to get token
echo "1️⃣ Logging in..."
LOGIN_RESPONSE=$(curl -s -X POST \
    -H "Content-Type: application/json" \
    -d '{"username":"admin","password":"admin123"}' \
    "$API_URL/auth/login")

TOKEN=$(echo $LOGIN_RESPONSE | grep -o '"token":"[^"]*' | cut -d'"' -f4)

if [ -z "$TOKEN" ]; then
    echo "❌ Login failed!"
    echo "Response: $LOGIN_RESPONSE"
    exit 1
fi

echo "✅ Login successful"

# Step 2: Get stations
echo ""
echo "2️⃣ Getting stations..."
STATIONS_RESPONSE=$(make_api_call "GET" "/stations" "" "$TOKEN")
echo "Stations: $STATIONS_RESPONSE"

# Step 3: Create reservation
echo ""
echo "3️⃣ Creating reservation..."
RESERVATION_DATA="{
    \"charging_station_id\": 1,
    \"connector_id\": 1,
    \"id_tag\": \"$ID_TAG\",
    \"phone_number\": \"+6281234567890\",
    \"expiry_minutes\": 60
}"

RESERVATION_RESPONSE=$(make_api_call "POST" "/reservations" "$RESERVATION_DATA" "$TOKEN")
echo "Reservation created: $RESERVATION_RESPONSE"

# Step 4: Test OCPP with wrong ID tag
echo ""
echo "4️⃣ Testing OCPP with WRONG ID tag..."
cd /www/wwwroot/cgs-csms.dharmap.com/CIMORINGS/testing
node reservation-test.js "$STATION_ID" "with-reservation" "WRONG_TAG_123" &
WRONG_PID=$!
sleep 8
kill $WRONG_PID 2>/dev/null

echo ""
echo "5️⃣ Testing OCPP with CORRECT reserved ID tag..."
node reservation-test.js "$STATION_ID" "with-reservation" "$ID_TAG" &
CORRECT_PID=$!
sleep 8
kill $CORRECT_PID 2>/dev/null

echo ""
echo "6️⃣ Checking reservations status..."
RESERVATIONS_STATUS=$(make_api_call "GET" "/reservations" "" "$TOKEN")
echo "Current reservations: $RESERVATIONS_STATUS"

echo ""
echo "✅ Test completed!"
echo ""
echo "📋 Summary:"
echo "- Created reservation for ID tag: $ID_TAG"
echo "- Tested with wrong ID tag (should be rejected)"
echo "- Tested with correct ID tag (should be accepted)"
echo "- Check the OCPP server logs for detailed validation results"
