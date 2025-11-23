# API Configuration - Add to .env file

## WhatsApp API Configuration

Add these lines to your `.env` file:

```env
WHATSAPP_API_KEY=9a2f8d1b-45c7-4e89-bf0d-3e6a9c1e72a4
WHATSAPP_BASE_URL=https://connect.wadina.agency/webhooks
WHATSAPP_WEBHOOK_ID=  # Optional: if your API requires a webhook ID
WHATSAPP_TEST_MODE=false  # Set to true for testing without sending actual messages
```

## Infobip SMS API Configuration

Add these lines to your `.env` file:

```env
INFOBIP_API_KEY=495029784b87988c1a8aed7259740704-13b5ec69-243c-45ec-9e00-a71963c36650
INFOBIP_BASE_URL=https://xl4ln4.api.infobip.com/sms/2/text/advanced
INFOBIP_SENDER_ID=TaskManager  # Optional: sender name for SMS
```

## Complete Configuration Block

Add this entire block to your `.env` file:

```env
# WhatsApp API
WHATSAPP_API_KEY=9a2f8d1b-45c7-4e89-bf0d-3e6a9c1e72a4
WHATSAPP_BASE_URL=https://connect.wadina.agency/webhooks
WHATSAPP_WEBHOOK_ID=
WHATSAPP_TEST_MODE=false

# Infobip SMS API
INFOBIP_API_KEY=495029784b87988c1a8aed7259740704-13b5ec69-243c-45ec-9e00-a71963c36650
INFOBIP_BASE_URL=https://xl4ln4.api.infobip.com/sms/2/text/advanced
INFOBIP_SENDER_ID=TaskManager
```

## After Adding Configuration

1. **Clear config cache:**
   ```bash
   php artisan config:clear
   ```

2. **Test the endpoints:**
   - Use Postman to test OTP endpoints
   - Check if messages are being sent

## Notes

- The WhatsApp API URL will be constructed as: `{WHATSAPP_BASE_URL}/whatsapp` or `{WHATSAPP_BASE_URL}/whatsapp/{WHATSAPP_WEBHOOK_ID}` if webhook ID is provided
- The SMS API URL will use the `INFOBIP_BASE_URL` directly
- API keys will be automatically included in request headers

