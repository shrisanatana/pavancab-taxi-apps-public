# ⭐ Star Ratings & Ride Issue Reports Desk

## 1. Passenger Star Ratings & Driver Feedback

- **Trigger**: Displayed automatically on completed ride cards in `app/rides.php`.
- **Interactive Interface**: 5-star selector with hover animations (`handleStarHover`, `handleStarClick`) and optional text review input.
- **Backend Processing (`/user/rate-ride` in `app/api_rides.php`)**:
  - Updates `user_rating`, `user_review`, and `rated_at` in `app_bookings`.
  - Recalculates driver average rating (`app_drivers.rating`) and total rating counts (`app_drivers.total_ratings`).
- **Live Dispatch Visibility**:
  - Star ratings and passenger reviews appear live on ride cards in `app/dashboard.php`.

---

## 2. Ride Issue Reports Desk (`app_ride_reports`)

- **Passenger Reporting Interface**: Accessible via the **Report** button on `app/rides.php`.
- **Categories Supported**:
  - 🛡️ Safety Concern (`SAFETY`)
  - 🗣️ Driver Behavior (`DRIVER_BEHAVIOR`)
  - 💰 Fare Dispute (`OVERCHARGING`)
  - 🗺️ Route Deviation (`ROUTE_DEVIATION`)
  - 🚖 Cab Condition (`VEHICLE_CONDITION`)
  - 💼 Lost Item (`LOST_ITEM`)
- **Automated Dispatch Alert**:
  - `notifyAdminAndTeamRideReport()` sends instant WhatsApp message to Super Admin and team dispatchers with booking ID, reporter phone, category, severity, and description.
- **Admin Resolution Desk**:
  - Dispatchers can review, investigate, and mark reports resolved with notes in `app/dashboard.php`.
