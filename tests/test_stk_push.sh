echo "Testing M-Pesa STK Push Integration"
echo "===================================="
echo ""

# Set your variables
NGROK_URL="https://b3f4-102-34-12-22.ngrok-free.app"
API_KEY="mpesa_test_key_12345"
PHONE="254708374149"  # Sandbox test number
AMOUNT="10"

echo "1. Testing STK Push..."
echo "URL: $NGROK_URL/api/stk-push"
echo "Phone: $PHONE"
echo "Amount: $AMOUNT"
echo ""

RESPONSE=$(curl -s -X POST "$NGROK_URL/api/stk-push" \
  -H "Content-Type: application/json" \
  -H "X-API-KEY: $API_KEY" \
  -d "{
    \"phone\": \"$PHONE\",
    \"amount\": $AMOUNT,
    \"account_ref\": \"TEST001\",
    \"description\": \"Test Payment\"
  }")

echo "Response:"
echo "$RESPONSE" | jq '.'
echo ""

# Extract checkout request ID
CHECKOUT_ID=$(echo "$RESPONSE" | jq -r '.data.checkout_request_id // empty')

if [ ! -z "$CHECKOUT_ID" ]; then
    echo "✓ STK Push initiated successfully!"
    echo "Checkout Request ID: $CHECKOUT_ID"
    echo ""
    echo "Check your phone (254708374149) for M-Pesa prompt"
    echo ""
    echo "To check status, run:"
    echo "curl $NGROK_URL/api/stk-push/$CHECKOUT_ID/status"
else
    echo "✗ STK Push failed"
fi

### Make it executable:
chmod +x tests/test_stk_push.sh
