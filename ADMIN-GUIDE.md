# PavanCab — Admin & Operations Guide (Redacted)

> **Internal document.** All real secrets are intentionally removed and live only in the `.env` file on the server. This guide explains *where* things are and *how* to change them — without exposing any live credentials.

---

## 1. The 3 Android Apps

| App | Package | Folder | Used by |
|-----|---------|--------|---------|
| **PavanCab (Passenger)** | `com.pavancab.niranjan` | `android-app/` | Customers booking taxis |
| **PAVANCAB Dispatch** | `com.pavancab.dispatch` | `dispatch-app/` | Office / ops team |
| **PAVANCAB Driver** | `com.pavancab.driver` | `driver-app/` | Taxi drivers |

All three talk to the **same backend**:

- API base URL: `https://pavancab.com/app/`
- Passenger API: `app/api/passenger/index.php`
- Dispatch API:   `app/api/dispatch/index.php`
- Driver API:     `app/api/driver/index.php`

---

## 2. Config — one place (`.env`)

All server-side credentials (WhatsApp, Razorpay, Database, Admin contact) live in **one file**:

```
SERVER PATH: public_html/.env
```

You do **not** edit PHP code to change a credential. Edit `.env` and save — the apps pick it up automatically.

Relevant keys (see `.env.example` for placeholders):

```
DB_HOST / DB_USER / DB_PASS / DB_NAME          # MySQL
DEFAULT_META_WA_TOKEN / DEFAULT_META_WA_PHONE_ID   # WhatsApp OTP
SUPER_ADMIN_EMAIL / SUPER_ADMIN_PHONE          # Admin / support
FCM_VAPID_KEY                                  # Firebase push
RAZORPAY_MODE / RAZORPAY_KEY_ID / RAZORPAY_KEY_SECRET        # test
RAZORPAY_PROD_KEY_ID / RAZORPAY_PROD_KEY_SECRET              # live
```

**How to switch payments to LIVE:**
1. Finish KYC on the Razorpay dashboard and get live keys.
2. In `.env`: set `RAZORPAY_MODE=live`.
3. Fill `RAZORPAY_PROD_KEY_ID` and `RAZORPAY_PROD_KEY_SECRET`.
4. Save. The driver app reads the active keys automatically from the server — no app change needed.

**Firebase / FCM:** the per-app `google-services.json` files are final and never change. The server-side FCM service account is managed from the admin dashboard.

---

## 3. Database

- Engine: **MySQL** (schema: [`app/database.sql`](app/database.sql), structure only).
- Connection settings come from `.env`.
- Most app tables are **auto-created / auto-migrated** on boot (`app/db.php`).

---

## 4. App Signing (Play Store updates)

Each app uses its own keystore; the passwords are in a git-ignored `keystore.properties`. **Keep these absolutely safe** — losing a keystore means the Play Store app can never be updated.

| App | Keystore (git-ignored) | Key alias |
|-----|------------------------|-----------|
| Passenger | `android-app/app/pavancab-passenger.jks` + `keystore.properties` | `passenger` |
| Dispatch | `dispatch-app/app/pavancab-dispatch.jks` + `keystore.properties` | `dispatch` |
| Driver | `driver-app/app/pavancab-driver.jks` + `keystore.properties` | `driver` |

> ⚠️ These `.jks` / `keystore.properties` files are **not** committed to the repository. Ask the project owner for them if you need to sign a release.

---

## 5. Building AAB + APK

Requirements: JDK 17 and the Android SDK.

```bash
# Play Store bundle (AAB):
gradlew.bat bundleRelease --offline
#   -> app/build/outputs/bundle/release/app-release.aab

# Installable APK:
gradlew.bat assembleRelease --offline
#   -> app/build/outputs/apk/release/app-release.apk
```

Run inside `android-app/`, `dispatch-app/`, or `driver-app/`. Always sign with the **same keystore** so the Play Store signature stays valid across updates.

---

## 6. Support

- Support WhatsApp: `wa.me/<SUPER_ADMIN_PHONE>` (see `.env`)
- Support email: `<SUPER_ADMIN_EMAIL>` (see `.env`)
