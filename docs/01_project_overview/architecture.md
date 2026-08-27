# 🏗️ System Architecture & Technology Stack

## 1. High-Level Architecture Overview

PAVANCAB is designed around a **"Behind-the-Scenes Dispatch Tower" (Wizard of Oz) model**:
- **From the Passenger's viewpoint**: The app delivers an automated, premium Uber/Ola-style cab booking and live radar tracking experience.
- **Behind the scenes (No Driver App)**: The **Super Admin and authorized Team Dispatchers** control all driver allocations, status progressions, and communication directly from the **Dispatch Command Tower** (`app/dashboard.php`). Drivers receive their dispatch orders directly via automated **Meta WhatsApp Messages** and direct phone calls.
- **Technology**: Pure PHP 8.x + MySQLi backend architecture with Mobile-First Glassmorphic Dark UI.

```
+-------------------------------------------------------------------------+
|                              CLIENT TIER                                |
|  - Mobile-First Webapp (HTML5, Tailwind CSS, Lucide Icons, Vanilla JS) |
|  - Progressive Real-Time Polling (3s Hash-Diff Live Feed)               |
|  - Firebase Cloud Messaging (FCM Web Push + Audio Alerts)               |
+------------------------------------+------------------------------------+
                                     |
                                     v
+------------------------------------+------------------------------------+
|                           APPLICATION TIER                              |
|  - app/index.php          : 4-Step Booking Wizard (One-Way, Hourly, Tour)|
|  - app/rides.php          : Passenger Ride Radar & Single Ride Spotlight|
|  - app/dashboard.php      : Dispatch Command Tower (Admin & Team)       |
|  - app/auth.php           : WhatsApp OTP Authentication Module          |
|  - app/api_dashboard.php  : Admin Actions (Assign, Status, Fares, Fleet)|
|  - app/api_rides.php      : Passenger Actions (Rate, Boost, Cancel)     |
|  - app/bookings.php       : Booking Creation & History API              |
|  - app/drops.php          : Dynamic Drop Locations API                  |
|  - app/hourly.php         : Hourly Rental Packages API                  |
|  - app/tours.php          : Sightseeing Tour Packages API               |
|  - app/db.php             : Core Database, Config & Notification Engine |
+------------------------------------+------------------------------------+
                                     |
                                     v
+------------------------------------+------------------------------------+
|                         EXTERNAL INTEGRATIONS                           |
|  - Meta WhatsApp Cloud API: Automated OTPs & Booking WhatsApp Alerts   |
|  - Firebase Cloud Messaging: Push Notifications to Dispatch & Riders    |
|  - MySQL Database         : Unified Relational Data Store              |
+-------------------------------------------------------------------------+
```

---

## 2. Directory Structure

```
Goa Taxi App/
├── app/                      # Web application source files
│   ├── .htaccess             # Apache rewrite rules & security headers
│   ├── admin.php             # Admin route redirector to dashboard.php
│   ├── api.php               # Comprehensive API router & webhook handler
│   ├── api_dashboard.php     # Dispatch tower backend API engine
│   ├── api_rides.php         # Passenger rides radar API engine
│   ├── auth.php              # WhatsApp OTP login & session management
│   ├── bookings.php          # Booking creation & history endpoint
│   ├── cabs/                 # Vehicle fleet PNG assets (sedan, ertiga, etc.)
│   ├── dashboard.php         # Dispatch Command Tower UI
│   ├── db.php                # Database singleton, query helpers & alerts
│   ├── drops.php             # Destination drops with live fare calculation
│   ├── firebase-messaging-sw.js # FCM Background Service Worker
│   ├── footer.php            # Global footer, Auth Modal, Report Modal & FCM
│   ├── header.php            # Global header, Navbar & JS session state
│   ├── hourly.php            # Hourly rental tiers & calculation API
│   ├── index.php             # Main booking portal with 4-step wizard
│   ├── logo-pavancab.png     # Official high-resolution logo
│   ├── pickups.php           # Dynamic pickup location options
│   ├── rides.php             # My Rides tracking & spotlight ride card
│   └── tours.php             # Goa sightseeing tour packages
├── deploy.ps1                # Automated WinSCP live deployment script
├── winscp_upload_live.txt    # WinSCP FTP sync instruction script
└── docs/                     # Project memory documentation
```

---

## 3. Session & Access Control Rules

1. **Visitors**: Can freely explore Step 1 (Route & Service Selection).
2. **Step 2, 3, 4 & Booking**: Requires authenticated WhatsApp login.
3. **Passengers**: Access to `index.php` and their own rides in `rides.php`.
4. **Dispatch Team & Super Admin**: Access to `dashboard.php` to assign drivers, modify fares, manage fleet, and resolve ride reports.
