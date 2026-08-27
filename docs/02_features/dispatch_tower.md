# 🛡️ Dispatch Command Tower (`app/dashboard.php`)

The Dispatch Command Tower is the centralized live control center for dispatching cabs across Goa.

---

## 1. Live Feed & Auto-Refresh Engine

- **Polling Frequency**: Every 3 seconds (`fetchLiveUpdates()`).
- **Hash-Differential Refresh**: Computes `currentHash` across all active bookings. Only re-renders DOM when booking data, driver assignment, or fares change.
- **Active Filter Locking**: When the dispatcher filters by `🔴 Needs Driver`, `Assigned`, `On Trip`, `Completed`, `Cancelled`, or `All`, the active filter is strictly preserved across background live polls.
- **Modal Lock Protection**: Background sync never disrupts an open modal (`isModalActive = true`).

---

## 2. Dispatcher Operations

1. **⚡ Assign Driver Modal**:
   - Select from saved driver roster (`app_drivers`) or enter ad-hoc driver name, phone, and vehicle plate number.
   - Automatically marks driver status as `on_trip`.
   - Sends automated Meta WhatsApp order to driver and dispatched notification to passenger.
   - Triggers FCM Web Push notification with audio chime.

2. **🚖 Status Lifecycle Management**:
   - `PENDING`: Needs Driver.
   - `CONFIRMED`: Driver Assigned.
   - `IN_TRANSIT`: Driver on Trip.
   - `COMPLETED`: Ride Finished.
   - `CANCELLED`: Cancelled by Admin or Passenger.

3. **💰 Fare Adjustment & Surge Boost**:
   - Dispatchers can adjust fare or add boost amounts directly with reason notes.
   - Sends updated fare WhatsApp message and FCM alert to rider and driver.

4. **🖨️ Receipt & Trip Slip Generation**:
   - 1-click printable receipt slip for passenger accounting.

5. **👥 Driver Fleet & Team Management**:
   - Add, toggle availability (`available` / `on_trip`), and view completed trips per driver.
   - Invite new team dispatchers with role permissions.
