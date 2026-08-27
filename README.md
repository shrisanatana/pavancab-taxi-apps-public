# PavanCab — Goa Taxi Booking Platform

**PavanCab** is a complete, production-ready taxi-booking platform for Goa, India. It ships as **three native Android apps** (Passenger, Dispatch, and Driver) plus a **PHP + MySQL backend** with a web admin dashboard. Everything runs against one shared backend at `https://pavancab.com/`.

This is the full source code of the platform, structured for anyone (from students to studio developers) to read, run, and deploy. All changeable credentials are kept in a single `.env` file — no secrets are hardcoded in the code (see [Configuration](#configuration)).

---

## ✨ Feature Highlights

### 📱 Passenger App (`android-app/` — package `com.pavancab.niranjan`)
- OTP login delivered via **WhatsApp Business API** (number-based auth).
- Book a taxi (car type, pickup/drop, date-time, local & outstation).
- See **multiple driver offers** with proposed fares and accept the best one.
- Live ride status, driver details, fare proposals, and negotiated fares.
- Rate & review the driver after the trip.
- Emergency alert button that notifies the dispatch team with live location.
- Ride reports & safety incident reporting.
- Firebase push notifications for ride updates.

### 📟 Dispatch App (`dispatch-app/` — package `com.pavancab.dispatch`)
- Office / operations dashboard on mobile.
- Create and manage bookings (app, phone, and manual sources).
- **Assign / reassign drivers**, freeze or cancel rides.
- Approve new drivers, manage commission status (paid / pending / waived).
- Team management with role-based permissions.
- Live view of drivers, system settings, and ride reports.
- Push notifications for new rides and alerts.

### 🚖 Driver App (`driver-app/` — package `com.pavancab.driver`)
- OTP login via WhatsApp, view and edit profile, vehicle & number plate.
- See **new ride opportunities** and **offer your own fare** (reverse bidding).
- Accept / decline rides; trip lifecycle (accept → on-trip → complete).
- **Earn & pay**: pay subscription/commission online via **Razorpay**, and manage a **driver wallet**.
- Online/offline toggle, subscription status & reminders.
- Driver ratings and review history.

### 🌐 Web Backend & Dashboard (`app/`)
- PHP (8.2) + MySQL REST-style API used by all three apps.
- Web **admin dashboard** (`/app/`) for full operational control.
- WhatsApp OTP engine, **FCM push notifications** (Firebase), auto-migrations on boot.
- Razorpay payment integration (test / live modes), driver wallet transactions.
- Cron jobs (subscription expiry, driver online timeout, reminders, hourly ops).
- Ship includes the full **MySQL schema** in [`app/database.sql`](app/database.sql).

---

## 🗂 Repository Structure

```
├── android-app/              # Passenger Android app (Kotlin, Jetpack Compose / XML)
├── dispatch-app/             # Dispatch/office Android app
├── driver-app/               # Driver Android app
├── app/                      # PHP + MySQL backend & web admin dashboard
│   ├── api/                  #   JSON API endpoints (driver / dispatch / passenger)
│   ├── dashboard/            #   Web admin dashboard
│   ├── db.php                #   DB config, helpers, auto-migrations
│   ├── auth*.php             #   Auth / OTP / session logic
│   ├── cron.php / each-min-cron.php / hourly.php   #   Scheduled jobs
│   ├── database.sql          #   Complete MySQL schema (structure, no data)
│   └── .htaccess
├── .env.example              # Template for all credentials (safe to share)
└── README.md
```

---

## 🛠 Tech Stack

| Layer      | Technology |
|------------|------------|
| **Android** | Kotlin, Jetpack Compose / XML, Retrofit / OkHttp, Firebase (FCM) |
| **Backend** | PHP 8.2, MySQL (MariaDB), REST JSON API |
| **Payments** | Razorpay (test & live modes) |
| **Auth / OTP** | Meta WhatsApp Business Cloud API |
| **Push** | Firebase Cloud Messaging (FCM v1) |
| **Deploy** | cPanel / public_html via FTP/SFTP |

---

## 🚀 Getting Started

### 1. Android apps
Each app is a standard Gradle Android project.

```bash
# In android-app/, dispatch-app/, or driver-app/:
export JAVA_HOME=<your JDK 17 path>
export ANDROID_HOME=<your Android SDK path>

# Build a Play-Store bundle (AAB):
./gradlew bundleRelease --offline

# Build an installable APK:
./gradlew assembleRelease --offline
```

Outputs land in `app/build/outputs/bundle/release/` and `app/build/outputs/apk/release/`.

> Android signing passwords live in each app's local `app/keystore.properties` (git-ignored). Do **not** commit the `.jks` keystores or `keystore.properties` to a public repo — losing them means you can never update the app on the Play Store.

### 2. Backend
1. Copy `.env.example` to `.env` and fill in real values (database, WhatsApp, Razorpay).
2. Upload the `app/` folder into your web root (e.g. `public_html/app/`).
3. Import `app/database.sql` into your MySQL database.
4. Tables are **auto-created/migrated** on first run, so the app works even without a manual import.

### 3. Configuration
All changeable credentials are read from **`.env`** at runtime — the code never hardcodes them:

```
# Database
DB_HOST=...
DB_USER=...
DB_PASS=...
DB_NAME=...

# WhatsApp (Meta) OTP
DEFAULT_META_WA_TOKEN=...
DEFAULT_META_WA_PHONE_ID=...

# Admin / support
SUPER_ADMIN_EMAIL=...
SUPER_ADMIN_PHONE=...

# Firebase / FCM
FCM_VAPID_KEY=...

# Razorpay
RAZORPAY_MODE=test            # test | live
RAZORPAY_KEY_ID=rzp_test_...
RAZORPAY_KEY_SECRET=...
RAZORPAY_PROD_KEY_ID=          # fill for live mode
RAZORPAY_PROD_KEY_SECRET=
```

The `.env` file is **git-ignored** and never uploaded. Push notifications use per-app `google-services.json` files (final, unchanged).

---

## 🌍 Live Site
- **Website / admin dashboard:** https://pavancab.com/
- **API base:** `https://pavancab.com/app/`

---

## 📄 License & Support
This repository is **private** and intended for the PavanCab team and their developers.
For support, contact the admin via the details configured in `.env` (see `SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PHONE`).

---

*PavanCab — Goa's own taxi booking platform. Ride local. Ride safe.*
