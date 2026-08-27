<div align="center">

# 🚕 PavanCab — Goa Taxi Booking Platform

**A complete ride-hailing & taxi-booking operating system for Goa, India.**

3 native Android apps (Passenger · Dispatch · Driver) + a PHP/MySQL backend —
one shared system for bookings, driver offers, WhatsApp OTP auth, Razorpay payments, and FCM push notifications.

![Android](https://img.shields.io/badge/Android-Kotlin-brightgreen?logo=android&logoColor=white)
![Backend](https://img.shields.io/badge/Backend-PHP%208.2-blue?logo=php&logoColor=white)
![Database](https://img.shields.io/badge/Database-MySQL-orange?logo=mysql&logoColor=white)
![Payments](https://img.shields.io/badge/Payments-Razorpay-blueviolet)
![Auth](https://img.shields.io/badge/Auth-WhatsApp%20OTP-25D366?logo=whatsapp&logoColor=white)

</div>

---

## What is PavanCab?

**PavanCab** is a production-ready **taxi booking app** / **cab management system** built for the travel and tourism market in **Goa, India**. It covers the entire ride lifecycle — from a passenger booking a cab and receiving **WhatsApp OTP** login, to **drivers bidding on rides**, negotiating **fares**, and getting paid through **Razorpay** subscriptions and wallets, all coordinated by a central **dispatch team**.

This is the **full open-source architecture**: clean, readable, and structured so that students, freelancers, and startup developers can study, run, and extend a real-world fleet-operator system.

### 📦 Download the apps (ready-to-install builds)

Pre-built, signed **APK + AAB** for all three apps are in the [`releases/`](releases/) folder:

| App | APK (install) | AAB (Play Store) |
|-----|---------------|------------------|
| **Passenger** | `PavanCab-Passenger-release.apk` | `PavanCab-Passenger-release.aab` |
| **Dispatch** | `PavanCab-Dispatch-release.apk` | `PavanCab-Dispatch-release.aab` |
| **Driver** | `PavanCab-Driver-release.apk` | `PavanCab-Driver-release.aab` |

> Install the `.apk` directly on any Android device (enable "Install unknown apps"). The `.aab` is for uploading to the **Google Play Console**.

> 🔒 **Security note:** All changeable credentials (WhatsApp, Razorpay, database, admin) live in a single `.env` file — **no secrets hardcoded in the code**. See [Configuration](#configuration).

---

## 📑 Table of Contents

- [Key Features](#-key-features)
- [Repository Structure](#-repository-structure)
- [Tech Stack](#-tech-stack)
- [Getting Started](#-getting-started)
- [Configuration](#-configuration)
- [API Overview](#-api-overview)
- [Screenshots](#-screenshots)
- [Live Site](#-live-site)
- [Roadmap](#-roadmap)
- [License](#-license)

---

## ✨ Key Features

### 📱 Passenger App — taxi booking for customers
`android-app/` · package `com.pavancab.niranjan`
- Login with **WhatsApp OTP** (Meta WhatsApp Business Cloud API).
- Book local & outstation cabs — car type, pickup/drop, date & time.
- **Reverse-bidding:** receive multiple **driver fare offers** and accept the best one.
- Live ride status, driver details, fare negotiation, and confirmation.
- Rating & review, **emergency alert** with live location, and safety ride reports.
- **FCM push notifications** for every ride update.

### 📟 Dispatch App — fleet & operations tower
`dispatch-app/` · package `com.pavancab.dispatch`
- Create & manage bookings (app, phone, manual sources).
- **Assign / reassign drivers**, freeze or cancel rides.
- Approve drivers; manage **commission** (paid / pending / waived).
- Team management with **role-based permissions**, live driver view, ride reports.
- Push notifications for new rides & alerts.

### 🚖 Driver App — earn with your cab
`driver-app/` · package `com.pavancab.driver`
- WhatsApp OTP login; manage profile, vehicle & number plate.
- See **new ride opportunities** and **offer your own fare**.
- Trip lifecycle: accept → on-trip → complete.
- **Pay subscription / commission online with Razorpay** and manage a **driver wallet**.
- Online/offline toggle, subscription status & renewals, ratings.

### 🌐 Web Backend & Admin Dashboard
`app/`
- PHP **8.2** + MySQL REST-style API powering all 3 apps.
- Web **admin dashboard** for full operational control.
- WhatsApp OTP engine, **FCM v1 push**, auto-migrations on boot.
- **Razorpay** integration (test / live), driver wallet transactions.
- Cron jobs (subscription expiry, driver timeouts, reminders, hourly ops).
- Ships with the complete **MySQL schema**: [`app/database.sql`](app/database.sql).

---

## 🗂 Repository Structure

```
├── android-app/      # Passenger app (Kotlin)
├── dispatch-app/     # Dispatch / operations app (Kotlin)
├── driver-app/       # Driver app (Kotlin)
├── app/              # PHP + MySQL backend & web dashboard
│   ├── api/          #   JSON API (driver / dispatch / passenger)
│   ├── dashboard/    #   Web admin dashboard
│   ├── db.php        #   DB config, helpers, auto-migrations
│   ├── auth*.php     #   Auth / OTP / session logic
│   ├── cron.php      #   Scheduled jobs
│   └── database.sql  #   Full MySQL schema (structure only)
├── .env.example      # Credential template (safe)
└── README.md
```

---

## 🛠 Tech Stack

| Layer        | Technology |
|--------------|------------|
| **Mobile**   | Kotlin · Jetpack Compose / XML · Retrofit / OkHttp |
| **Push**     | Firebase Cloud Messaging (FCM v1) |
| **Auth / OTP** | Meta WhatsApp Business Cloud API |
| **Backend**  | PHP 8.2 · MySQL (MariaDB) · REST JSON API |
| **Payments** | Razorpay (test & live modes) |
| **Deploy**   | cPanel / public_html (FTP/SFTP) |

---

## 🚀 Getting Started

### 1. Run the Android apps
Each app is a standard Gradle project.

```bash
export JAVA_HOME=<your JDK 17 path>
export ANDROID_HOME=<your Android SDK path>

# Play-Store bundle (AAB):
./gradlew bundleRelease --offline
# Installable APK:
./gradlew assembleRelease --offline
```

Outputs: `app/build/outputs/bundle/release/` and `app/build/outputs/apk/release/`.

> **Signing:** passwords live in each app's local `app/keystore.properties` (git-ignored). Never commit `.jks` keystores to a public repo.

### 2. Run the backend
1. `cp .env.example .env` and fill in real values.
2. Upload the `app/` folder to your web root (`public_html/app/`).
3. Import `app/database.sql`. Tables also **auto-migrate** on first boot.

---

## 🔐 Configuration

All credentials are read from **`.env`** at runtime — never from code:

```
# Database
DB_HOST=...        DB_USER=...        DB_PASS=...        DB_NAME=...

# WhatsApp (Meta) OTP
DEFAULT_META_WA_TOKEN=...        DEFAULT_META_WA_PHONE_ID=...

# Admin / support
SUPER_ADMIN_EMAIL=...            SUPER_ADMIN_PHONE=...

# Firebase / FCM
FCM_VAPID_KEY=...

# Razorpay
RAZORPAY_MODE=test               # test | live
RAZORPAY_KEY_ID=rzp_test_...
RAZORPAY_KEY_SECRET=...
RAZORPAY_PROD_KEY_ID=            # fill for live mode
RAZORPAY_PROD_KEY_SECRET=
```

`.env` is **git-ignored** and never uploaded. Push uses per-app `google-services.json` (final, unchanged).

---

## 🔌 API Overview

All three apps share the backend REST API:

| App      | Endpoint |
|----------|----------|
| Passenger | `app/api/passenger/index.php` |
| Dispatch  | `app/api/dispatch/index.php` |
| Driver    | `app/api/driver/index.php` |

---

## 📸 Screenshots

> _Coming soon — add your app screenshots here to showcase the UI._

---

## 🌍 Live Site

- **Website / Admin Dashboard:** https://pavancab.com/
- **API base:** `https://pavancab.com/app/`

---

## 🗺 Roadmap

- [x] Passenger · Dispatch · Driver apps
- [x] WhatsApp OTP authentication
- [x] Driver reverse-bidding & fare offers
- [x] Razorpay subscriptions, commissions & wallet
- [x] FCM push notifications
- [ ] iOS apps
- [ ] Multi-language (incl. Konkani / Hindi)
- [ ] Analytics dashboard & driver reports

---

## 📄 License

This repository is published for **demonstration and learning** purposes by the PavanCab team.
**All rights reserved** unless otherwise licensed. Contact the maintainers for commercial use or collaboration.

---

<div align="center">

**PavanCab — Goa's own taxi booking platform. Ride local. Ride safe.**

🔗 [pavancab.com](https://pavancab.com)

</div>
