# 🕒 Passenger Ride Radar & Single Ride Spotlight (`app/rides.php`)

`app/rides.php` provides riders with real-time tracking of their active and historical bookings.

---

## 1. Single Ride Spotlight View (`?id={BOOKING_ID}`)

When a passenger finishes booking on `index.php` or opens a direct link:
1. URL format: `https://pavancab.com/app/rides.php?id={ID}&phone={PHONE}`
2. The server fetches the target booking, verifies session ownership, and renders a **✨ YOUR BOOKED RIDE (#GTA-XXXXXX)** Spotlight Card at the top.
3. Automatically smooth-scrolls straight to the spotlight card on load.

---

## 2. Active Trip Radar Features

1. **Ola/Uber 3-Step Live Progress Stepper**:
   - `1. Booked` ➔ `2. Driver Dispatched` ➔ `3. On Trip`
2. **Assigned Driver Card**:
   - Displays Driver Name, Phone Number, Vehicle License Plate.
   - **Call Driver** and **WhatsApp Driver** 1-click action buttons.
3. **⚡ Boost Driver Fare**:
   - Allows passengers waiting for driver assignment to boost fare (+₹100, +₹200, +₹500 Peak Boost) to attract nearby drivers.
4. **🚫 Cancel Ride**:
   - 1-click ride cancellation with notification to Dispatch and Driver.
5. **⭐ Star Rating & Reviews**:
   - For completed rides, passengers can rate (1-5 stars) and write comments.
   - Recalculates driver average ratings dynamically in database.
6. **🔗 Direct Open Link**:
   - Every ride card has an explicit **"Open Page"** button and clickable booking reference.

---

## 3. Session & Access Control
1. **Authenticated Users**: Access is granted immediately if `$_SESSION['user']` exists, regardless of whether prior bookings exist. Logged-in riders see their personalized dashboard without login prompts.
2. **Guest Direct Links**: Unauthenticated users opening direct booking spotlight links (`?id=...&phone=...`) can track that specific booking securely via phone verification.
3. **Unauthenticated Gate**: If neither session nor booking parameters are present, users see the WhatsApp 1-click login prompt.
