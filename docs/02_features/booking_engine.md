# 🚕 4-Step Booking Engine & Fare Calculation

## 1. Booking Modes

PAVANCAB supports three distinct booking modes on `app/index.php`:

1. **One-Way Transfers (`one_way`)**:
   - Dynamic pickup dropdown (`app/pickups.php?type=oneway`).
   - Dynamic drop locations with distance and per-tier fares (`app/drops.php?pickup_id=X`).
   - Fixed, guaranteed point-to-point pricing.

2. **Hourly Rentals (`hourly`)**:
   - 4 Hours (40 KM), 8 Hours (80 KM), or 12 Hours (120 KM) packages (`app/hourly.php?place_id=X`).
   - Extra KM, extra Hour, and night allowance rates defined in `goahourfares`.

3. **Sightseeing Tours (`tour`)**:
   - Curated Goa tour packages (North Goa, South Goa, Dudhsagar Waterfalls) (`app/tours.php?place_id=X`).
   - Full-day private cab transfers with transparent pricing.

---

## 2. 4-Step Booking Progression

```
[ Step 1: Route & Service ] ➔ [ Step 2: Schedule Time ] ➔ [ Step 3: Choose Cab ] ➔ [ Step 4: Confirm & Book ]
```

- **Step 1 (Route & Service)**: Publicly accessible to all visitors.
- **Step 2 (Schedule Time)**: WhatsApp login required. Offers "Book for Right Now" or "Schedule for Later".
- **Step 3 (Choose Cab)**: Displays 4 vehicle tiers with live computed fixed pricing:
  - **Sedan AC Cab**: Swift Dzire, Toyota Etios (4 Seater).
  - **Ertiga AC**: Maruti Ertiga (6 Seater).
  - **SUV (6-7 Seater)**: Toyota Innova / Marazzo.
  - **Innova Crysta VIP**: Luxury Crysta AC.
- **Step 4 (Confirm & Book)**: Pre-fills passenger name & phone from session, accepts flight/pickup notes, creates booking in `app_bookings`, sends instant WhatsApp & FCM alerts, and redirects to the **Single Ride Spotlight Page** (`rides.php?id={ID}`).
