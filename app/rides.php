<?php
/**
 * PAVANCAB GOA TAXI - Private Passenger Live Rides & Tracking Radar
 * Path: app/rides.php
 */

require_once __DIR__ . '/db.php';

$currentUser      = $_SESSION['user'] ?? null;
$isLoggedIn       = !empty($currentUser);
$targetBookingId  = intval($_GET['id'] ?? $_GET['booking_id'] ?? 0);
$targetRef        = trim($_GET['ref'] ?? $_GET['booking_ref'] ?? '');
$targetPhone      = cleanPhoneDigits($_GET['phone'] ?? '');
$spotlightBooking = null;
$phoneClean       = '';

$conn = db();

if ($currentUser) {
    $rawPhone = $currentUser['mobile'] ?? $currentUser['phone'] ?? $currentUser['member_phone'] ?? '';
    if (!empty($rawPhone)) {
        $phoneClean = substr(preg_replace('/\D/', '', $rawPhone), -10);
    }
}

if (!$phoneClean && !empty($_SESSION['active_booking_phone'])) {
    $phoneClean = substr(preg_replace('/\D/', '', $_SESSION['active_booking_phone']), -10);
}

// If target booking specified via URL, load it directly
if ($targetBookingId > 0 || !empty($targetRef)) {
    $stmtTarget = $conn->prepare("SELECT b.*, 
            COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, 
            COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone, 
            COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number, 'GA-03-T-1234') as vehicle_number,
            d.name as driver_name_full, d.phone as driver_phone_full, d.car_model, d.plate_number 
            FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id 
            WHERE b.id = ? OR b.booking_ref = ? LIMIT 1");
    $stmtTarget->bind_param('is', $targetBookingId, $targetRef);
    $stmtTarget->execute();
    $rT = $stmtTarget->get_result();
    if ($rowT = $rT->fetch_assoc()) {
        $spotlightBooking = $rowT;
        if (empty($phoneClean) && !empty($rowT['customer_phone'])) {
            $phoneClean = substr(preg_replace('/\D/', '', $rowT['customer_phone']), -10);
            $_SESSION['active_booking_phone'] = $rowT['customer_phone'];
        }
    }
}

if (!$phoneClean && $targetPhone) {
    $phoneClean = substr($targetPhone, -10);
    $_SESSION['active_booking_phone'] = $targetPhone;
}

$userEmail    = $currentUser['email'] ?? '';
$userBookings = [];
$activeRides  = [];
$pastRides    = [];

if ($phoneClean || $userEmail) {
    if ($phoneClean && $userEmail) {
        $sql = "SELECT b.*, 
                COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, 
                COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone, 
                COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number, 'GA-03-T-1234') as vehicle_number,
                d.name as driver_name_full, d.phone as driver_phone_full, d.car_model, d.plate_number 
                FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id 
                WHERE (b.customer_phone IS NOT NULL AND RIGHT(REPLACE(REPLACE(b.customer_phone, '+', ''), ' ', ''), 10) = ?)
                   OR (b.user_email = ? AND ? != '')
                ORDER BY b.id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('sss', $phoneClean, $userEmail, $userEmail);
    } elseif ($phoneClean) {
        $sql = "SELECT b.*, 
                COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, 
                COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone, 
                COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number, 'GA-03-T-1234') as vehicle_number,
                d.name as driver_name_full, d.phone as driver_phone_full, d.car_model, d.plate_number 
                FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id 
                WHERE (b.customer_phone IS NOT NULL AND RIGHT(REPLACE(REPLACE(b.customer_phone, '+', ''), ' ', ''), 10) = ?)
                ORDER BY b.id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $phoneClean);
    } else {
        $sql = "SELECT b.*, 
                COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, 
                COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone, 
                COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number, 'GA-03-T-1234') as vehicle_number,
                d.name as driver_name_full, d.phone as driver_phone_full, d.car_model, d.plate_number 
                FROM app_bookings b LEFT JOIN app_drivers d ON b.driver_id = d.id 
                WHERE b.user_email = ?
                ORDER BY b.id DESC";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $userEmail);
    }
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) {
        $userBookings[] = $row;
    }
}

if ($spotlightBooking) {
    $found = false;
    foreach ($userBookings as $b) {
        if ($b['id'] == $spotlightBooking['id']) {
            $found = true;
            break;
        }
    }
    if (!$found) {
        array_unshift($userBookings, $spotlightBooking);
    }
}

foreach ($userBookings as $b) {
    $statusUpper = strtoupper($b['status']);
    if (in_array($statusUpper, ['PENDING', 'CONFIRMED', 'ASSIGNED', 'IN_TRANSIT', 'ON_TRIP', 'ARRIVED'])) {
        $activeRides[] = $b;
    } else {
        $pastRides[] = $b;
    }
}

$hasAccess = ($isLoggedIn || !empty($phoneClean) || !empty($spotlightBooking));

$activePage = 'rides';
include __DIR__ . '/header.php';
?>

<div class="max-w-4xl mx-auto space-y-8 animate-fadeIn">

  <?php if (!$hasAccess): ?>
    <script>
      (function(){
        try {
          const storedPhone = localStorage.getItem('pavancab_user_phone');
          if (storedPhone && storedPhone.length >= 10 && !window.location.search.includes('phone=')) {
            const u = new URL(window.location.href);
            u.searchParams.set('phone', storedPhone);
            window.location.replace(u.href);
          }
        } catch(e){}
      })();
    </script>
    <!-- UNAUTHENTICATED PRIVATE VIEW: LOGIN WITH WHATSAPP -->
    <div class="max-w-md mx-auto my-10 text-center space-y-6 animate-fadeIn">
      <div class="w-20 h-20 rounded-3xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center mx-auto text-amber-400 shadow-[0_0_40px_rgba(245,158,11,0.2)]">
        <i data-lucide="shield-check" class="w-10 h-10"></i>
      </div>
      
      <div class="space-y-2">
        <h1 class="text-2xl sm:text-3xl font-black text-white font-outfit uppercase tracking-tight">
          Track Your Goa Taxi Rides
        </h1>
        <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed">
          Log in with your WhatsApp number to view your private active ride radar, driver details, and trip receipts.
        </p>
      </div>

      <div class="uber-card p-6 sm:p-7 rounded-3xl border border-slate-800 space-y-4 shadow-2xl">
        <p class="text-xs font-bold text-amber-300">Fast 1-click WhatsApp OTP verification</p>
        <button onclick="openAuthModal()" class="w-full gradient-btn-gold text-slate-950 font-black text-xs py-3.5 rounded-xl uppercase tracking-wider flex items-center justify-center gap-2 shadow-xl">
          <i data-lucide="message-square" class="w-4 h-4"></i> Login with WhatsApp
        </button>
      </div>

      <div class="pt-2">
        <a href="./index.php" class="text-xs text-slate-400 hover:text-white inline-flex items-center gap-1">
          <i data-lucide="plus-circle" class="w-3.5 h-3.5 text-amber-400"></i> Or Book a New Goa Cab Transfer
        </a>
      </div>
    </div>

  <?php else: ?>
    <!-- AUTHENTICATED USER PRIVATE RIDES SCREEN -->

    <!-- FCM auto-connects silently - no manual banner needed -->

    <!-- PAGE HEADER -->
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-800/80 pb-4">
      <div>
        <div class="flex items-center gap-2">
          <h1 class="text-2xl sm:text-3xl font-black text-white font-outfit uppercase tracking-tight flex items-center gap-2">
            <i data-lucide="navigation" class="w-7 h-7 text-amber-400"></i> My Active Goa Rides
          </h1>
          <span class="text-[10px] bg-emerald-500/10 text-emerald-300 border border-emerald-500/30 px-2.5 py-0.5 rounded-full font-bold flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-ping"></span> Live Radar
          </span>
        </div>
        <p class="text-xs text-slate-400 font-medium mt-1">
          Logged in as <strong class="text-white"><?php echo htmlspecialchars($currentUser['name'] ?? ($spotlightBooking['customer_name'] ?? 'Passenger')); ?></strong> 
          <?php if ($phoneClean): ?>(ðŸ“ž +91 <?php echo htmlspecialchars($phoneClean); ?>)<?php endif; ?>
        </p>
      </div>

      <div class="flex items-center gap-2">
        <a href="./index.php" class="gradient-btn-gold text-slate-950 font-black text-xs px-4 py-2 rounded-xl uppercase flex items-center gap-1 shadow-md">
          <i data-lucide="plus" class="w-3.5 h-3.5"></i> Book Cab
        </a>
        <a href="./auth.php?action=logout" class="bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-400 hover:text-white text-xs font-bold px-3 py-2 rounded-xl transition">
          Switch Account
        </a>
      </div>
    </div>

    <!-- SECTION 1: LIVE ACTIVE RIDES RADAR -->
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-black text-white uppercase font-outfit flex items-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
          Active Trips (<span id="active-rides-count"><?php echo count($activeRides); ?></span>)
        </h2>
      </div>

      <div id="active-rides-feed-container" class="space-y-6">
        <?php if (empty($activeRides)): ?>
          <div class="uber-card p-10 rounded-3xl border border-slate-800 text-center space-y-4 shadow-xl">
            <div class="w-14 h-14 rounded-full bg-slate-900 text-amber-400 flex items-center justify-center mx-auto text-2xl font-black">
              ðŸš–
            </div>
            <div class="space-y-1">
              <h3 class="text-base font-black text-white uppercase font-outfit">No Active Rides in Progress</h3>
              <p class="text-xs text-slate-400 max-w-sm mx-auto">
                You have no ongoing or pending trips right now. Request a taxi whenever you're ready!
              </p>
            </div>
            <div>
              <a href="./index.php" class="inline-flex items-center gap-2 gradient-btn-gold text-slate-950 font-black text-xs px-5 py-2.5 rounded-xl uppercase shadow-lg">
                <i data-lucide="plus-circle" class="w-4 h-4"></i> Request a Goa Cab Now
              </a>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($activeRides as $b): 
            $statusUpper = strtoupper($b['status']);
            $isPending = ($statusUpper === 'PENDING');
            $isAssigned = in_array($statusUpper, ['CONFIRMED', 'ASSIGNED']);
            $isInTransit = in_array($statusUpper, ['IN_TRANSIT', 'ON_TRIP', 'ARRIVED']);
            
            $tripTypeBadge = 'ðŸš– One-Way Drop';
            if ($b['trip_type'] === 'hourly') $tripTypeBadge = 'â±ï¸ Hourly Rental';
            elseif ($b['trip_type'] === 'tour' || $b['trip_type'] === 'sightseeing') $tripTypeBadge = 'ðŸ–ï¸ Sightseeing Tour';

            $step = 1;
            if ($isAssigned) $step = 2;
            elseif ($isInTransit) $step = 3;
            elseif ($statusUpper === 'COMPLETED') $step = 4;
          ?>
            <div class="user-ride-card uber-card p-6 sm:p-7 rounded-3xl border border-slate-800 space-y-5 shadow-2xl relative overflow-hidden transition-all" id="ride-card-<?php echo $b['id']; ?>">
              
              <!-- TOP STRIP -->
              <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/80 pb-4">
                <div class="space-y-1">
                  <div class="flex flex-wrap items-center gap-2">
                    <a href="./rides.php?id=<?php echo $b['id']; ?>" class="text-sm font-black text-amber-300 hover:text-amber-200 font-outfit uppercase underline tracking-wider">#<?php echo htmlspecialchars($b['booking_ref']); ?></a>
                    <span class="text-[10px] bg-slate-800 text-amber-300 font-extrabold px-2.5 py-0.5 rounded-full border border-slate-700"><?php echo htmlspecialchars($b['cab_type']); ?></span>
                    <span class="text-[10px] bg-indigo-500/20 text-indigo-300 font-extrabold px-2.5 py-0.5 rounded-full border border-indigo-500/30"><?php echo $tripTypeBadge; ?></span>
                  </div>
                  <span class="text-[11px] text-slate-400 block font-semibold">Passenger: <strong><?php echo htmlspecialchars($b['customer_name']); ?></strong></span>
                </div>

                <div class="flex items-center gap-3">
                  <div class="text-right">
                    <span class="text-2xl font-black text-amber-400 font-outfit block" id="fare-text-<?php echo $b['id']; ?>">
                      â‚¹<?php echo floatval($b['total_fare']); ?>
                    </span>
                    <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Fixed Fare</span>
                  </div>
                  <span class="text-xs font-black uppercase px-3 py-1.5 rounded-xl border <?php 
                    if ($isPending) echo 'bg-amber-400/20 text-amber-300 border-amber-400/50 animate-pulse';
                    elseif ($isAssigned) echo 'bg-indigo-500/20 text-indigo-300 border-indigo-500/50';
                    elseif ($isInTransit) echo 'bg-yellow-500/20 text-yellow-300 border-yellow-500/50 animate-pulse';
                    else echo 'bg-emerald-500/20 text-emerald-300 border-emerald-500/50';
                  ?>" id="status-badge-<?php echo $b['id']; ?>">
                    <?php echo str_replace('_', ' ', htmlspecialchars($b['status'])); ?>
                  </span>
                </div>
              </div>

              <!-- OLA/UBER STYLE 4-STEP PROGRESS STEPPER -->
              <div class="bg-slate-950/70 p-3.5 rounded-2xl border border-slate-800/80">
                <div class="grid grid-cols-4 gap-1 text-center">
                  <div class="space-y-1">
                    <div class="h-1.5 rounded-full <?php echo $step >= 1 ? 'bg-amber-400' : 'bg-slate-800'; ?>"></div>
                    <span class="text-[10px] font-extrabold <?php echo $step >= 1 ? 'text-amber-300' : 'text-slate-500'; ?> block">1. Booked</span>
                  </div>
                  <div class="space-y-1">
                    <div class="h-1.5 rounded-full <?php echo $step >= 2 ? 'bg-indigo-400' : 'bg-slate-800'; ?>"></div>
                    <span class="text-[10px] font-extrabold <?php echo $step >= 2 ? 'text-indigo-300' : 'text-slate-500'; ?> block">2. Dispatched</span>
                  </div>
                  <div class="space-y-1">
                    <div class="h-1.5 rounded-full <?php echo $step >= 3 ? 'bg-yellow-400' : 'bg-slate-800'; ?><?php echo $step === 3 ? ' animate-pulse' : ''; ?>"></div>
                    <span class="text-[10px] font-extrabold <?php echo $step >= 3 ? 'text-yellow-300' : 'text-slate-500'; ?> block">3. On Trip</span>
                  </div>
                  <div class="space-y-1">
                    <div class="h-1.5 rounded-full <?php echo $step >= 4 ? 'bg-emerald-400' : 'bg-slate-800'; ?>"></div>
                    <span class="text-[10px] font-extrabold <?php echo $step >= 4 ? 'text-emerald-300' : 'text-slate-500'; ?> block">4. Completed</span>
                  </div>
                </div>
              </div>

              <!-- ROUTE & SCHEDULE DETAILS -->
              <div class="bg-slate-950/70 p-4 rounded-2xl border border-slate-800/80 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
                <div class="space-y-1.5">
                  <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
                    <span class="text-slate-400 font-bold">Pickup:</span>
                    <span class="text-white font-extrabold"><?php echo htmlspecialchars($b['pickup_location']); ?></span>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
                    <span class="text-slate-400 font-bold">Destination:</span>
                    <span class="text-white font-extrabold"><?php echo htmlspecialchars($b['drop_location']); ?></span>
                  </div>
                </div>

                <div class="space-y-1 md:text-right">
                  <div class="text-slate-300 font-bold">
                    ðŸ“… Schedule: <span class="text-amber-300 font-black"><?php echo formatIndianDateTime($b['pickup_date'], $b['pickup_time']); ?></span>
                  </div>
                  <?php if ($b['special_notes']): ?>
                    <p class="text-[11px] text-amber-400 font-semibold italic text-left md:text-right">ðŸ“ <?php echo nl2br(htmlspecialchars($b['special_notes'])); ?></p>
                  <?php endif; ?>
                </div>
              </div>

              <!-- DRIVER DETAILS (WHEN ASSIGNED) VS RADAR (WHEN PENDING) -->
              <?php if (($isAssigned || $isInTransit) && ($b['driver_phone'] || $b['driver_name'])): ?>
                <div class="bg-gradient-to-r from-indigo-950/40 via-slate-900 to-indigo-950/40 border border-indigo-500/30 p-4 rounded-2xl flex flex-wrap justify-between items-center gap-3 text-xs shadow-inner">
                  <div class="flex items-center gap-3">
                    <div class="w-11 h-11 rounded-2xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center font-black">
                      <i data-lucide="car" class="w-6 h-6"></i>
                    </div>
                    <div>
                      <div class="flex items-center gap-2">
                        <span class="text-[10px] text-indigo-300 uppercase font-black">Dispatched Goa Driver</span>
                        <span class="text-[10px] bg-amber-400/20 text-amber-300 px-2 py-0.5 rounded font-bold">â­ 4.9 Verified</span>
                      </div>
                      <span class="font-black text-white text-base block mt-0.5"><?php echo htmlspecialchars($b['driver_name']); ?></span>
                      <span class="text-amber-300 font-mono font-black text-xs">ðŸš– <?php echo htmlspecialchars($b['vehicle_number']); ?></span>
                    </div>
                  </div>

                  <div class="flex items-center gap-2">
                    <a href="tel:<?php echo htmlspecialchars($b['driver_phone']); ?>" class="bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs px-3.5 py-2.5 rounded-xl flex items-center gap-1.5 shadow">
                      <i data-lucide="phone" class="w-4 h-4 text-amber-400"></i> Call Driver
                    </a>
                    <a href="https://wa.me/<?php echo cleanPhoneDigits($b['driver_phone']); ?>" target="_blank" class="bg-emerald-600 hover:bg-emerald-500 text-slate-950 font-black text-xs px-3.5 py-2.5 rounded-xl flex items-center gap-1.5 shadow-md">
                      <i data-lucide="message-circle" class="w-4 h-4"></i> WhatsApp Driver
                    </a>
                  </div>
                </div>
              <?php elseif ($isPending): ?>
                <!-- RADAR SEARCHING -->
                <div class="bg-amber-500/10 border border-amber-500/30 p-4 rounded-2xl flex items-center justify-between text-xs">
                  <div class="flex items-center gap-3">
                    <div class="w-3 h-3 rounded-full bg-amber-400 radar-pulse"></div>
                    <div>
                      <span class="font-black text-amber-300 block">Dispatching Nearest Goa Cab...</span>
                      <span class="text-slate-400 text-[11px]">Dispatch tower is assigning a nearby driver for your booking ref #<?php echo $b['booking_ref']; ?>.</span>
                    </div>
                  </div>
                  <a href="https://wa.me/919000000000?text=Hi%2C%20checking%20status%20for%20booking%20%23<?php echo $b['booking_ref']; ?>" target="_blank" class="text-xs text-amber-300 hover:underline font-bold flex items-center gap-1">
                    <i data-lucide="help-circle" class="w-3.5 h-3.5"></i> Support
                  </a>
                </div>
              <?php endif; ?>

              <!-- ACTION BAR: CANCEL + REPORT + SOS + BOOST FARE -->
              <div class="flex flex-wrap items-center justify-between gap-2.5 pt-3 border-t border-slate-800/80">
                <div class="flex items-center gap-2">
                  <?php if ($isPending): ?>
                  <button onclick="handleCancelRide(<?php echo $b['id']; ?>)" class="text-xs font-bold text-slate-400 hover:text-red-400 hover:bg-red-500/10 px-3 py-1.5 rounded-xl border border-slate-700/80 transition flex items-center gap-1">
                    <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Cancel
                  </button>
                  <?php endif; ?>
                  <button onclick="openReportModal(<?php echo $b['id']; ?>)" class="text-xs font-black text-amber-300 hover:text-amber-200 bg-amber-500/10 hover:bg-amber-500/20 px-3 py-1.5 rounded-xl border border-amber-500/30 transition flex items-center gap-1">
                    <i data-lucide="alert-triangle" class="w-3.5 h-3.5 text-amber-400"></i> Report
                  </button>
                  <a href="./rides.php?id=<?php echo $b['id']; ?>" class="text-xs font-black text-emerald-300 hover:text-emerald-200 bg-emerald-500/10 hover:bg-emerald-500/20 px-3 py-1.5 rounded-xl border border-emerald-500/30 transition flex items-center gap-1">
                    <i data-lucide="external-link" class="w-3.5 h-3.5 text-emerald-400"></i> Open Page
                  </a>
                </div>

                <?php if ($isPending): ?>
                  <!-- BOOST PEAK FARE (ONLY WHEN LOOKING FOR DRIVER) -->
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span class="text-[11px] font-black text-amber-400 uppercase flex items-center gap-1">
                      <i data-lucide="zap" class="w-3 h-3"></i> Boost:
                    </span>
                    <button onclick="handleBoostFare(<?php echo $b['id']; ?>, 100)" class="bg-slate-900 hover:bg-amber-400/20 border border-amber-500/30 text-amber-300 text-xs font-bold px-2.5 py-1 rounded-lg transition">
                      +â‚¹100
                    </button>
                    <button onclick="handleBoostFare(<?php echo $b['id']; ?>, 200)" class="bg-slate-900 hover:bg-amber-400/20 border border-amber-500/30 text-amber-300 text-xs font-bold px-2.5 py-1 rounded-lg transition">
                      +â‚¹200
                    </button>
                    <button onclick="handleBoostFare(<?php echo $b['id']; ?>, 500)" class="gradient-btn-gold text-slate-950 text-xs font-black px-3 py-1 rounded-lg shadow-md transition">
                      +â‚¹500 Boost
                    </button>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- SECTION 2: PAST RIDES & BOOKING HISTORY -->
    <div class="space-y-4 pt-4 border-t border-slate-800/80">
      <div class="flex items-center justify-between cursor-pointer" onclick="toggleHistoryPanel()">
        <h2 class="text-lg font-black text-white uppercase font-outfit flex items-center gap-2">
          <i data-lucide="history" class="w-5 h-5 text-slate-400"></i>
          Past Rides & Booking History (<span id="past-rides-count"><?php echo count($pastRides); ?></span>)
        </h2>
        <span class="text-xs text-amber-400 font-bold hover:underline" id="history-toggle-text">Show Past History â–¼</span>
      </div>

      <div id="past-rides-container" class="space-y-5 hidden">
        <?php if (empty($pastRides)): ?>
          <div id="past-rides-empty" class="text-xs text-slate-500 text-center py-4">No completed or cancelled trips in history.</div>
        <?php else: ?>
          <?php foreach ($pastRides as $b): 
            $isCompleted = (strtoupper($b['status']) === 'COMPLETED');
            $currentRating = intval($b['user_rating'] ?? 0);
            $currentReview = htmlspecialchars($b['user_review'] ?? '');
          ?>
            <div class="uber-card p-5 sm:p-6 rounded-3xl border border-slate-800 space-y-4 shadow-xl" id="past-ride-<?php echo (int)$b['id']; ?>">
              <div class="flex flex-wrap justify-between items-center gap-2">
                <div>
                  <span class="text-sm font-black text-white font-outfit uppercase">#<?php echo htmlspecialchars($b['booking_ref']); ?></span>
                  <span class="text-[10px] bg-slate-800 text-slate-300 font-bold px-2 py-0.5 rounded-md ml-1.5 uppercase"><?php echo htmlspecialchars($b['cab_type']); ?></span>
                </div>
                <div class="flex items-center gap-2">
                  <button onclick="openReportModal(<?php echo (int)$b['id']; ?>)" class="text-[11px] font-black text-amber-300 hover:text-amber-200 bg-amber-500/10 px-2.5 py-1 rounded-lg border border-amber-500/30 flex items-center gap-1">
                    <i data-lucide="alert-triangle" class="w-3 h-3 text-amber-400"></i> Report Issue
                  </button>
                  <span class="text-lg font-black text-amber-400 font-outfit">â‚¹<?php echo floatval($b['total_fare']); ?></span>
                  <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-lg border <?php echo $isCompleted ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-red-500/20 text-red-300 border-red-500/40'; ?>">
                    <?php echo str_replace('_', ' ', htmlspecialchars($b['status'])); ?>
                  </span>
                </div>
              </div>

              <div class="text-xs text-slate-300 flex flex-wrap justify-between gap-2 bg-slate-950 p-3 rounded-xl border border-slate-800">
                <div>ðŸ“ <?php echo htmlspecialchars($b['pickup_location']); ?> âž” <?php echo htmlspecialchars($b['drop_location']); ?></div>
                <div class="text-slate-400">ðŸ“… <?php echo formatIndianDateTime($b['pickup_date'], $b['pickup_time']); ?></div>
              </div>

              <?php if ($isCompleted): ?>
                <div class="bg-slate-950/80 p-4 rounded-2xl border border-amber-500/30 space-y-3">
                  <div class="flex items-center justify-between">
                    <span class="text-xs font-black text-amber-300 uppercase">
                      â­ Driver Rating & Review (<?php echo htmlspecialchars($b['driver_name'] ?: 'Driver'); ?>)
                    </span>
                    <span id="rating-status-badge-<?php echo $b['id']; ?>" class="text-[11px] font-extrabold <?php echo $currentRating > 0 ? 'text-emerald-400' : 'text-slate-400'; ?>">
                      <?php echo $currentRating > 0 ? "âœ“ $currentRating / 5 Stars Saved" : 'Click star to rate:'; ?>
                    </span>
                  </div>

                  <div class="flex items-center gap-2" id="star-group-<?php echo $b['id']; ?>">
                    <?php for ($s = 1; $s <= 5; $s++): 
                      $isFilled = ($s <= $currentRating);
                    ?>
                      <button type="button" 
                              onclick="handleStarClick(<?php echo $b['id']; ?>, <?php echo $s; ?>)" 
                              onmouseenter="handleStarHover(<?php echo $b['id']; ?>, <?php echo $s; ?>)"
                              onmouseleave="handleStarReset(<?php echo $b['id']; ?>)"
                              class="star-btn-<?php echo $b['id']; ?> text-2xl focus:outline-none transition-transform hover:scale-125"
                              data-star="<?php echo $s; ?>">
                        <span class="star-icon" style="color: <?php echo $isFilled ? '#F59E0B' : '#475569'; ?>;">
                          <?php echo $isFilled ? 'â˜…' : 'â˜†'; ?>
                        </span>
                      </button>
                    <?php endfor; ?>
                  </div>

                  <div class="flex gap-2">
                    <input type="text" id="review-input-<?php echo $b['id']; ?>" value="<?php echo $currentReview; ?>" placeholder="Share driver feedback..." class="flex-grow px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white">
                    <button type="button" onclick="handleSaveReviewText(<?php echo $b['id']; ?>)" class="gradient-btn-gold text-slate-950 font-black text-xs px-3.5 py-1.5 rounded-xl uppercase">
                      Save
                    </button>
                  </div>
                  <div id="rating-msg-<?php echo $b['id']; ?>" class="text-[10px] font-bold"></div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  <?php endif; ?>
</div>

<script>
let lastKnownActiveHash = '';

const userRatingsStore = {};
<?php foreach ($userBookings as $b): ?>
  userRatingsStore[<?php echo $b['id']; ?>] = <?php echo intval($b['user_rating'] ?? 0); ?>;
<?php endforeach; ?>

function toggleHistoryPanel() {
  const panel = document.getElementById('past-rides-container');
  const text = document.getElementById('history-toggle-text');
  if (panel.classList.contains('hidden')) {
    panel.classList.remove('hidden');
    text.innerText = 'Hide Past History â–²';
  } else {
    panel.classList.add('hidden');
    text.innerText = 'Show Past History â–¼';
  }
}

function openReportModal(bookingId) {
  document.getElementById('report_booking_id').value = bookingId;
  document.getElementById('report-msg').innerHTML = '';
  document.getElementById('report_description').value = '';
  document.getElementById('report-modal').classList.remove('hidden');
}

function closeReportModal() {
  document.getElementById('report-modal').classList.add('hidden');
}

async function handleSubmitReport(e) {
  e.preventDefault();
  const bookingId = document.getElementById('report_booking_id').value;
  const category = document.getElementById('report_category').value;
  const desc = document.getElementById('report_description').value.trim();
  const phone = '<?php echo $phoneClean; ?>' || localStorage.getItem('pavancab_user_phone') || '';
  const name = '<?php echo htmlspecialchars($currentUser['name'] ?? 'Passenger'); ?>';
  const msg = document.getElementById('report-msg');
  const btn = document.getElementById('report-submit-btn');

  btn.disabled = true;
  btn.innerHTML = 'Submitting...';

  try {
    const res = await fetch(`./api.php?action=report_issue`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        booking_id: bookingId,
        reporter_phone: phone,
        reporter_name: name,
        issue_category: category,
        description: desc
      })
    });
    const data = await res.json();
    if (data.success) {
      msg.innerHTML = '<span class="text-emerald-400 font-bold">âœ“ Report submitted! Dispatch tower has been alerted.</span>';
      setTimeout(() => closeReportModal(), 1200);
    } else {
      msg.innerHTML = `<span class="text-red-400 font-bold">${data.error || 'Failed to submit'}</span>`;
    }
  } catch(err) {
    msg.innerHTML = '<span class="text-red-400 font-bold">Network error</span>';
  } finally {
    btn.disabled = false;
    btn.innerHTML = 'Submit Report';
  }
}

function showInAppAlert(title, message) {
  let toast = document.getElementById('rides-live-toast');
  if (!toast) {
    toast = document.createElement('div');
    toast.id = 'rides-live-toast';
    toast.className = 'fixed top-4 right-4 z-50 max-w-sm bg-gradient-to-r from-amber-500 via-amber-400 to-amber-500 text-slate-950 p-4 rounded-2xl shadow-2xl font-outfit border-2 border-white/40 transform transition-all duration-500 flex items-center gap-3 animate-bounce';
    document.body.appendChild(toast);
  }
  toast.innerHTML = `
    <span class="text-2xl">ðŸš•</span>
    <div>
      <div class="text-xs font-black uppercase tracking-wider">${title}</div>
      <div class="text-xs font-bold">${message}</div>
    </div>
  `;
  toast.style.display = 'flex';
  setTimeout(() => { if (toast) toast.style.display = 'none'; }, 6000);
}

function handleStarHover(bookingId, hoverStar) {
  const buttons = document.querySelectorAll(`.star-btn-${bookingId}`);
  buttons.forEach(btn => {
    const starVal = parseInt(btn.dataset.star || 0);
    const icon = btn.querySelector('.star-icon');
    if (icon) {
      if (starVal <= hoverStar) {
        icon.style.color = '#F59E0B';
        icon.innerText = 'â˜…';
      } else {
        icon.style.color = '#475569';
        icon.innerText = 'â˜†';
      }
    }
  });
}

function handleStarReset(bookingId) {
  const savedRating = userRatingsStore[bookingId] || 0;
  const buttons = document.querySelectorAll(`.star-btn-${bookingId}`);
  buttons.forEach(btn => {
    const starVal = parseInt(btn.dataset.star || 0);
    const icon = btn.querySelector('.star-icon');
    if (icon) {
      if (starVal <= savedRating) {
        icon.style.color = '#F59E0B';
        icon.innerText = 'â˜…';
      } else {
        icon.style.color = '#475569';
        icon.innerText = 'â˜†';
      }
    }
  });
}

async function handleStarClick(bookingId, selectedStar) {
  userRatingsStore[bookingId] = selectedStar;
  handleStarReset(bookingId);

  const statusBadge = document.getElementById(`rating-status-badge-${bookingId}`);
  if (statusBadge) {
    statusBadge.className = 'text-[11px] font-extrabold text-emerald-400';
    statusBadge.innerText = `âœ“ ${selectedStar} / 5 Stars Saved`;
  }

  const reviewInput = document.getElementById(`review-input-${bookingId}`);
  const reviewText = reviewInput ? reviewInput.value.trim() : '';

  try {
    await fetch(`./api_rides.php?action=rate-ride`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        booking_id: bookingId,
        rating: selectedStar,
        review_text: reviewText
      })
    });
  } catch (err) {}
}

async function handleSaveReviewText(bookingId) {
  const selectedStar = userRatingsStore[bookingId] || 5;
  const reviewInput = document.getElementById(`review-input-${bookingId}`);
  const reviewText = reviewInput ? reviewInput.value.trim() : '';
  const msg = document.getElementById(`rating-msg-${bookingId}`);

  try {
    const res = await fetch(`./api_rides.php?action=rate-ride`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        booking_id: bookingId,
        rating: selectedStar,
        review_text: reviewText
      })
    });
    const data = await res.json();
    if (data.success && msg) {
      msg.innerHTML = '<span class="text-emerald-400">âœ“ Review saved!</span>';
    }
  } catch (err) {}
}

async function handleBoostFare(bookingId, boostAmount) {
  if (!confirm(`Boost fare by +â‚¹${boostAmount} to expedite driver assignment?`)) return;
  try {
    const res = await fetch('./api_rides.php?action=boost-fare', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ booking_id: bookingId, boost_amount: boostAmount })
    });
    const data = await res.json();
    if (data.success) {
      lastKnownActiveHash = '';
      pollCustomerRides();
      showInAppAlert('âš¡ Fare Boosted!', `+â‚¹${boostAmount} added to ride #${bookingId}`);
    } else {
      alert(data.error || 'Failed to boost fare');
    }
  } catch (e) {
    alert('Network error boosting fare');
  }
}

async function handleCancelRide(bookingId) {
  if (!confirm('Are you sure you want to cancel this booking?')) return;
  try {
    const res = await fetch(`./api_rides.php?action=cancel-booking&booking_id=${encodeURIComponent(bookingId)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ booking_id: bookingId })
    });
    const data = await res.json();
    if (data.success) {
      lastKnownActiveHash = '';
      pollCustomerRides();
      showInAppAlert('ðŸš« Ride Cancelled', `Booking #${bookingId} was cancelled.`);
    } else {
      alert(data.error || 'Failed to cancel ride');
    }
  } catch (e) {
    alert('Network error cancelling ride');
  }
}

function cleanDigits(phone) {
  return String(phone || '').replace(/\D/g, '');
}

function renderActiveRideCardHTML(b) {
  const statusUpper = (b.status || 'PENDING').toUpperCase();
  const isCancelled = (statusUpper.includes('CANCEL') || statusUpper === 'REJECTED');
  const isDone = (statusUpper === 'COMPLETED' || statusUpper === 'FINISHED');
  const isAssigned = (statusUpper === 'CONFIRMED' || statusUpper === 'ASSIGNED' || statusUpper === 'ACCEPTED' || statusUpper === 'DRIVER_ASSIGNED');
  const isInTransit = (statusUpper === 'IN_TRANSIT' || statusUpper === 'ON_TRIP' || statusUpper === 'ARRIVED');
  const isPending = (!isCancelled && !isDone && !isInTransit && !isAssigned);

  let statusBadgeClass = 'bg-slate-800 text-slate-300 border-slate-700';
  let step = 1;

  if (isPending) {
    statusBadgeClass = 'bg-amber-500/20 text-amber-300 border-amber-500/40 animate-pulse';
    step = 1;
  } else if (isAssigned) {
    statusBadgeClass = 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40';
    step = 2;
  } else if (isInTransit) {
    statusBadgeClass = 'bg-yellow-500/20 text-yellow-300 border-yellow-500/40 animate-pulse';
    step = 3;
  } else if (isDone) {
    statusBadgeClass = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40';
    step = 4;
  }

  let tripTypeBadge = 'One-Way Transfer';
  if (b.trip_type === 'round_trip') tripTypeBadge = 'Round Trip';
  else if (b.trip_type === 'sightseeing') tripTypeBadge = 'Goa Sightseeing Tour';

  let driverCard = '';
  if (b.driver_name || b.driver_phone) {
    const driverPhoneClean = cleanDigits(b.driver_phone);
    driverCard = `
      <div class="bg-gradient-to-br from-slate-900 to-indigo-950/40 p-4 rounded-2xl border border-indigo-500/30 space-y-3">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center text-xl font-black">
              ðŸ‘¨â€âœˆï¸
            </div>
            <div>
              <span class="text-[10px] text-indigo-300 font-bold uppercase tracking-wider block">Assigned Driver</span>
              <h3 class="font-extrabold text-white text-base">${b.driver_name || 'Assigned Driver'}</h3>
              <span class="text-xs text-amber-300 font-mono font-bold">ðŸš– ${b.vehicle_number || 'GA-03-T-1234'}</span>
            </div>
          </div>
          <span class="text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2 py-0.5 rounded-full font-bold uppercase">
            Dispatched
          </span>
        </div>

        <div class="flex items-center gap-2 pt-1 border-t border-indigo-500/20">
          <a href="tel:${b.driver_phone}" class="flex-1 bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold py-2 rounded-xl text-center flex items-center justify-center gap-1.5">
            <i data-lucide="phone" class="w-3.5 h-3.5 text-amber-400"></i> Call Driver
          </a>
          <a href="https://wa.me/${driverPhoneClean}?text=Hi%2C%20I%20am%20your%20passenger%20for%20PAVANCAB%20ride%20%23${b.booking_ref}" target="_blank" class="flex-1 bg-emerald-600 hover:bg-emerald-500 text-slate-950 text-xs font-black py-2 rounded-xl text-center flex items-center justify-center gap-1.5 shadow">
            <i data-lucide="send" class="w-3.5 h-3.5"></i> WhatsApp
          </a>
        </div>
      </div>
    `;
  } else {
    driverCard = `
      <div class="bg-slate-950/70 p-4 rounded-2xl border border-dashed border-amber-500/40 flex items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-amber-500/10 text-amber-400 flex items-center justify-center text-lg animate-pulse">
            â³
          </div>
          <div class="text-xs">
            <span class="font-black text-amber-300 block">Dispatching Nearest Goa Cab...</span>
            <span class="text-slate-400 text-[11px]">Dispatch tower is assigning a nearby driver for your booking ref #${b.booking_ref}.</span>
          </div>
        </div>
        <a href="https://wa.me/919000000000?text=Hi%2C%20checking%20status%20for%20booking%20%23${b.booking_ref}" target="_blank" class="text-xs text-amber-300 hover:underline font-bold flex items-center gap-1">
          <i data-lucide="help-circle" class="w-3.5 h-3.5"></i> Support
        </a>
      </div>
    `;
  }

  let boostHTML = '';
  if (isPending) {
    boostHTML = `
      <div class="flex flex-wrap items-center gap-2">
        <span class="text-xs font-black text-amber-400 uppercase flex items-center gap-1">
          <i data-lucide="zap" class="w-3.5 h-3.5"></i> Boost Fare:
        </span>
        <button onclick="handleBoostFare(${b.id}, 100)" class="bg-slate-900 hover:bg-amber-400/20 border border-amber-500/30 text-amber-300 text-xs font-bold px-3 py-1.5 rounded-xl transition">
          +â‚¹100
        </button>
        <button onclick="handleBoostFare(${b.id}, 500)" class="gradient-btn-gold text-slate-950 text-xs font-black px-3.5 py-1.5 rounded-xl shadow-md transition">
          +â‚¹500 Boost
        </button>
      </div>
    `;
  }

  return `
    <div class="user-ride-card uber-card p-6 sm:p-7 rounded-3xl border border-slate-800 space-y-5 shadow-2xl relative overflow-hidden transition-all animate-fadeIn" id="ride-card-${b.id}">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/80 pb-4">
        <div class="space-y-1">
          <div class="flex flex-wrap items-center gap-2">
            <a href="./rides.php?id=${b.id}" class="text-sm font-black text-amber-300 hover:text-amber-200 font-outfit uppercase underline tracking-wider">#${b.booking_ref}</a>
            <span class="text-[10px] bg-slate-800 text-amber-300 font-extrabold px-2.5 py-0.5 rounded-full border border-slate-700">${b.cab_type}</span>
            <span class="text-[10px] bg-indigo-500/20 text-indigo-300 font-extrabold px-2.5 py-0.5 rounded-full border border-indigo-500/30">${tripTypeBadge}</span>
          </div>
          <span class="text-[11px] text-slate-400 block font-semibold">Passenger: <strong>${b.customer_name}</strong></span>
        </div>
        <div class="flex items-center gap-3">
          <div class="text-right">
            <span class="text-2xl font-black text-amber-400 font-outfit block" id="fare-text-${b.id}">
              â‚¹${parseFloat(b.total_fare || 0)}
            </span>
            <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Fixed Fare</span>
          </div>
          <span class="text-xs font-black uppercase px-3 py-1.5 rounded-xl border ${statusBadgeClass}" id="status-badge-${b.id}">
            ${statusUpper.replace(/_/g, ' ')}
          </span>
        </div>
      </div>

      <div class="bg-slate-950/70 p-3.5 rounded-2xl border border-slate-800/80">
        <div class="grid grid-cols-4 gap-1 text-center">
          <div class="space-y-1">
            <div class="h-1.5 rounded-full ${step >= 1 ? 'bg-amber-400' : 'bg-slate-800'}"></div>
            <span class="text-[10px] font-extrabold ${step >= 1 ? 'text-amber-300' : 'text-slate-500'} block">1. Booked</span>
          </div>
          <div class="space-y-1">
            <div class="h-1.5 rounded-full ${step >= 2 ? 'bg-indigo-400' : 'bg-slate-800'}"></div>
            <span class="text-[10px] font-extrabold ${step >= 2 ? 'text-indigo-300' : 'text-slate-500'} block">2. Dispatched</span>
          </div>
          <div class="space-y-1">
            <div class="h-1.5 rounded-full ${step >= 3 ? 'bg-yellow-400' : 'bg-slate-800'}${step === 3 ? ' animate-pulse' : ''}"></div>
            <span class="text-[10px] font-extrabold ${step >= 3 ? 'text-yellow-300' : 'text-slate-500'} block">3. On Trip</span>
          </div>
          <div class="space-y-1">
            <div class="h-1.5 rounded-full ${step >= 4 ? 'bg-emerald-400' : 'bg-slate-800'}"></div>
            <span class="text-[10px] font-extrabold ${step >= 4 ? 'text-emerald-300' : 'text-slate-500'} block">4. Completed</span>
          </div>
        </div>
      </div>

      <div class="bg-slate-950/70 p-4 rounded-2xl border border-slate-800/80 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
        <div class="space-y-1.5">
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
            <span class="text-slate-400 font-bold">Pickup:</span>
            <span class="text-white font-extrabold">${b.pickup_location}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
            <span class="text-slate-400 font-bold">Destination:</span>
            <span class="text-white font-extrabold">${b.drop_location}</span>
          </div>
        </div>
        <div class="space-y-1 md:text-right">
          <div class="text-slate-300 font-bold">
            ðŸ“… Schedule: <span class="text-amber-300 font-black">${b.pickup_date} at ${b.pickup_time || ''}</span>
          </div>
          ${b.special_notes ? `<p class="text-[11px] text-amber-400 font-semibold italic text-left md:text-right">ðŸ“ ${b.special_notes}</p>` : ''}
        </div>
      </div>

      ${driverCard}

      <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-800/80">
        <div class="flex items-center gap-2">
          ${statusUpper === 'PENDING' ? `<button onclick="handleCancelRide(${b.id})" class="text-xs font-bold text-red-400 hover:text-red-300 hover:bg-red-500/10 px-3.5 py-2 rounded-xl border border-red-500/20 transition flex items-center gap-1"><i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Cancel Ride</button>` : ''}
          ${statusUpper === 'COMPLETED' ? `<a href="./review.php?ref=${encodeURIComponent(b.booking_ref || '')}" class="text-xs font-black text-blue-300 hover:text-blue-200 bg-blue-500/10 hover:bg-blue-500/20 px-3.5 py-2 rounded-xl border border-blue-500/30 transition flex items-center gap-1"><i data-lucide="star" class="w-3.5 h-3.5 text-blue-400"></i> Rate Ride</a>` : ''}
          <a href="./rides.php?id=${b.id}" class="text-xs font-black text-emerald-300 hover:text-emerald-200 bg-emerald-500/10 hover:bg-emerald-500/20 px-3 py-2 rounded-xl border border-emerald-500/30 transition flex items-center gap-1">
            <i data-lucide="external-link" class="w-3.5 h-3.5 text-emerald-400"></i> Open Page
          </a>
        </div>
        ${boostHTML}
      </div>
    </div>
  `;
}

function renderPastRideCardHTML(b) {
  const statusUpper = (b.status || 'COMPLETED').toUpperCase();
  const isCompleted = (statusUpper === 'COMPLETED');
  const currentRating = userRatingsStore[b.id] !== undefined ? userRatingsStore[b.id] : parseInt(b.user_rating || 0);
  const currentReview = (b.user_review || '').replace(/"/g, '&quot;');
  const fare = parseFloat(b.total_fare || 0);
  const driverName = b.driver_name || b.driver_name_full || 'Assigned Driver';

  let starsHTML = '';
  for (let s = 1; s <= 5; s++) {
    const isFilled = (s <= currentRating);
    starsHTML += `
      <button type="button" 
              onclick="handleStarClick(${b.id}, ${s})" 
              onmouseenter="handleStarHover(${b.id}, ${s})"
              onmouseleave="handleStarReset(${b.id})"
              class="star-btn-${b.id} text-2xl focus:outline-none transition-transform hover:scale-125"
              data-star="${s}">
        <span class="star-icon" style="color: ${isFilled ? '#F59E0B' : '#475569'};">
          ${isFilled ? 'â˜…' : 'â˜†'}
        </span>
      </button>
    `;
  }

  let ratingSection = '';
  if (isCompleted) {
    ratingSection = `
      <div class="bg-slate-950/80 p-4 rounded-2xl border border-amber-500/30 space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-black text-amber-300 uppercase">
            â­ Driver Rating & Review (${driverName})
          </span>
          <span id="rating-status-badge-${b.id}" class="text-[11px] font-extrabold ${currentRating > 0 ? 'text-emerald-400' : 'text-slate-400'}">
            ${currentRating > 0 ? `âœ“ ${currentRating} / 5 Stars Saved` : 'Click star to rate:'}
          </span>
        </div>

        <div class="flex items-center gap-2" id="star-group-${b.id}">
          ${starsHTML}
        </div>

        <div class="flex gap-2">
          <input type="text" id="review-input-${b.id}" value="${currentReview}" placeholder="Share driver feedback..." class="flex-grow px-3 py-1.5 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white">
          <button type="button" onclick="handleSaveReviewText(${b.id})" class="gradient-btn-gold text-slate-950 font-black text-xs px-3.5 py-1.5 rounded-xl uppercase shadow">
            Save
          </button>
        </div>
        <div id="rating-msg-${b.id}" class="text-[10px] font-bold"></div>
      </div>
    `;
  }

  return `
    <div class="uber-card p-5 sm:p-6 rounded-3xl border border-slate-800 space-y-4 shadow-xl animate-fadeIn" id="past-ride-${b.id}">
      <div class="flex flex-wrap justify-between items-center gap-2">
        <div>
          <span class="text-sm font-black text-white font-outfit uppercase">#${b.booking_ref || b.id}</span>
          <span class="text-[10px] bg-slate-800 text-slate-300 font-bold px-2 py-0.5 rounded-md ml-1.5 uppercase">${b.cab_type || 'Cab'}</span>
        </div>
        <div class="flex items-center gap-2">
          <button onclick="openReportModal(${b.id})" class="text-[11px] font-black text-amber-300 hover:text-amber-200 bg-amber-500/10 px-2.5 py-1 rounded-lg border border-amber-500/30 flex items-center gap-1">
            <i data-lucide="alert-triangle" class="w-3 h-3 text-amber-400"></i> Report Issue
          </button>
          <span class="text-lg font-black text-amber-400 font-outfit">â‚¹${fare}</span>
          <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-lg border ${isCompleted ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-red-500/20 text-red-300 border-red-500/40'}">
            ${statusUpper.replace(/_/g, ' ')}
          </span>
        </div>
      </div>

      <div class="text-xs text-slate-300 flex flex-wrap justify-between gap-2 bg-slate-950 p-3 rounded-xl border border-slate-800">
        <div>ðŸ“ ${b.pickup_location || ''} âž” ${b.drop_location || ''}</div>
        <div class="text-slate-400">ðŸ“… ${b.pickup_date || ''} at ${(b.pickup_time || '').substring(0, 5)}</div>
      </div>

      ${ratingSection}
    </div>
  `;
}

function renderSpotlightCardHTML(b) {
  const statusUpper = String(b.status || 'PENDING').toUpperCase().trim();
  const isCancelled = (statusUpper.includes('CANCEL') || statusUpper === 'REJECTED');
  const isDone = (statusUpper === 'COMPLETED' || statusUpper === 'FINISHED');
  const isAssigned = (statusUpper === 'CONFIRMED' || statusUpper === 'ASSIGNED' || statusUpper === 'ACCEPTED' || statusUpper === 'DRIVER_ASSIGNED');
  const isInTransit = (statusUpper === 'IN_TRANSIT' || statusUpper === 'ON_TRIP' || statusUpper === 'ARRIVED');
  const isPending = (!isCancelled && !isDone && !isInTransit && !isAssigned);

  if (isCancelled) {
    return `
      <div id="spotlight-card" class="bg-gradient-to-br from-red-500/10 via-slate-900 to-red-500/5 p-6 rounded-3xl border-2 border-red-500/50 space-y-4 shadow-[0_0_40px_rgba(239,68,68,0.2)] animate-fadeIn">
        <div class="flex flex-wrap justify-between items-center gap-3 border-b border-red-500/20 pb-3">
          <div class="flex items-center gap-2">
            <span class="w-3 h-3 rounded-full bg-red-400"></span>
            <h2 class="text-base sm:text-lg font-black text-red-300 font-outfit uppercase tracking-tight">
              ðŸš« RIDE #${b.booking_ref || b.id} WAS CANCELLED
            </h2>
          </div>
          <span class="text-xs bg-red-500/20 text-red-300 font-black px-3 py-1 rounded-xl uppercase border border-red-500/40">
            Cancelled
          </span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
          <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 space-y-2 opacity-80">
            <div class="text-slate-400 font-bold uppercase text-[10px]">Cancelled Route:</div>
            <div class="text-white font-extrabold text-sm">ðŸ“ ${b.pickup_location || ''} âž” ${b.drop_location || ''}</div>
            <div class="text-slate-400">ðŸ“… Scheduled: ${b.pickup_date || ''} at ${(b.pickup_time || '').substring(0, 5)}</div>
            <div class="text-slate-400">ðŸš• Vehicle: ${b.cab_type || 'Cab'}</div>
          </div>

          <div class="bg-slate-950 p-4 rounded-2xl border border-red-500/30 space-y-3 flex flex-col justify-between">
            <div class="space-y-1">
              <div class="text-red-400 font-black text-xs flex items-center gap-1.5">
                <i data-lucide="alert-circle" class="w-4 h-4"></i> Booking Cancelled
              </div>
              <p class="text-[11px] text-slate-400">This booking has been cancelled. No driver is assigned to this trip.</p>
            </div>
            <div>
              <a href="./index.php" class="inline-flex items-center gap-1.5 gradient-btn-gold text-slate-950 font-black text-xs px-4 py-2 rounded-xl uppercase shadow">
                <i data-lucide="plus-circle" class="w-3.5 h-3.5"></i> Book a New Goa Cab
              </a>
            </div>
          </div>
        </div>
      </div>
    `;
  }

  let driverHTML = '';
  if (b.driver_name || b.driver_phone) {
    const driverPhoneClean = cleanDigits(b.driver_phone);
    driverHTML = `
      <div class="space-y-1">
        <div class="text-white font-extrabold flex items-center gap-1.5">
          <span>ðŸ‘¨â€âœˆï¸ ${b.driver_name || 'Assigned Driver'}</span>
          <span class="text-[10px] bg-emerald-500/20 text-emerald-300 border border-emerald-500/40 px-2 py-0.2 rounded-full font-bold uppercase">Dispatched</span>
        </div>
        <div class="text-amber-300 font-mono font-bold text-[11px]">ðŸš– ${b.vehicle_number || 'GA-03-T-1234'}</div>
        <div class="flex gap-2 pt-1">
          <a href="tel:${b.driver_phone}" class="text-[11px] bg-slate-800 text-slate-200 font-bold px-2 py-1 rounded-lg hover:bg-slate-700 flex items-center gap-1">
            <i data-lucide="phone" class="w-3 h-3 text-amber-400"></i> Call
          </a>
          <a href="https://wa.me/${driverPhoneClean}" target="_blank" class="text-[11px] bg-emerald-600 text-slate-950 font-black px-2.5 py-1 rounded-lg hover:bg-emerald-500 flex items-center gap-1">
            <i data-lucide="send" class="w-3 h-3"></i> WhatsApp
          </a>
        </div>
      </div>
    `;
  } else {
    driverHTML = `
      <div class="text-amber-300 font-black flex items-center gap-1.5 text-xs">
        <span class="animate-spin inline-block">â³</span> Dispatching Nearby Goa Driver...
      </div>
      <p class="text-[11px] text-slate-400 pt-1">Finding nearest available cab. You will receive an instant WhatsApp alert.</p>
    `;
  }

  let statusBadgeClass = 'bg-amber-500/20 text-amber-300 border-amber-500/40';
  if (isInTransit) statusBadgeClass = 'bg-yellow-500/20 text-yellow-300 border-yellow-500/40 animate-pulse';
  else if (isAssigned) statusBadgeClass = 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40';
  else if (isDone) statusBadgeClass = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40';

  return `
    <div id="spotlight-card" class="bg-gradient-to-br from-amber-500/10 via-slate-900 to-amber-500/5 p-6 rounded-3xl border-2 border-amber-400 space-y-4 shadow-[0_0_50px_rgba(245,158,11,0.25)] animate-fadeIn">
      <div class="flex flex-wrap justify-between items-center gap-3 border-b border-amber-500/20 pb-3">
        <div class="flex items-center gap-2">
          <span class="w-3 h-3 rounded-full bg-emerald-400 animate-ping"></span>
          <h2 class="text-base sm:text-lg font-black text-amber-300 font-outfit uppercase tracking-tight">
            âœ¨ YOUR BOOKED RIDE #${b.booking_ref || b.id}
          </h2>
        </div>
        <div class="flex items-center gap-2">
          <span class="text-xs font-black uppercase px-2.5 py-1 rounded-xl border ${statusBadgeClass}">
            ${statusUpper.replace(/_/g, ' ')}
          </span>
          <span class="text-[10px] bg-amber-400 text-slate-950 font-black px-2.5 py-1 rounded-xl uppercase shadow">
            Live Radar
          </span>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 space-y-2">
          <div class="text-slate-400 font-bold uppercase text-[10px]">Route Details:</div>
          <div class="text-white font-extrabold text-sm">ðŸ“ ${b.pickup_location || ''} âž” ${b.drop_location || ''}</div>
          <div class="text-slate-300">ðŸ“… Schedule: <strong class="text-amber-300">${b.pickup_date || ''} at ${(b.pickup_time || '').substring(0, 5)}</strong></div>
          <div class="text-slate-300">ðŸš• Vehicle: <strong>${b.cab_type || 'Cab'} AC Cab</strong></div>
        </div>

        <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 space-y-2 flex flex-col justify-between">
          <div>
            <div class="text-slate-400 font-bold uppercase text-[10px]">Driver Status:</div>
            ${driverHTML}
          </div>
          <div class="pt-2 flex justify-between items-center border-t border-slate-800">
            <span class="text-slate-400 font-bold uppercase text-[10px]">Total Fixed Fare:</span>
            <span class="text-2xl font-black text-amber-400 font-outfit">â‚¹${parseFloat(b.total_fare || 0)}</span>
          </div>
        </div>
      </div>
    </div>
  `;
}

function checkRidesNotificationBanner() {
  if ('Notification' in window && Notification.permission === 'default' && window.isLoggedIn) {
    Notification.requestPermission().then(perm => {
      if (perm === 'granted' && typeof window.syncFcmTokenWithUser === 'function') {
        const phone = '<?php echo $phoneClean; ?>' || localStorage.getItem('pavancab_user_phone') || '';
        window.syncFcmTokenWithUser({ mobile: phone });
      }
    });
  }
}

function enableRidesPushNotifications() {}

async function pollCustomerRides() {
  try {
    const urlParams = new URLSearchParams(window.location.search);
    const qPhone = urlParams.get('phone') || '<?php echo $phoneClean; ?>' || (function(){ try{ return localStorage.getItem('pavancab_user_phone'); }catch(e){ return ''; } })() || '';
    const qId = urlParams.get('id') || urlParams.get('booking_id') || '';

    const res = await fetch(`./api_rides.php?action=user-bookings&phone=${encodeURIComponent(qPhone)}&id=${encodeURIComponent(qId)}`);
    const bookings = await res.json();
    if (Array.isArray(bookings)) {
      const activeOnly = bookings.filter(b => ['PENDING', 'CONFIRMED', 'ASSIGNED', 'IN_TRANSIT', 'ON_TRIP', 'ARRIVED'].includes((b.status || '').toUpperCase()));
      const pastOnly   = bookings.filter(b => !['PENDING', 'CONFIRMED', 'ASSIGNED', 'IN_TRANSIT', 'ON_TRIP', 'ARRIVED'].includes((b.status || '').toUpperCase()));

      const currentHash = JSON.stringify(bookings.map(x => ({ id: x.id, s: x.status, f: x.total_fare, d: x.driver_id, dp: x.driver_phone, dn: x.driver_name, vn: x.vehicle_number, n: x.special_notes, r: x.user_rating, rv: x.user_review })));
      
      if (!lastKnownActiveHash || lastKnownActiveHash !== currentHash) {
        const isFirstLoad = (lastKnownActiveHash === '');
        lastKnownActiveHash = currentHash;

        if (!isFirstLoad) {
          // Play notification chime on live ride status change
          try {
            const audioCtx = new (window.AudioContext || window.webkitAudioContext)();
            const osc = audioCtx.createOscillator();
            const gain = audioCtx.createGain();
            osc.type = 'sine';
            osc.frequency.setValueAtTime(587.33, audioCtx.currentTime); // D5
            osc.frequency.setValueAtTime(880, audioCtx.currentTime + 0.1); // A5
            gain.gain.setValueAtTime(0.15, audioCtx.currentTime);
            gain.gain.exponentialRampToValueAtTime(0.01, audioCtx.currentTime + 0.35);
            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start();
            osc.stop(audioCtx.currentTime + 0.35);
          } catch(e){}

          showInAppAlert('âš¡ Live Ride Update', 'Your trip radar received fresh updates from dispatch!');
        }

        // 1. Live update spotlight card if target ID or spotlight container exists
        if (qId) {
          const spotlightMatch = bookings.find(b => parseInt(b.id) === parseInt(qId) || b.booking_ref === qId);
          const spotContainer = document.getElementById('spotlight-container');
          if (spotContainer && spotlightMatch) {
            spotContainer.innerHTML = renderSpotlightCardHTML(spotlightMatch);
          }
        }

        // 2. Live update active rides container
        const container = document.getElementById('active-rides-feed-container');
        const countEl = document.getElementById('active-rides-count');
        if (countEl) countEl.innerText = activeOnly.length;

        if (container) {
          if (activeOnly.length === 0) {
            container.innerHTML = `
              <div class="uber-card p-10 rounded-3xl border border-slate-800 text-center space-y-4 shadow-xl">
                <h3 class="text-base font-black text-white uppercase font-outfit">No Active Rides in Progress</h3>
                <p class="text-xs text-slate-400">All trips completed or cancelled.</p>
              </div>
            `;
          } else {
            container.innerHTML = activeOnly.map(renderActiveRideCardHTML).join('');
          }
        }

        // 3. Live update past rides container
        const pastContainer = document.getElementById('past-rides-container');
        const pastCountEl = document.getElementById('past-rides-count');
        if (pastCountEl) pastCountEl.innerText = pastOnly.length;

        if (pastContainer) {
          if (pastOnly.length === 0) {
            pastContainer.innerHTML = `<div id="past-rides-empty" class="text-xs text-slate-500 text-center py-4">No completed or cancelled trips in history.</div>`;
          } else {
            pastContainer.innerHTML = pastOnly.map(renderPastRideCardHTML).join('');
          }
        }

        if (typeof lucide !== 'undefined') lucide.createIcons();
      }
    }
  } catch (e) {}
}

document.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const targetId = urlParams.get('id') || urlParams.get('booking_id');
  if (targetId) {
    const el = document.getElementById('ride-card-' + targetId);
    if (el) {
      setTimeout(() => {
        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        el.classList.add('ring-2', 'ring-amber-400');
      }, 300);
    }
  }
  
  checkRidesNotificationBanner();

  // Automatically sync browser FCM push token for this passenger
  const activePhone = '<?php echo $phoneClean; ?>' || (function(){ try{ return localStorage.getItem('pavancab_user_phone'); }catch(e){ return ''; } })() || '';
  const activeEmail = '<?php echo $userEmail; ?>' || (function(){ try{ return localStorage.getItem('pavancab_user_email'); }catch(e){ return ''; } })() || '';
  if (activePhone) {
    try { localStorage.setItem('pavancab_user_phone', activePhone); } catch(e){}
    if (activeEmail) {
      try { localStorage.setItem('pavancab_user_email', activeEmail); } catch(e){}
    }
    
    const trySync = () => {
      if (typeof window.syncFcmTokenWithUser === 'function') {
        window.syncFcmTokenWithUser({ mobile: activePhone, email: activeEmail });
      }
    };
    trySync();
    setTimeout(trySync, 1000);
    setTimeout(trySync, 3000);
  }

  pollCustomerRides();
  setInterval(pollCustomerRides, 2500);
});
</script>

<?php include __DIR__ . '/footer.php'; ?>
