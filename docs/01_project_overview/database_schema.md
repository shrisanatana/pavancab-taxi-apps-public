# 🗄️ Database Schema & Architecture

The database connection is managed via `db()` singleton in `app/db.php` utilizing standard `mysqli` with UTF-8 (`utf8mb4`) encoding.

---

## 1. Core Tables

### `app_bookings`
Stores all ride requests, status lifecycle, driver assignments, and passenger ratings.

| Column | Type | Description |
|---|---|---|
| `id` | `INT AUTO_INCREMENT PRIMARY KEY` | Internal ID |
| `booking_ref` | `VARCHAR(50) NOT NULL` | Unique reference (e.g. `GTA-852109`) |
| `user_email` | `VARCHAR(255)` | Booking user email |
| `customer_name` | `VARCHAR(255) NOT NULL` | Passenger full name |
| `customer_phone` | `VARCHAR(50) NOT NULL` | Passenger mobile number |
| `trip_type` | `VARCHAR(50)` | `one_way`, `hourly`, `tour` |
| `pickup_location` | `VARCHAR(255) NOT NULL` | Pickup address / hotel |
| `drop_location` | `VARCHAR(255) NOT NULL` | Drop address / destination |
| `pickup_date` | `VARCHAR(50) NOT NULL` | Date (`YYYY-MM-DD`) |
| `pickup_time` | `VARCHAR(50) NOT NULL` | Time (`HH:MM`) |
| `cab_type` | `VARCHAR(50) NOT NULL` | `Sedan`, `Ertiga`, `SUV`, `Crysta` |
| `total_fare` | `DECIMAL(10,2) NOT NULL` | Total fixed fare amount in INR |
| `driver_id` | `INT NULL` | Foreign key to `app_drivers.id` |
| `driver_name` | `VARCHAR(255) NULL` | Assigned driver name |
| `driver_phone` | `VARCHAR(50) NULL` | Assigned driver phone |
| `vehicle_number` | `VARCHAR(50) NULL` | Vehicle license plate |
| `driver_decision` | `VARCHAR(50)` | `NONE`, `ACCEPTED`, `DECLINED` |
| `status` | `VARCHAR(50) DEFAULT 'PENDING'` | `PENDING`, `CONFIRMED`, `IN_TRANSIT`, `COMPLETED`, `CANCELLED_BY_ADMIN`, `CANCELLED_BY_USER` |
| `user_rating` | `INT DEFAULT 0` | Star rating (1 to 5) |
| `user_review` | `TEXT NULL` | Written passenger review |
| `rated_at` | `DATETIME NULL` | Rating timestamp |
| `special_notes` | `TEXT NULL` | Special requests or fare boost logs |
| `created_at` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | Timestamp |

---

### `app_drivers`
Fleet registry of drivers with real-time status and average star ratings.

| Column | Type | Description |
|---|---|---|
| `id` | `INT AUTO_INCREMENT PRIMARY KEY` | Driver ID |
| `name` | `VARCHAR(255) NOT NULL` | Driver name |
| `phone` | `VARCHAR(50) NOT NULL UNIQUE` | WhatsApp contact number |
| `car_model` | `VARCHAR(255)` | Vehicle model (e.g. `Swift Dzire`) |
| `plate_number` | `VARCHAR(50)` | Number plate (e.g. `GA-03-T-1234`) |
| `status` | `VARCHAR(50) DEFAULT 'available'` | `available`, `on_trip`, `offline` |
| `rating` | `DECIMAL(3,2) DEFAULT 5.0` | Average star rating |
| `total_ratings` | `INT DEFAULT 0` | Total completed rating reviews |

---

### `app_team_members`
Dispatchers and team members allowed to access the Dispatch Tower.

| Column | Type | Description |
|---|---|---|
| `id` | `INT AUTO_INCREMENT PRIMARY KEY` | Member ID |
| `member_name` | `VARCHAR(255) NOT NULL` | Dispatcher name |
| `member_phone` | `VARCHAR(50) NULL` | Contact number |
| `member_email` | `VARCHAR(255) NULL` | Email address |
| `role` | `VARCHAR(50) DEFAULT 'team'` | `team`, `admin` |
| `is_active` | `TINYINT(1) DEFAULT 1` | Active status flag |

---

### `app_otp_store`
Temporary storage for 6-digit WhatsApp OTP verification codes.

| Column | Type | Description |
|---|---|---|
| `id` | `INT AUTO_INCREMENT PRIMARY KEY` | ID |
| `phone` | `VARCHAR(20) NOT NULL` | 10-digit mobile number |
| `otp` | `VARCHAR(10) NOT NULL` | 6-digit OTP |
| `expires_at` | `DATETIME NOT NULL` | 10-minute expiry timestamp |

---

### `app_ride_reports`
Passenger safety and service quality reports submitted from the My Rides screen.

| Column | Type | Description |
|---|---|---|
| `id` | `INT AUTO_INCREMENT PRIMARY KEY` | Report ID |
| `booking_id` | `INT NOT NULL` | Linked `app_bookings.id` |
| `reporter_phone` | `VARCHAR(50)` | Passenger phone number |
| `reporter_name` | `VARCHAR(255)` | Passenger name |
| `issue_category` | `VARCHAR(100)` | `SAFETY`, `DRIVER_BEHAVIOR`, `OVERCHARGING`, `ROUTE_DEVIATION`, `VEHICLE_CONDITION`, `LOST_ITEM` |
| `severity` | `VARCHAR(20) DEFAULT 'medium'` | `low`, `medium`, `high`, `critical` |
| `description` | `TEXT NOT NULL` | Detailed incident description |
| `status` | `VARCHAR(50) DEFAULT 'PENDING'` | `PENDING`, `INVESTIGATING`, `RESOLVED` |
| `resolved_by` | `VARCHAR(255) NULL` | Admin who resolved the report |
| `resolution_notes` | `TEXT NULL` | Resolution explanation |
| `created_at` | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | Report submission time |
