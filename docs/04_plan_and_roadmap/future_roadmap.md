# ðŸ—ºï¸ Product Roadmap & Operating Architecture

## ðŸŽ¯ Core Operating Model: The "Dispatch Tower" Architecture (No Driver App)

> **Key Business Philosophy**:  
> To the **Passenger**, the app looks, feels, and operates like an automated Uber/Ola cab booking and live tracking platform.  
> **Behind the scenes**, the **Super Admin and authorized Team Dispatchers** manage and orchestrate all driver allocations, status progressions, and communications from the **Dispatch Command Tower** (`app/dashboard.php`). Drivers receive their dispatch orders and updates directly via automated **Meta WhatsApp Messages** and direct phone calls.

---

## ðŸ“‹ Comprehensive Feature Map (Current & Planned Enhancements)

### 1. ðŸš– Passenger Booking Engine (`app/index.php`)
- **Mode 1: One-Way Point-to-Point Transfers**: Dynamic pickup dropdown (`goaplaces`), destination dropdown with real-time distance and tier fares (`goafares`).
- **Mode 2: Hourly Cab Rentals**: 4 Hr (40 KM), 8 Hr (80 KM), 12 Hr (120 KM) packages (`goahourfares`).
- **Mode 3: Sightseeing Tours**: North Goa, South Goa, Dudhsagar Waterfalls full-day packages (`goatours`).
- **4-Step Intuitive Wizard**:
  1. *Route & Service* (Open to all visitors)
  2. *Schedule Time* (WhatsApp login required, Instant Now vs Schedule Later)
  3. *Choose Cab Tier* (Sedan, Ertiga, SUV, Crysta VIP with live fixed fare calculation)
  4. *Confirm & Book* (Auto-filled name/phone, flight notes, 1-click booking)

---

### 2. ðŸ” WhatsApp OTP Authentication & Role Engine (`app/auth.php`, `app/header.php`)
- **1-Click WhatsApp OTP Verification**: Zero passwords, automated 6-digit OTP code sent via Meta WhatsApp Cloud API.
- **Dynamic Role Resolution**:
  - `ðŸ‘‘ Super Admin`: Phone `8199000000` or Email `admin@pavancab-demo.local` âž” Direct access to Dispatch Tower.
  - `ðŸ›¡ï¸ Team Member`: Active entries in `app_team_members` âž” Direct access to Dispatch Tower.
  - `ðŸš– Passenger`: Standard riders âž” Access to Booking & My Rides.
- **Global Session State**: `window.currentUser` and `window.isUserLoggedIn` synchronized across all views.

---

### 3. ðŸ›¡ï¸ Dispatch Command Tower (`app/dashboard.php`, `app/api_dashboard.php`)
- **Live 3-Second Hash-Differential Polling**: Instant auto-refresh on new bookings, fare updates, or status changes.
- **Category Filter Tabs with Strict State Locking**:
  - `ðŸ”´ Needs Driver (PENDING)`
  - `Assigned (CONFIRMED)`
  - `On Trip (IN_TRANSIT)`
  - `Completed (COMPLETED)`
  - `Cancelled (CANCELLED)`
  - `All Rides`
- **âš¡ Assign Driver Modal**: Select from saved driver roster or input ad-hoc driver info; sends automated WhatsApp order to driver and dispatched alert to passenger.
- **ðŸš– Ride Lifecycle Controller**: Advance rides (`PENDING` âž” `CONFIRMED` âž” `IN_TRANSIT` âž” `COMPLETED` âž” `CANCELLED`).
- **ðŸ’° Fare Adjustments & Surge Boosts**: Adjust fare or add boost amounts with audit notes and automated notifications.
- **ðŸ‘¥ Fleet & Team Management**: Add drivers, toggle availability, track completed trips, invite team dispatchers.
- **ðŸ–¨ï¸ Trip Receipt Slips**: 1-click printable receipt for riders.

---

### 4. ðŸ•’ Passenger Live Rides Radar & Spotlight View (`app/rides.php`, `app/api_rides.php`)
- **Direct Single-Ride Spotlight Page (`?id={BOOKING_ID}`)**: Confirmed bookings land immediately on their dedicated spotlight card with smooth auto-scroll.
- **Ola/Uber 3-Step Live Status Stepper**: Visual progression (`1. Booked` âž” `2. Driver Dispatched` âž” `3. On Trip`).
- **Assigned Driver Card**: Name, phone, license plate, 1-click **Call Driver** & **WhatsApp Driver** buttons.
- **âš¡ Boost Fare Controls**: Riders waiting for assignment can add +â‚¹100, +â‚¹200, or +â‚¹500 Peak Boost.
- **ðŸš« 1-Click Ride Cancellation**: Safely cancel with real-time alerts to Dispatch.
- **â­ 5-Star Ratings & Written Reviews**: Recalculates driver average ratings dynamically.
- **âš ï¸ Ride Issue Reporting**: 6 categories (`SAFETY`, `DRIVER_BEHAVIOR`, `OVERCHARGING`, `ROUTE_DEVIATION`, `VEHICLE_CONDITION`, `LOST_ITEM`).

---

### 5. ðŸ”” Automated Notification Engine (`app/db.php`, `app/footer.php`)
- **Meta WhatsApp Cloud API**: Automated instant notifications for OTPs, new bookings, driver dispatch, cancellations, and fare updates.
- **Firebase Cloud Messaging (FCM)**: Real-time web push notifications with audio chimes and in-app floating toast alerts.

---

## ðŸš€ Execution & Quality Checklist (Feature by Feature)

1. [x] **Feature 1: 4-Step Booking Engine & Route Pricing** (Verified Live)
2. [x] **Feature 2: WhatsApp OTP & Unified Global Session State** (Verified Live)
3. [x] **Feature 3: Single Ride URL Spotlight & Auto-Scroll** (Verified Live)
4. [x] **Feature 4: Dispatch Command Tower Driver Assignment & Filter Locking** (Verified Live)
5. [x] **Feature 5: Passenger 5-Star Ratings & Dynamic Driver Rating Averages** (Verified Live)
6. [x] **Feature 6: Meta WhatsApp & FCM Real-Time Notification Pipeline** (Verified Live)
7. [x] **Feature 7: Ride Issue Reporting & Dispatch Resolution Desk** (Verified Live)
