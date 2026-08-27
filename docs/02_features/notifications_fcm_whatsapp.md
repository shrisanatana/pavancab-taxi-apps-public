# ðŸ”” Meta WhatsApp API & FCM Push Notifications

PAVANCAB features an automated dual-channel notification pipeline connecting Passengers, Drivers, and Dispatchers.

---

## 1. Meta WhatsApp Cloud API (`sendMetaWhatsApp`)

Integrated via `app/db.php`:
- **API Endpoint**: `https://graph.facebook.com/v18.0/{PHONE_NUMBER_ID}/messages`
- **Dynamic Credentials**: Token and Phone ID stored in `app_config` and managed live via Dispatch Settings.
- **Trigger Events**:
  1. **WhatsApp OTP Verification**: Dispatched when users log in.
  2. **Ride Confirmation**: Sent to Passenger with booking reference, route, fare, and date/time.
  3. **Dispatch Order**: Sent to Super Admin (+91 8199000000) and Team when a new ride is booked.
  4. **Driver Assignment**: Dispatched to Driver with passenger pickup details, and to Passenger with driver phone and vehicle plate.
  5. **Ride Cancellation & Fare Updates**: Broadcasted to affected parties.

---

## 2. Firebase Cloud Messaging (FCM HTTP v1 Web Push)

- **FCM Engine**: Standalone, zero-dependency pure PHP implementation in `app/fcm_v1.php`.
- **API Endpoint**: `https://fcm.googleapis.com/v1/projects/{PROJECT_ID}/messages:send`
- **Authentication**: Modern Google OAuth 2.0 Bearer Token generated via RSA-SHA256 JWT assertion with cached access token.
- **Service Account Credentials**: Loaded dynamically from `app_config` (`fcm_service_account_json`) or `app/firebase-service-account.json`.
- **Token Registration & User Linking**: Stored in `app_fcm_tokens` and synced automatically with passenger/admin phone numbers on login.
- **Diagnostic & Live Testing**: 
  - Admin Dispatch Tower includes 1-click **"FCM Push"** console to inspect engine health, active tokens, and trigger instant test pushes to current browser or all admins.
  - Live testing endpoint: `POST /admin/fcm-test`
- **Audio Chimes & Service Worker**:
  - Service Worker in `app/firebase-messaging-sw.js` catches background push events and shows native OS notifications with clickable app redirection.
  - In-app foreground audio chime (`showBrowserNotification()`) plays automatically upon new incoming alerts with animated floating toast banners.
