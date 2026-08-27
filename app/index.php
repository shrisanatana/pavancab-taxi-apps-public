<?php
/**
 * PAVANCAB GOA TAXI - Uber-Style 4-Step Booking Wizard (One-Way, Hourly & Tours with Real DB Fares)
 * Path: app/index.php
 */

require_once __DIR__ . '/db.php';

// Initial preloads for default view
$onewayPickups = dbRows("SELECT DISTINCT p.id, p.name FROM goaplaces p INNER JOIN goafares f ON f.goaplace_id = p.id ORDER BY p.name ASC");
$hourlyPickups = dbRows("SELECT DISTINCT p.id, p.name FROM goaplaces p INNER JOIN goahourfares h ON h.place_id = p.id ORDER BY p.name ASC");
$tourPickups   = dbRows("SELECT DISTINCT p.id, p.name FROM goaplaces p INNER JOIN goatours t ON t.place_id = p.id AND t.is_active = 1 ORDER BY p.name ASC");

$currentUser = $_SESSION['user'] ?? null;
$activePage = 'home';
include __DIR__ . '/header.php';
?>

<!-- MAIN BOOKING CONTAINER -->
<div class="max-w-3xl mx-auto space-y-6 animate-fadeIn">
  
  <!-- HERO HEADLINE -->
  <div class="text-center space-y-2">
    <div class="inline-flex items-center gap-2 bg-amber-500/10 border border-amber-500/30 text-amber-300 text-xs font-black uppercase px-4 py-1.5 rounded-full shadow-sm">
      <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
      Instant 24/7 Goa Cab & Sightseeing Dispatch
    </div>
    <h1 class="text-2xl sm:text-3xl md:text-4xl font-black text-white font-outfit uppercase tracking-tight">
      Request Goa Taxi
    </h1>
    <p class="text-xs sm:text-sm text-slate-300 max-w-lg mx-auto font-medium">
      Guaranteed lowest fixed rates for Mopa Airport, Dabolim Airport, Hourly Rentals & Curated Tours
    </p>
  </div>

  <!-- FCM auto-connects silently for logged-in users -->

  <!-- 4-STEP PROGRESS INDICATOR -->
  <div class="uber-card p-1.5 rounded-xl border border-slate-800 flex items-center justify-between text-[9.5px] font-black uppercase tracking-tight shadow-md">
    <div id="badge-step-1" onclick="goToStep(1)" class="flex-1 text-center py-1.5 px-1 rounded-lg bg-amber-400 text-slate-950 font-extrabold shadow-sm transition cursor-pointer">
      1. Route & Service
    </div>
    <div class="px-0.5 text-slate-600 text-[8px]">➔</div>
    <div id="badge-step-2" onclick="goToStep(2)" class="flex-1 text-center py-1.5 px-1 rounded-lg text-slate-400 transition cursor-pointer">
      2. Schedule Time
    </div>
    <div class="px-0.5 text-slate-600 text-[8px]">➔</div>
    <div id="badge-step-3" onclick="goToStep(3)" class="flex-1 text-center py-1.5 px-1 rounded-lg text-slate-400 transition cursor-pointer">
      3. Choose Cab
    </div>
    <div class="px-0.5 text-slate-600 text-[8px]">➔</div>
    <div id="badge-step-4" onclick="goToStep(4)" class="flex-1 text-center py-1.5 px-1 rounded-lg text-slate-400 transition cursor-pointer">
      4. Confirm
    </div>
  </div>

  <!-- BOOKING WIZARD CARD -->
  <div class="uber-card p-6 sm:p-8 rounded-3xl border border-slate-800 space-y-6 shadow-2xl relative overflow-hidden">
    <form id="booking-form" onsubmit="handleConfirmBooking(event)" class="space-y-6 relative z-10">
      <input type="hidden" id="booking_trip_type" value="one_way">
      <input type="hidden" id="selected_cab_type" value="Sedan">
      <input type="hidden" id="selected_total_fare" value="0">
      <input type="hidden" id="hourly_hours" value="8">

      <!-- ======================================================== -->
      <!-- STEP 1: SERVICE TYPE & ROUTE SELECTION -->
      <!-- ======================================================== -->
      <div id="step-1" class="space-y-5 animate-fadeIn">
        <!-- 3 SERVICE TABS -->
        <div class="flex items-center justify-between gap-1 p-1 bg-slate-950 rounded-2xl border border-slate-800">
          <button type="button" onclick="switchBookingMode('one_way')" id="tab-one_way" class="flex-1 py-2.5 px-2 rounded-xl font-black text-xs uppercase tracking-wider transition flex items-center justify-center gap-1.5 bg-amber-400 text-slate-950 shadow-md">
            <i data-lucide="navigation" class="w-3.5 h-3.5"></i> One-Way Drop
          </button>
          <button type="button" onclick="switchBookingMode('hourly')" id="tab-hourly" class="flex-1 py-2.5 px-2 rounded-xl font-black text-xs uppercase tracking-wider transition flex items-center justify-center gap-1.5 text-slate-400 hover:text-white">
            <i data-lucide="clock" class="w-3.5 h-3.5"></i> Hourly Rental
          </button>
          <button type="button" onclick="switchBookingMode('tour')" id="tab-tour" class="flex-1 py-2.5 px-2 rounded-xl font-black text-xs uppercase tracking-wider transition flex items-center justify-center gap-1.5 text-slate-400 hover:text-white">
            <i data-lucide="palmtree" class="w-3.5 h-3.5"></i> Sightseeing
          </button>
        </div>

        <!-- 1.1 ONE-WAY MODE -->
        <div id="mode-one_way-section" class="space-y-4">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1.5 flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> Pickup Location / Airport
            </label>
            <select id="oneway_pickup" onchange="fetchDropLocations(this.value)" class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
              <option value="">-- Select Pickup Location --</option>
              <?php foreach ($onewayPickups as $p): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1.5 flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span> Drop Destination
            </label>
            <select id="oneway_drop" onchange="calculateOneWayFare()" class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
              <option value="">-- Select Pickup Location First --</option>
            </select>
          </div>
        </div>

        <!-- 1.2 HOURLY MODE -->
        <div id="mode-hourly-section" class="hidden space-y-4">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1.5 flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> Starting Pickup Area / Hotel Location
            </label>
            <select id="hourly_pickup" onchange="fetchHourlyFares(this.value)" class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
              <option value="">-- Select Pickup Area --</option>
              <?php foreach ($hourlyPickups as $p): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1.5 flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-indigo-400"></span> Rental Duration Package
            </label>
            <div class="grid grid-cols-3 gap-2.5">
              <button type="button" onclick="selectHourlyPackage(4)" id="pkg-4" class="p-3 rounded-2xl border border-slate-800 bg-slate-900 text-center transition">
                <span class="text-xs font-black text-white block">4 Hours</span>
                <span class="text-[10px] text-slate-400 font-semibold">40 KM</span>
              </button>
              <button type="button" onclick="selectHourlyPackage(8)" id="pkg-8" class="p-3 rounded-2xl border border-amber-400 bg-amber-500/10 text-center transition">
                <span class="text-xs font-black text-amber-300 block">8 Hours</span>
                <span class="text-[10px] text-amber-400 font-semibold">80 KM</span>
              </button>
              <button type="button" onclick="selectHourlyPackage(12)" id="pkg-12" class="p-3 rounded-2xl border border-slate-800 bg-slate-900 text-center transition">
                <span class="text-xs font-black text-white block">12 Hours</span>
                <span class="text-[10px] text-slate-400 font-semibold">120 KM</span>
              </button>
            </div>
          </div>
          <p class="text-[11px] text-slate-400 italic">Includes dedicated driver, AC cab, fuel & toll allowance for the duration.</p>
        </div>

        <!-- 1.3 TOURS MODE -->
        <div id="mode-tour-section" class="hidden space-y-4">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1.5 flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> Hotel / Resort Pickup Location
            </label>
            <select id="tour_pickup" onchange="fetchTourFares(this.value)" class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
              <option value="">-- Select Pickup Hotel Area --</option>
              <?php foreach ($tourPickups as $p): ?>
                <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['name']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1.5 flex items-center gap-1.5">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span> Available Sightseeing Tours
            </label>
            <select id="tour_package" onchange="calculateTourFare()" class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
              <option value="">-- Select Pickup Hotel Area First --</option>
            </select>
          </div>

          <!-- Tour Highlights Box -->
          <div id="tour-details-box" class="bg-slate-950/80 p-4 rounded-2xl border border-slate-800 text-xs space-y-1.5 hidden">
            <div class="flex items-center justify-between">
              <span class="font-extrabold text-amber-300 text-sm block" id="tour-highlights-title">Tour Details</span>
              <span class="text-[10px] bg-emerald-500/20 text-emerald-300 font-bold px-2 py-0.5 rounded-full border border-emerald-500/30" id="tour-highlights-duration">8-10 Hours</span>
            </div>
            <p class="text-slate-300 text-xs" id="tour-highlights-desc"></p>
          </div>
        </div>

        <div class="pt-3 flex justify-end">
          <button type="button" onclick="goToStep(2)" class="gradient-btn-gold text-slate-950 font-black text-xs px-8 py-3.5 rounded-xl uppercase tracking-wider flex items-center gap-2 shadow-xl">
            Next: Schedule Time ➔
          </button>
        </div>
      </div>

      <!-- ======================================================== -->
      <!-- STEP 2: PICKUP DATE & TIME SCHEDULE -->
      <!-- ======================================================== -->
      <div id="step-2" class="hidden space-y-5 animate-fadeIn">
        <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 text-xs flex justify-between items-center">
          <div>
            <span class="text-[10px] text-slate-500 uppercase block font-bold">Selected Service & Route</span>
            <span class="text-amber-300 font-extrabold text-sm" id="summary-route-step2">Panjim ➔ Dabolim Airport</span>
          </div>
          <button type="button" onclick="goToStep(1)" class="text-xs text-amber-400 hover:underline font-bold">
            Change Route
          </button>
        </div>

        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-2">When do you need the cab?</label>
          <div class="grid grid-cols-2 gap-3 mb-4">
            <button type="button" onclick="setScheduleOption('now')" id="btn-now" class="p-3 rounded-xl border font-bold text-xs uppercase transition border-amber-400 bg-amber-500/10 text-amber-300">
              ⚡ Pick Up Now
            </button>
            <button type="button" onclick="setScheduleOption('later')" id="btn-later" class="p-3 rounded-xl border font-bold text-xs uppercase transition border-slate-800 bg-slate-900 text-slate-400">
              📅 Schedule For Later
            </button>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1.5">Pickup Date</label>
            <input type="date" id="pickup_date" value="<?php echo date('Y-m-d'); ?>" required class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1.5">Pickup Time</label>
            <input type="time" id="pickup_time" value="<?php echo date('H:i'); ?>" required class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
        </div>

        <div class="pt-3 flex justify-between gap-3">
          <button type="button" onclick="goToStep(1)" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs px-6 py-3.5 rounded-xl uppercase">
            ⬅ Back
          </button>
          <button type="button" onclick="goToStep(3)" class="gradient-btn-gold text-slate-950 font-black text-xs px-8 py-3.5 rounded-xl uppercase tracking-wider flex items-center gap-2 shadow-xl">
            Next: Choose Cab ➔
          </button>
        </div>
      </div>

      <!-- ======================================================== -->
      <!-- STEP 3: CHOOSE CAB TIER -->
      <!-- ======================================================== -->
      <div id="step-3" class="hidden space-y-5 animate-fadeIn">
        <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 text-xs flex justify-between items-center">
          <div>
            <span class="text-[10px] text-slate-500 uppercase block font-bold">Selected Route & Time</span>
            <span class="text-amber-300 font-extrabold text-sm" id="summary-route">Panjim ➔ Dabolim</span>
            <span class="text-slate-400 block font-semibold" id="summary-datetime">Today at 15:30</span>
          </div>
          <button type="button" onclick="goToStep(2)" class="text-xs text-amber-400 hover:underline font-bold">
            Edit Time
          </button>
        </div>

        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-3">Select Vehicle Tier</label>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <!-- Sedan -->
            <div onclick="selectVehicle('Sedan')" id="tier-Sedan" class="p-4 rounded-2xl border cursor-pointer border-amber-400 bg-amber-500/10 flex items-center justify-between transition uber-card-hover">
              <div class="flex items-center gap-3">
                <img src="./cabs/sedan.png" alt="Sedan" class="w-16 h-10 object-contain" onError="this.onerror=null; this.src='https://pavancab.com/cabs/sedan.png';">
                <div>
                  <h4 class="text-xs font-black text-white uppercase font-outfit">Sedan (4 Seater)</h4>
                  <p class="text-[10px] text-slate-400 font-semibold">Swift Dzire / Etios • AC</p>
                </div>
              </div>
              <span class="text-base font-black text-amber-300 font-outfit" id="fare-tier-Sedan">₹0</span>
            </div>

            <!-- Ertiga -->
            <div onclick="selectVehicle('Ertiga')" id="tier-Ertiga" class="p-4 rounded-2xl border cursor-pointer border-slate-800 bg-slate-900 flex items-center justify-between transition uber-card-hover">
              <div class="flex items-center gap-3">
                <img src="./cabs/ertiga.png" alt="Ertiga" class="w-16 h-10 object-contain" onError="this.onerror=null; this.src='https://pavancab.com/cabs/ertiga.png';">
                <div>
                  <h4 class="text-xs font-black text-white uppercase font-outfit">Ertiga (6 Seater)</h4>
                  <p class="text-[10px] text-slate-400 font-semibold">Maruti Ertiga • AC</p>
                </div>
              </div>
              <span class="text-base font-black text-amber-300 font-outfit" id="fare-tier-Ertiga">₹0</span>
            </div>

            <!-- SUV -->
            <div onclick="selectVehicle('SUV')" id="tier-SUV" class="p-4 rounded-2xl border cursor-pointer border-slate-800 bg-slate-900 flex items-center justify-between transition uber-card-hover">
              <div class="flex items-center gap-3">
                <img src="./cabs/innova.png" alt="SUV" class="w-16 h-10 object-contain" onError="this.onerror=null; this.src='https://pavancab.com/cabs/innova.png';">
                <div>
                  <h4 class="text-xs font-black text-white uppercase font-outfit">SUV (6-7 Seater)</h4>
                  <p class="text-[10px] text-slate-400 font-semibold">Toyota Innova / Marazzo</p>
                </div>
              </div>
              <span class="text-base font-black text-amber-300 font-outfit" id="fare-tier-SUV">₹0</span>
            </div>

            <!-- Crysta -->
            <div onclick="selectVehicle('Crysta')" id="tier-Crysta" class="p-4 rounded-2xl border cursor-pointer border-slate-800 bg-slate-900 flex items-center justify-between transition uber-card-hover">
              <div class="flex items-center gap-3">
                <img src="./cabs/innova.png" alt="Crysta" class="w-16 h-10 object-contain" onError="this.onerror=null; this.src='https://pavancab.com/cabs/innova.png';">
                <div>
                  <h4 class="text-xs font-black text-white uppercase font-outfit">Innova Crysta VIP</h4>
                  <p class="text-[10px] text-slate-400 font-semibold">Luxury Innova Crysta • AC</p>
                </div>
              </div>
              <span class="text-base font-black text-amber-300 font-outfit" id="fare-tier-Crysta">₹0</span>
            </div>
          </div>
        </div>

        <div class="pt-3 flex justify-between gap-3">
          <button type="button" onclick="goToStep(2)" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs px-6 py-3.5 rounded-xl uppercase">
            ⬅ Back
          </button>
          <button type="button" onclick="goToStep(4)" class="gradient-btn-gold text-slate-950 font-black text-xs px-8 py-3.5 rounded-xl uppercase tracking-wider flex items-center gap-2 shadow-xl">
            Next: Contact Info ➔
          </button>
        </div>
      </div>

      <!-- ======================================================== -->
      <!-- STEP 4: PASSENGER DETAILS & CONFIRMATION -->
      <!-- ======================================================== -->
      <div id="step-4" class="hidden space-y-5 animate-fadeIn">
        <div class="bg-gradient-to-r from-amber-500/10 via-yellow-500/5 to-amber-500/10 border border-amber-500/40 p-4 rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-inner">
          <div>
            <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Guaranteed Fixed Fare</span>
            <span class="text-3xl font-black text-amber-400 font-outfit block" id="final-fare-amount">₹0</span>
            <span class="text-[10px] text-slate-400 font-medium">Includes Fuel, Driver Allowance, Tolls & AC</span>
          </div>
          <div class="text-right">
            <span class="text-xs font-black text-amber-300 block" id="final-cab-label">Sedan AC Cab</span>
            <span class="text-[11px] text-slate-300 block font-semibold" id="final-route-label">Panjim ➔ Dabolim Airport</span>
          </div>
        </div>

        <!-- STEP 4 LOGIN REQUIRED BANNER (Visible if not logged in) -->
        <div id="step4-login-required" class="<?php echo ($currentUser ? 'hidden ' : ''); ?>bg-gradient-to-r from-amber-500/20 via-amber-500/10 to-amber-500/20 border border-amber-500/40 p-5 rounded-2xl text-center space-y-3 shadow-xl">
          <div class="w-12 h-12 rounded-2xl bg-emerald-500/20 border border-emerald-500/40 flex items-center justify-center mx-auto text-emerald-400">
            <i data-lucide="lock" class="w-6 h-6"></i>
          </div>
          <div class="space-y-1">
            <h4 class="text-base font-black text-white font-outfit uppercase">WhatsApp Login Required to Book Cab</h4>
            <p class="text-xs text-slate-300 max-w-sm mx-auto">
              Please log in with your WhatsApp number to confirm your Goa taxi, receive live driver location updates, and access trip receipts.
            </p>
          </div>
          <button type="button" onclick="openAuthModal()" class="w-full gradient-btn-gold text-slate-950 font-black text-xs py-3.5 rounded-xl uppercase tracking-wider flex items-center justify-center gap-2 shadow-xl transition">
            <i data-lucide="message-square" class="w-4 h-4"></i> Login with WhatsApp to Complete Booking
          </button>
        </div>

        <!-- STEP 4 BOOKING FORM (Visible if logged in) -->
        <div id="step4-booking-form" class="<?php echo (!$currentUser ? 'hidden ' : ''); ?>space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black text-slate-300 uppercase mb-1.5">Passenger Full Name</label>
              <input type="text" id="customer_name" value="<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>" placeholder="e.g. Rahul Sharma" required class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
            </div>
            <div>
              <label class="block text-xs font-black text-slate-300 uppercase mb-1.5">WhatsApp Mobile Number</label>
              <input type="tel" id="customer_phone" value="<?php echo htmlspecialchars($currentUser['mobile'] ?? ''); ?>" placeholder="+919876543210" maxlength="20" required readonly class="w-full px-4 py-3 bg-slate-950 border border-amber-500/40 rounded-xl text-sm font-bold text-amber-300 tracking-wider">
            </div>
          </div>

          <div>
            <label class="block text-xs font-black text-slate-400 uppercase mb-1">Special Notes / Flight Number (Optional)</label>
            <input type="text" id="special_notes" placeholder="e.g. Flight 6E-542 arriving at Mopa Airport" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
          </div>

          <div id="booking-status-msg" class="text-xs text-center font-bold min-h-[16px]"></div>

          <div class="pt-3 flex justify-between gap-3">
            <button type="button" onclick="goToStep(3)" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs px-6 py-3.5 rounded-xl uppercase">
              ⬅ Change Cab
            </button>
            <button type="submit" id="submit-booking-btn" class="gradient-btn-gold text-slate-950 font-black text-xs sm:text-sm px-8 py-3.5 rounded-xl uppercase tracking-wider flex items-center gap-2 shadow-2xl">
              <i data-lucide="check-circle" class="w-5 h-5"></i> ⚡ Confirm & Book Goa Cab Now
            </button>
          </div>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
const isUserLoggedIn = <?php echo json_encode(!empty($currentUser)); ?>;
let currentFares = { Sedan: 0, Ertiga: 0, SUV: 0, Crysta: 0 };
let currentBookingMode = 'one_way';
let cachedHourlyFares = {};
let cachedTourMap = {};
let currentStep = 1;

function switchBookingMode(mode) {
  currentBookingMode = mode;
  document.getElementById('booking_trip_type').value = mode;

  const btnOneWay = document.getElementById('tab-one_way');
  const btnHourly = document.getElementById('tab-hourly');
  const btnTour   = document.getElementById('tab-tour');

  const secOneWay = document.getElementById('mode-one_way-section');
  const secHourly = document.getElementById('mode-hourly-section');
  const secTour   = document.getElementById('mode-tour-section');

  const activeClass = 'flex-1 py-2.5 px-2 rounded-xl font-black text-xs uppercase tracking-wider transition flex items-center justify-center gap-1.5 bg-amber-400 text-slate-950 shadow-md';
  const idleClass   = 'flex-1 py-2.5 px-2 rounded-xl font-black text-xs uppercase tracking-wider transition flex items-center justify-center gap-1.5 text-slate-400 hover:text-white';

  btnOneWay.className = (mode === 'one_way') ? activeClass : idleClass;
  btnHourly.className = (mode === 'hourly') ? activeClass : idleClass;
  btnTour.className   = (mode === 'tour') ? activeClass : idleClass;

  secOneWay.classList.toggle('hidden', mode !== 'one_way');
  secHourly.classList.toggle('hidden', mode !== 'hourly');
  secTour.classList.toggle('hidden', mode !== 'tour');

  if (mode === 'one_way') calculateOneWayFare();
  else if (mode === 'hourly') {
    const pickupId = document.getElementById('hourly_pickup').value;
    if (pickupId) fetchHourlyFares(pickupId);
    else calculateHourlyFare();
  }
  else if (mode === 'tour') {
    const pickupId = document.getElementById('tour_pickup').value;
    if (pickupId) fetchTourFares(pickupId);
  }
}

function setScheduleOption(opt) {
  const btnNow = document.getElementById('btn-now');
  const btnLater = document.getElementById('btn-later');
  const dateInput = document.getElementById('pickup_date');
  const timeInput = document.getElementById('pickup_time');

  if (opt === 'now') {
    btnNow.className = 'p-3 rounded-xl border font-bold text-xs uppercase transition border-amber-400 bg-amber-500/10 text-amber-300';
    btnLater.className = 'p-3 rounded-xl border font-bold text-xs uppercase transition border-slate-800 bg-slate-900 text-slate-400';
    const now = new Date();
    dateInput.value = now.toISOString().split('T')[0];
    timeInput.value = now.toTimeString().substring(0, 5);
  } else {
    btnLater.className = 'p-3 rounded-xl border font-bold text-xs uppercase transition border-amber-400 bg-amber-500/10 text-amber-300';
    btnNow.className = 'p-3 rounded-xl border font-bold text-xs uppercase transition border-slate-800 bg-slate-900 text-slate-400';
  }
}

function goToStep(step) {
  if (step >= 2) {
    if (!window.isUserLoggedIn && !window.currentUser) {
      openAuthModal();
      const msg = document.getElementById('auth-msg');
      if (msg) msg.innerHTML = '<span class="text-amber-400 font-black">🔒 Please log in with WhatsApp to view cab fares & schedule your trip!</span>';
      return;
    }

    let summaryText = '';
    if (currentBookingMode === 'one_way') {
      const pSelect = document.getElementById('oneway_pickup');
      const dSelect = document.getElementById('oneway_drop');
      if (!pSelect.value || !dSelect.value) {
        alert('Please select both Pickup Location and Drop Destination.');
        return;
      }
      const pName = pSelect.options[pSelect.selectedIndex].text;
      const dName = dSelect.options[dSelect.selectedIndex]?.dataset?.name || dSelect.options[dSelect.selectedIndex]?.text;
      summaryText = `${pName} ➔ ${dName}`;
    } else if (currentBookingMode === 'hourly') {
      const pSelect = document.getElementById('hourly_pickup');
      if (!pSelect.value) {
        alert('Please select your Starting Pickup Area for hourly rental.');
        return;
      }
      const hours = document.getElementById('hourly_hours').value;
      summaryText = `${pSelect.options[pSelect.selectedIndex].text} (${hours} Hours / ${hours * 10} KM Rental)`;
    } else if (currentBookingMode === 'tour') {
      const pSelect = document.getElementById('tour_pickup');
      const tSelect = document.getElementById('tour_package');
      if (!pSelect.value || !tSelect.value) {
        alert('Please select your Hotel / Pickup Location and Sightseeing Tour.');
        return;
      }
      const tourObj = cachedTourMap[tSelect.value];
      const tourTitle = tourObj ? tourObj.title : tSelect.value;
      summaryText = `${tourTitle} (from ${pSelect.options[pSelect.selectedIndex].text})`;
    }

    document.getElementById('summary-route-step2').innerText = summaryText;
    document.getElementById('summary-route').innerText = summaryText;
    document.getElementById('final-route-label').innerText = summaryText;
  }

  if (step >= 3) {
    const date = document.getElementById('pickup_date').value;
    const time = document.getElementById('pickup_time').value;
    document.getElementById('summary-datetime').innerText = `${date} at ${time}`;
  }

  if (step >= 4) {
    if (!window.isUserLoggedIn && !window.currentUser) {
      openAuthModal();
      const msg = document.getElementById('auth-msg');
      if (msg) msg.innerHTML = '<span class="text-amber-400 font-black">🔒 Please log in with WhatsApp first to complete booking!</span>';
      return;
    }
    const fare = parseFloat(document.getElementById('selected_total_fare').value || 0);
    if (!fare || fare <= 0) {
      alert('Please select a cab tier to continue.');
      return;
    }

    // Ensure booking form is revealed and populated
    const loginReq = document.getElementById('step4-login-required');
    const bookForm = document.getElementById('step4-booking-form');
    if (loginReq) loginReq.classList.add('hidden');
    if (bookForm) bookForm.classList.remove('hidden');

    const u = window.currentUser;
    if (u) {
      const nameInput = document.getElementById('customer_name');
      const phoneInput = document.getElementById('customer_phone');
      if (nameInput && u.name && !nameInput.value) nameInput.value = u.name;
      if (phoneInput && u.mobile && !phoneInput.value) phoneInput.value = u.mobile;
    }
  }

  currentStep = step;
  [1, 2, 3, 4].forEach(s => {
    const el = document.getElementById(`step-${s}`);
    const badge = document.getElementById(`badge-step-${s}`);
    if (s === step) {
      el.classList.remove('hidden');
      badge.className = 'flex-1 text-center py-2 rounded-xl bg-amber-400 text-slate-950 font-extrabold shadow-md transition';
    } else {
      el.classList.add('hidden');
      badge.className = 'flex-1 text-center py-2 rounded-xl text-slate-400 transition';
    }
  });

  if (typeof lucide !== 'undefined') lucide.createIcons();
}

async function fetchDropLocations(pickupId) {
  if (!pickupId) return;
  const select = document.getElementById('oneway_drop');
  select.innerHTML = '<option value="">Loading destinations...</option>';
  try {
    const res = await fetch(`./drops.php?pickup_id=${pickupId}`);
    const drops = await res.json();
    select.innerHTML = '<option value="">-- Select Drop Destination --</option>';
    drops.forEach(d => {
      select.innerHTML += `<option value="${d.id}" data-sedan="${d.sedan_fare}" data-suv="${d.suv_fare}" data-name="${d.destination}">${d.destination} (${d.distance} km)</option>`;
    });
  } catch (e) {
    select.innerHTML = '<option value="">Failed to load destinations</option>';
  }
}

function calculateOneWayFare() {
  const dSelect = document.getElementById('oneway_drop');
  const opt = dSelect.options[dSelect.selectedIndex];

  if (!opt || !opt.dataset.sedan) {
    updateFaresDisplay({ Sedan: 0, Ertiga: 0, SUV: 0, Crysta: 0 });
    return;
  }

  const sedan = parseFloat(opt.dataset.sedan || 0);
  const suv = parseFloat(opt.dataset.suv || 0);

  currentFares = {
    Sedan: sedan,
    Ertiga: Math.round(suv * 0.95),
    SUV: suv,
    Crysta: Math.round(suv * 1.25)
  };

  updateFaresDisplay(currentFares);
}

async function fetchHourlyFares(placeId) {
  if (!placeId) return;
  try {
    const res = await fetch(`./hourly.php?place_id=${placeId}`);
    const data = await res.json();
    if (data.success && data.fares) {
      cachedHourlyFares = data.fares;
    }
  } catch (e) {}
  calculateHourlyFare();
}

function selectHourlyPackage(hours) {
  document.getElementById('hourly_hours').value = hours;
  [4, 8, 12].forEach(h => {
    const el = document.getElementById(`pkg-${h}`);
    if (h === hours) {
      el.className = 'p-3 rounded-2xl border border-amber-400 bg-amber-500/10 text-center transition';
      el.children[0].className = 'text-xs font-black text-amber-300 block';
      el.children[1].className = 'text-[10px] text-amber-400 font-semibold';
    } else {
      el.className = 'p-3 rounded-2xl border border-slate-800 bg-slate-900 text-center transition';
      el.children[0].className = 'text-xs font-black text-white block';
      el.children[1].className = 'text-[10px] text-slate-400 font-semibold';
    }
  });
  calculateHourlyFare();
}

function calculateHourlyFare() {
  const hours = parseInt(document.getElementById('hourly_hours').value || 8);
  const hKey = String(hours);

  if (cachedHourlyFares && cachedHourlyFares.Sedan) {
    currentFares = {
      Sedan: cachedHourlyFares.Sedan[hKey] || (hours === 4 ? 1700 : (hours === 8 ? 2500 : 3500)),
      Ertiga: cachedHourlyFares.Ertiga[hKey] || (hours === 4 ? 2100 : (hours === 8 ? 3100 : 4300)),
      SUV: cachedHourlyFares.SUV[hKey] || (hours === 4 ? 2500 : (hours === 8 ? 3700 : 5100)),
      Crysta: cachedHourlyFares.Crysta[hKey] || (hours === 4 ? 3100 : (hours === 8 ? 4600 : 6400))
    };
  } else {
    const rates = {
      4: { Sedan: 1700, Ertiga: 2100, SUV: 2500, Crysta: 3100 },
      8: { Sedan: 2500, Ertiga: 3100, SUV: 3700, Crysta: 4600 },
      12: { Sedan: 3500, Ertiga: 4300, SUV: 5100, Crysta: 6400 }
    };
    currentFares = rates[hours] || rates[8];
  }

  updateFaresDisplay(currentFares);
}

async function fetchTourFares(placeId) {
  if (!placeId) return;
  const select = document.getElementById('tour_package');
  const descBox = document.getElementById('tour-details-box');
  select.innerHTML = '<option value="">Loading available sightseeing tours...</option>';
  descBox.classList.add('hidden');
  cachedTourMap = {};

  try {
    const res = await fetch(`./tours.php?place_id=${placeId}`);
    const data = await res.json();
    
    if (data.success && data.tours && data.tours.length > 0) {
      select.innerHTML = '<option value="">-- Choose Available Sightseeing Tour --</option>';
      data.tours.forEach(t => {
        cachedTourMap[t.tour_name] = t;
        select.innerHTML += `<option value="${t.tour_name}">${t.title}</option>`;
      });
      // Auto-select first tour
      select.selectedIndex = 1;
      calculateTourFare();
    } else {
      select.innerHTML = '<option value="">No tours available from this location</option>';
      updateFaresDisplay({ Sedan: 0, Ertiga: 0, SUV: 0, Crysta: 0 });
      descBox.classList.remove('hidden');
      document.getElementById('tour-highlights-title').innerText = 'No Tours from this location';
      document.getElementById('tour-highlights-desc').innerText = 'Sightseeing packages are not mapped for this pickup location. Please choose an adjacent beach/town pickup or select an Hourly Rental package.';
    }
  } catch (e) {
    select.innerHTML = '<option value="">Failed to load tours</option>';
  }
}

function calculateTourFare() {
  const tSelect = document.getElementById('tour_package');
  const tourKey = tSelect.value;
  const descBox = document.getElementById('tour-details-box');

  if (!tourKey || !cachedTourMap[tourKey]) {
    updateFaresDisplay({ Sedan: 0, Ertiga: 0, SUV: 0, Crysta: 0 });
    descBox.classList.add('hidden');
    return;
  }

  const t = cachedTourMap[tourKey];
  currentFares = {
    Sedan: t.Sedan || 0,
    Ertiga: t.Ertiga || 0,
    SUV: t.SUV || 0,
    Crysta: t.Crysta || 0
  };

  descBox.classList.remove('hidden');
  document.getElementById('tour-highlights-title').innerText = t.title;
  document.getElementById('tour-highlights-duration').innerText = t.duration || '8-10 Hours';
  document.getElementById('tour-highlights-desc').innerText = t.desc;

  updateFaresDisplay(currentFares);
}

function updateFaresDisplay(fares) {
  document.getElementById('fare-tier-Sedan').innerText = '₹' + (fares.Sedan || 0);
  document.getElementById('fare-tier-Ertiga').innerText = '₹' + (fares.Ertiga || 0);
  document.getElementById('fare-tier-SUV').innerText = '₹' + (fares.SUV || 0);
  document.getElementById('fare-tier-Crysta').innerText = '₹' + (fares.Crysta || 0);

  const selectedCab = document.getElementById('selected_cab_type').value || 'Sedan';
  const total = fares[selectedCab] || fares.Sedan || 0;

  document.getElementById('selected_total_fare').value = total;
  document.getElementById('final-fare-amount').innerText = '₹' + total;
  document.getElementById('final-cab-label').innerText = `${selectedCab} AC Cab`;
}

function selectVehicle(cabType) {
  document.getElementById('selected_cab_type').value = cabType;
  ['Sedan', 'Ertiga', 'SUV', 'Crysta'].forEach(c => {
    const el = document.getElementById(`tier-${c}`);
    if (c === cabType) {
      el.className = 'p-4 rounded-2xl border cursor-pointer border-amber-400 bg-amber-500/10 flex items-center justify-between transition uber-card-hover';
    } else {
      el.className = 'p-4 rounded-2xl border cursor-pointer border-slate-800 bg-slate-900 flex items-center justify-between transition uber-card-hover';
    }
  });
  const total = currentFares[cabType] || 0;
  document.getElementById('selected_total_fare').value = total;
  document.getElementById('final-fare-amount').innerText = '₹' + total;
  document.getElementById('final-cab-label').innerText = `${cabType} AC Cab`;
}

async function handleConfirmBooking(e) {
  e.preventDefault();

  if (!window.isUserLoggedIn && !window.currentUser) {
    openAuthModal();
    const msg = document.getElementById('auth-msg');
    if (msg) msg.innerHTML = '<span class="text-amber-400 font-black">🔒 Please log in with WhatsApp first to book your cab!</span>';
    return;
  }

  const fare = parseFloat(document.getElementById('selected_total_fare').value || 0);
  if (!fare || fare <= 0) {
    alert('Please complete route selection and calculate fare before confirming.');
    return;
  }

  let pickup = '';
  let drop = '';

  if (currentBookingMode === 'one_way') {
    const pSelect = document.getElementById('oneway_pickup');
    const dSelect = document.getElementById('oneway_drop');
    pickup = pSelect.options[pSelect.selectedIndex]?.text || '';
    drop   = dSelect.options[dSelect.selectedIndex]?.dataset?.name || dSelect.options[dSelect.selectedIndex]?.text || '';
  } else if (currentBookingMode === 'hourly') {
    const pSelect = document.getElementById('hourly_pickup');
    const hours = document.getElementById('hourly_hours').value;
    pickup = pSelect.options[pSelect.selectedIndex]?.text || 'Goa';
    drop   = `${hours} Hours / ${hours * 10} KM Local Hourly Rental`;
  } else if (currentBookingMode === 'tour') {
    const pSelect = document.getElementById('tour_pickup');
    const tSelect = document.getElementById('tour_package');
    const tourObj = cachedTourMap[tSelect.value];
    pickup = pSelect.options[pSelect.selectedIndex]?.text || 'Goa Hotel';
    drop   = tourObj ? tourObj.title : tSelect.value;
  }

  const name = document.getElementById('customer_name').value.trim();
  const phone = document.getElementById('customer_phone').value.trim();
  const date = document.getElementById('pickup_date').value;
  const time = document.getElementById('pickup_time').value;
  const cab = document.getElementById('selected_cab_type').value;
  const notes = document.getElementById('special_notes').value.trim();
  const msg = document.getElementById('booking-status-msg');
  const btn = document.getElementById('submit-booking-btn');

  if (phone) {
    try { localStorage.setItem('pavancab_user_phone', phone); } catch(e){}
    if (typeof window.syncFcmTokenWithUser === 'function') {
      window.syncFcmTokenWithUser({ mobile: phone });
    }
  }

  // Politely request notification permission on submit if not yet determined
  if ('Notification' in window && Notification.permission === 'default') {
    try {
      Notification.requestPermission().then(perm => {
        if (perm === 'granted' && typeof window.syncFcmTokenWithUser === 'function') {
          window.syncFcmTokenWithUser({ mobile: phone });
        }
      });
    } catch(e){}
  }

  btn.innerHTML = '<span class="animate-spin inline-block mr-1">⏳</span> Booking Cab & Dispatching...';
  btn.disabled = true;

  try {
    const res = await fetch('./bookings.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        customer_name: name,
        customer_phone: phone,
        pickup_location: pickup,
        drop_location: drop,
        pickup_date: date,
        pickup_time: time,
        cab_type: cab,
        total_fare: fare,
        trip_type: currentBookingMode,
        special_notes: notes,
        fcm_token: window.fcmTokenStored || (function(){ try{ return localStorage.getItem('pavancab_fcm_token'); }catch(e){ return ''; } })() || ''
      })
    });
    const data = await res.json();

    if (data.success) {
      msg.innerHTML = '<span class="text-emerald-400 font-black text-sm">✓ Ride Booked Successfully! Opening Live Ride Radar...</span>';
      setTimeout(() => {
        const targetId = (data.booking && data.booking.id) ? data.booking.id : '';
        window.location.href = `./rides.php?id=${encodeURIComponent(targetId)}&phone=${encodeURIComponent(phone)}`;
      }, 700);
    } else {
      msg.innerHTML = `<span class="text-red-400 font-bold">${data.error || 'Failed to book cab'}</span>`;
      btn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> ⚡ Confirm & Book Goa Cab Now';
      btn.disabled = false;
    }
  } catch (err) {
    msg.innerHTML = '<span class="text-red-400 font-bold">Network error. Please try again.</span>';
    btn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> ⚡ Confirm & Book Goa Cab Now';
    btn.disabled = false;
  }
}
</script>

<?php include __DIR__ . '/footer.php'; ?>
