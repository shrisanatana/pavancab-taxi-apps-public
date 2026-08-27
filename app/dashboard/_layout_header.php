<?php
/**
 * PAVANCAB GOA TAXI - Dispatch Tower Layout Header
 * Path: app/dashboard/_layout_header.php
 */

require_once __DIR__ . '/../db.php';

// Auth Guard: Admin & Team Members only
$user = $_SESSION['user'] ?? null;
$allowedRoles = ['admin', 'team'];

if (!$user || !in_array($user['role'] ?? '', $allowedRoles)) {
    header('Location: ../auth.php?redirect=dashboard/' . urlencode(basename($_SERVER['PHP_SELF'] ?? 'index.php')));
    exit;
}

$isSuperAdmin = (($user['role'] ?? '') === 'admin') || (preg_replace('/\D/', '', $user['mobile'] ?? $user['phone'] ?? '') === '8199000000');
$userName = $user['name'] ?? ($isSuperAdmin ? 'Super Admin' : 'Dispatcher');

// Load Data
$conn = db();
$bookings = [];
$drivers  = [];

$resB = $conn->query("
    SELECT b.*, 
           COALESCE(NULLIF(b.driver_name, ''), d.name) as driver_name, 
           COALESCE(NULLIF(b.driver_phone, ''), d.phone) as driver_phone, 
           COALESCE(NULLIF(b.vehicle_number, ''), d.plate_number, 'GA-03-T-1234') as vehicle_number,
           d.name as driver_name_full, d.phone as driver_phone_full, d.car_model, d.plate_number 
    FROM app_bookings b 
    LEFT JOIN app_drivers d ON b.driver_id = d.id 
    ORDER BY b.id DESC
");
if ($resB) {
    while ($row = $resB->fetch_assoc()) {
        $bookings[] = $row;
    }
}

$resD = $conn->query("SELECT * FROM app_drivers ORDER BY name ASC");
if ($resD) {
    while ($row = $resD->fetch_assoc()) {
        $drivers[] = $row;
    }
}

if (!function_exists('classifyRideStatus')) {
    function classifyRideStatus($status) {
        $s = strtoupper(trim((string)$status));
        if (strpos($s, 'CANCEL') !== false || $s === 'REJECTED') return 'CANCELLED';
        if ($s === 'COMPLETED' || $s === 'FINISHED') return 'COMPLETED';
        if ($s === 'IN_TRANSIT' || $s === 'ON_TRIP' || $s === 'ARRIVED') return 'IN_TRANSIT';
        if ($s === 'CONFIRMED' || $s === 'ASSIGNED' || $s === 'ACCEPTED' || $s === 'DRIVER_ASSIGNED') return 'CONFIRMED';
        return 'PENDING';
    }
}

// Unified Counts & Stats Computation
$statTotal     = count($bookings);
$statPending   = 0;
$statAssigned  = 0;
$statInTransit = 0;
$statCompleted = 0;
$statCancelled = 0;
$totalRevenue  = 0.0;

foreach ($bookings as $b) {
    $uKey = classifyRideStatus($b['status'] ?? 'PENDING');
    if ($uKey === 'CANCELLED') {
        $statCancelled++;
    } elseif ($uKey === 'COMPLETED') {
        $statCompleted++;
        $totalRevenue += floatval($b['total_fare'] ?? 0);
    } elseif ($uKey === 'IN_TRANSIT') {
        $statInTransit++;
        $totalRevenue += floatval($b['total_fare'] ?? 0);
    } elseif ($uKey === 'CONFIRMED') {
        $statAssigned++;
        $totalRevenue += floatval($b['total_fare'] ?? 0);
    } else {
        $statPending++;
    }
}

$currentScript = basename($_SERVER['PHP_SELF'] ?? 'index.php');
if (!isset($activeTab)) {
    if ($currentScript === 'pending.php') $activeTab = 'PENDING';
    elseif ($currentScript === 'assigned.php') $activeTab = 'CONFIRMED';
    elseif ($currentScript === 'intransit.php') $activeTab = 'IN_TRANSIT';
    elseif ($currentScript === 'completed.php') $activeTab = 'COMPLETED';
    elseif ($currentScript === 'cancelled.php') $activeTab = 'CANCELLED';
    elseif ($currentScript === 'fleet.php') $activeTab = 'FLEET';
    elseif ($currentScript === 'reports.php') $activeTab = 'REPORTS';
    elseif ($currentScript === 'team.php') $activeTab = 'TEAM';
    elseif ($currentScript === 'users.php' || $currentScript === 'user_detail.php') $activeTab = 'USERS';
    else $activeTab = 'ALL';
}

if (!headers_sent()) {
    header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private');
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
    header('X-Accel-Cache-Control: no-store');
    header('X-LiteSpeed-Cache-Control: no-cache, private, no-store');
}

$pageTitle = $pageTitle ?? 'PAVANCAB Dispatch Tower';
$cacheBust = time();
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($pageTitle); ?> â€¢ PAVANCAB Goa</title>
  
  <!-- Tailwind CSS & Lucide Icons -->
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://unpkg.com/lucide@latest"></script>
  
  <!-- Fonts: Outfit & Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&family=Outfit:wght@500;600;700;800;900&display=swap" rel="stylesheet">
  
  <style>
    body { font-family: 'Inter', sans-serif; }
    .font-outfit { font-family: 'Outfit', sans-serif; }
    .uber-card {
      background: linear-gradient(145deg, rgba(15, 23, 42, 0.85), rgba(2, 6, 23, 0.95));
      backdrop-filter: blur(12px);
    }
    .gradient-btn-gold {
      background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%);
      transition: all 0.2s ease;
    }
    .gradient-btn-gold:hover {
      box-shadow: 0 0 25px rgba(245, 158, 11, 0.4);
      transform: translateY(-1px);
    }
    .custom-scrollbar::-webkit-scrollbar {
      height: 6px;
      width: 6px;
    }
    .custom-scrollbar::-webkit-scrollbar-track {
      background: #0f172a;
    }
    .custom-scrollbar::-webkit-scrollbar-thumb {
      background: #1e293b;
      border-radius: 9999px;
    }
  </style>
</head>
<body class="min-h-screen bg-slate-950 text-slate-100 flex flex-col antialiased selection:bg-amber-400 selection:text-slate-950">

  <!-- TOP COMMAND APP BAR -->
  <header class="sticky top-0 z-40 bg-slate-950/90 backdrop-blur-xl border-b border-slate-800 shadow-2xl">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3.5 flex flex-wrap items-center justify-between gap-4">
      
      <!-- BRAND & LIVE PULSE -->
      <div class="flex items-center gap-3">
        <a href="./index.php" class="flex items-center gap-2.5 group">
          <div class="w-10 h-10 rounded-2xl bg-gradient-to-tr from-amber-500 to-amber-300 flex items-center justify-center text-slate-950 font-black text-xl shadow-lg shadow-amber-500/25 group-hover:scale-105 transition-transform">
            ðŸš–
          </div>
          <div>
            <div class="flex items-center gap-1.5">
              <span class="font-extrabold text-white text-lg tracking-tight font-outfit">PAVANCAB</span>
              <span class="text-[10px] bg-amber-400/20 text-amber-300 border border-amber-400/40 px-1.5 py-0.5 rounded font-black tracking-wider uppercase">TOWER</span>
            </div>
            <p class="text-[10px] text-slate-400 font-semibold tracking-wide flex items-center gap-1">
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span> Live Goa Dispatch Center
            </p>
          </div>
        </a>
      </div>

      <!-- DESK SWITCHER & QUICK ACTIONS -->
      <div class="flex items-center gap-2 flex-wrap">
        <!-- New Phone Booking Button -->
        <button onclick="openManualBookingModal()" class="gradient-btn-gold text-slate-950 font-black text-xs px-3.5 py-2 rounded-xl uppercase flex items-center gap-1.5 shadow-md">
          <i data-lucide="phone-call" class="w-3.5 h-3.5"></i>
          <span>+ Phone Booking</span>
        </button>

        <!-- Fleet Driver Manager -->
        <a href="./fleet.php" class="bg-slate-900 hover:bg-slate-800 border <?php echo $activeTab === 'FLEET' ? 'border-amber-400 text-amber-300' : 'border-slate-700 text-slate-300'; ?> text-xs font-bold px-3 py-2 rounded-xl transition flex items-center gap-1.5 shadow">
          <i data-lucide="users" class="w-3.5 h-3.5 text-amber-400"></i>
          <span class="hidden sm:inline">Fleet</span>
        </a>

        <!-- App Users Directory & Live App Radar -->
        <a href="./users.php" class="bg-slate-900 hover:bg-slate-800 border <?php echo $activeTab === 'USERS' ? 'border-amber-400 text-amber-300' : 'border-slate-700 text-slate-300'; ?> text-xs font-bold px-3 py-2 rounded-xl transition flex items-center gap-1.5 shadow" title="App Users & Live Open/Close Tracker">
          <i data-lucide="users-round" class="w-3.5 h-3.5 text-amber-400"></i>
          <span class="hidden sm:inline">Users</span>
        </a>

        <!-- Ride Reports Desk -->
        <a href="./reports.php" class="bg-slate-900 hover:bg-slate-800 border <?php echo $activeTab === 'REPORTS' ? 'border-amber-400 text-amber-300' : 'border-slate-700 text-slate-300'; ?> text-xs font-bold px-3 py-2 rounded-xl transition flex items-center gap-1.5 shadow">
          <i data-lucide="shield-alert" class="w-3.5 h-3.5 text-amber-400"></i>
          <span class="hidden sm:inline">Reports Desk</span>
        </a>

        <?php if ($isSuperAdmin): ?>
          <!-- WhatsApp API Settings -->
          <button onclick="openWaConfigModal()" class="bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold px-3 py-2 rounded-xl transition flex items-center gap-1.5 shadow" title="Meta WhatsApp API Credentials">
            <i data-lucide="message-square-code" class="w-3.5 h-3.5 text-emerald-400"></i>
            <span class="hidden md:inline">WhatsApp API</span>
          </button>

          <!-- Firebase FCM Push Settings & Live Test -->
          <button onclick="openFcmConfigModal()" class="bg-slate-900 hover:bg-slate-800 border border-amber-400/40 text-amber-300 hover:text-white text-xs font-bold px-3 py-2 rounded-xl transition flex items-center gap-1.5 shadow" title="Firebase Cloud Messaging (FCM HTTP v1)">
            <i data-lucide="bell-ring" class="w-3.5 h-3.5 text-amber-400 animate-pulse"></i>
            <span class="hidden md:inline">FCM Push</span>
          </button>

          <!-- Team Dispatchers Manager -->
          <a href="./team.php" class="bg-slate-900 hover:bg-slate-800 border <?php echo $activeTab === 'TEAM' ? 'border-amber-400 text-amber-300' : 'border-slate-700 text-slate-300'; ?> text-xs font-bold px-3 py-2 rounded-xl transition flex items-center gap-1.5 shadow" title="Manage Dispatch Staff">
            <i data-lucide="shield-check" class="w-3.5 h-3.5 text-indigo-400"></i>
            <span class="hidden md:inline">Team</span>
          </a>
        <?php endif; ?>

        <!-- CSV Export -->
        <button onclick="exportBookingsToCSV()" class="bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white text-xs font-bold px-2.5 py-2 rounded-xl transition" title="Export CSV">
          <i data-lucide="download" class="w-3.5 h-3.5"></i>
        </button>

        <!-- Live Sync Trigger -->
        <button onclick="triggerManualSync()" class="bg-slate-900 hover:bg-slate-800 border border-slate-700 text-amber-400 text-xs font-bold px-2.5 py-2 rounded-xl transition" title="Live Refresh Sync">
          <i data-lucide="refresh-cw" class="w-3.5 h-3.5"></i>
        </button>

        <!-- User Logout -->
        <a href="../auth.php?action=logout" class="bg-red-500/10 hover:bg-red-500/20 text-red-400 border border-red-500/30 text-xs font-black px-3 py-2 rounded-xl transition flex items-center gap-1.5 shadow" title="Log Out">
          <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
          <span class="hidden sm:inline">Logout</span>
        </a>
      </div>
    </div>
  </header>

  <!-- LIVE STATUS NOTIFICATION BANNER -->
  <div id="live-alert-banner" class="hidden bg-amber-400 text-slate-950 text-xs font-black py-2 px-4 text-center sticky top-[61px] z-30 shadow-lg animate-pulse flex items-center justify-center gap-2">
    <span>âš¡ Real-time Dispatch Update: New bookings / driver status synchronized with DB!</span>
  </div>

  <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 flex-grow space-y-6 w-full">

    <!-- USER & METRICS STRIP -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-xl sm:text-2xl font-black text-white font-outfit uppercase tracking-tight flex items-center gap-2">
          <span id="desk-page-title"><?php echo htmlspecialchars($pageTitle); ?></span>
          <span class="text-xs font-bold px-2.5 py-0.5 rounded-full border <?php echo $isSuperAdmin ? 'bg-amber-400/20 text-amber-300 border-amber-400/40' : 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40'; ?>">
            <?php echo $isSuperAdmin ? 'Super Admin' : 'Team Dispatcher'; ?>
          </span>
        </h1>
        <p class="text-xs text-slate-400 mt-0.5">
          Logged in as <strong class="text-white"><?php echo htmlspecialchars($userName); ?></strong> â€¢ Full dispatch control & WhatsApp notification broadcast
        </p>
      </div>

      <!-- SEARCH BOX -->
      <div class="w-full sm:w-72 relative">
        <input type="text" id="booking-search-input" onkeyup="searchBookings(this.value)" placeholder="Search Ref, Phone, Passenger, Location..." class="w-full bg-slate-900 border border-slate-800 rounded-xl pl-9 pr-4 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-400">
        <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
      </div>
    </div>

    <!-- DYNAMIC STATS COUNTER TILES (live filter â€” no page refresh) -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
      <button type="button" data-desk-filter="ALL" onclick="return switchRideDesk(event, 'ALL')" class="desk-stat-tile uber-card p-4 rounded-2xl border text-left w-full <?php echo $activeTab === 'ALL' ? 'border-amber-400 bg-slate-900/90 shadow-amber-500/10 shadow-lg' : 'border-slate-800'; ?> transition block group">
        <span class="text-[10px] uppercase font-black tracking-wider text-slate-400 block group-hover:text-amber-400 transition">All Rides</span>
        <span class="text-2xl font-black text-white font-outfit mt-1 block" id="stat-total"><?php echo $statTotal; ?></span>
      </button>

      <button type="button" data-desk-filter="PENDING" onclick="return switchRideDesk(event, 'PENDING')" class="desk-stat-tile uber-card p-4 rounded-2xl border text-left w-full relative overflow-hidden <?php echo $activeTab === 'PENDING' ? 'border-red-400 bg-red-950/20 shadow-red-500/10 shadow-lg' : 'border-slate-800'; ?> transition block group">
        <?php if ($statPending > 0): ?>
          <span class="absolute top-2 right-2 flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
          </span>
        <?php endif; ?>
        <span class="text-[10px] uppercase font-black tracking-wider text-red-400 block">Needs Driver</span>
        <span class="text-2xl font-black text-red-400 font-outfit mt-1 block" id="stat-pending"><?php echo $statPending; ?></span>
      </button>

      <button type="button" data-desk-filter="CONFIRMED" onclick="return switchRideDesk(event, 'CONFIRMED')" class="desk-stat-tile uber-card p-4 rounded-2xl border text-left w-full <?php echo $activeTab === 'CONFIRMED' ? 'border-indigo-400 bg-indigo-950/20 shadow-indigo-500/10 shadow-lg' : 'border-slate-800'; ?> transition block group">
        <span class="text-[10px] uppercase font-black tracking-wider text-indigo-400 block">Assigned Cabs</span>
        <span class="text-2xl font-black text-indigo-400 font-outfit mt-1 block" id="stat-assigned"><?php echo $statAssigned; ?></span>
      </button>

      <button type="button" data-desk-filter="IN_TRANSIT" onclick="return switchRideDesk(event, 'IN_TRANSIT')" class="desk-stat-tile uber-card p-4 rounded-2xl border text-left w-full <?php echo $activeTab === 'IN_TRANSIT' ? 'border-yellow-400 bg-yellow-950/20 shadow-yellow-500/10 shadow-lg' : 'border-slate-800'; ?> transition block group">
        <span class="text-[10px] uppercase font-black tracking-wider text-yellow-400 block">On Trip</span>
        <span class="text-2xl font-black text-yellow-400 font-outfit mt-1 block" id="stat-intransit"><?php echo $statInTransit; ?></span>
      </button>

      <button type="button" data-desk-filter="COMPLETED" onclick="return switchRideDesk(event, 'COMPLETED')" class="desk-stat-tile uber-card p-4 rounded-2xl border text-left w-full <?php echo $activeTab === 'COMPLETED' ? 'border-emerald-400 bg-emerald-950/20 shadow-emerald-500/10 shadow-lg' : 'border-slate-800'; ?> transition block group">
        <span class="text-[10px] uppercase font-black tracking-wider text-emerald-400 block">Completed</span>
        <span class="text-2xl font-black text-emerald-400 font-outfit mt-1 block" id="stat-completed"><?php echo $statCompleted; ?></span>
      </button>

      <button type="button" data-desk-filter="CANCELLED" onclick="return switchRideDesk(event, 'CANCELLED')" class="desk-stat-tile uber-card p-4 rounded-2xl border text-left w-full <?php echo $activeTab === 'CANCELLED' ? 'border-slate-500 bg-slate-900/90' : 'border-slate-800'; ?> transition block group">
        <span class="text-[10px] uppercase font-black tracking-wider text-slate-400 block">Cancelled</span>
        <span class="text-2xl font-black text-slate-300 font-outfit mt-1 block" id="stat-cancelled"><?php echo $statCancelled; ?></span>
      </button>
    </div>

    <!-- DEDICATED DESK NAVIGATION TABS -->
    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 custom-scrollbar border-b border-slate-800">
      <button type="button" data-desk-filter="ALL" onclick="return switchRideDesk(event, 'ALL')" class="desk-nav-tab px-3.5 py-2 rounded-xl font-black text-xs uppercase transition whitespace-nowrap flex items-center gap-1.5 <?php echo $activeTab === 'ALL' ? 'bg-amber-400 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-900'; ?>">
        <i data-lucide="layers" class="w-3.5 h-3.5"></i> All Rides (<span id="count-ALL"><?php echo $statTotal; ?></span>)
      </button>

      <button type="button" data-desk-filter="PENDING" onclick="return switchRideDesk(event, 'PENDING')" class="desk-nav-tab px-3.5 py-2 rounded-xl font-black text-xs uppercase transition whitespace-nowrap flex items-center gap-1.5 <?php echo $activeTab === 'PENDING' ? 'bg-red-500 text-white shadow-md' : 'text-red-400 hover:text-white hover:bg-slate-900'; ?>">
        <i data-lucide="alert-circle" class="w-3.5 h-3.5"></i> Needs Driver (<span id="count-PENDING"><?php echo $statPending; ?></span>)
      </button>

      <button type="button" data-desk-filter="CONFIRMED" onclick="return switchRideDesk(event, 'CONFIRMED')" class="desk-nav-tab px-3.5 py-2 rounded-xl font-black text-xs uppercase transition whitespace-nowrap flex items-center gap-1.5 <?php echo $activeTab === 'CONFIRMED' ? 'bg-indigo-600 text-white shadow-md' : 'text-indigo-400 hover:text-white hover:bg-slate-900'; ?>">
        <i data-lucide="car" class="w-3.5 h-3.5"></i> Assigned (<span id="count-CONFIRMED"><?php echo $statAssigned; ?></span>)
      </button>

      <button type="button" data-desk-filter="IN_TRANSIT" onclick="return switchRideDesk(event, 'IN_TRANSIT')" class="desk-nav-tab px-3.5 py-2 rounded-xl font-black text-xs uppercase transition whitespace-nowrap flex items-center gap-1.5 <?php echo $activeTab === 'IN_TRANSIT' ? 'bg-yellow-500 text-slate-950 shadow-md' : 'text-yellow-400 hover:text-white hover:bg-slate-900'; ?>">
        <i data-lucide="navigation" class="w-3.5 h-3.5"></i> On Trip (<span id="count-IN_TRANSIT"><?php echo $statInTransit; ?></span>)
      </button>

      <button type="button" data-desk-filter="COMPLETED" onclick="return switchRideDesk(event, 'COMPLETED')" class="desk-nav-tab px-3.5 py-2 rounded-xl font-black text-xs uppercase transition whitespace-nowrap flex items-center gap-1.5 <?php echo $activeTab === 'COMPLETED' ? 'bg-emerald-600 text-white shadow-md' : 'text-emerald-400 hover:text-white hover:bg-slate-900'; ?>">
        <i data-lucide="check-circle-2" class="w-3.5 h-3.5"></i> Completed (<span id="count-COMPLETED"><?php echo $statCompleted; ?></span>)
      </button>

      <button type="button" data-desk-filter="CANCELLED" onclick="return switchRideDesk(event, 'CANCELLED')" class="desk-nav-tab px-3.5 py-2 rounded-xl font-black text-xs uppercase transition whitespace-nowrap flex items-center gap-1.5 <?php echo $activeTab === 'CANCELLED' ? 'bg-slate-700 text-white shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-900'; ?>">
        <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Cancelled (<span id="count-CANCELLED"><?php echo $statCancelled; ?></span>)
      </button>

      <a href="./fleet.php" class="px-3.5 py-2 rounded-xl font-black text-xs uppercase transition whitespace-nowrap flex items-center gap-1.5 <?php echo $activeTab === 'FLEET' ? 'bg-amber-400 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-900'; ?>">
        <i data-lucide="user-check" class="w-3.5 h-3.5"></i> Drivers Fleet (<span id="count-FLEET"><?php echo count($drivers); ?></span>)
      </a>

      <a href="./users.php" class="px-3.5 py-2 rounded-xl font-black text-xs uppercase transition whitespace-nowrap flex items-center gap-1.5 <?php echo $activeTab === 'USERS' ? 'bg-amber-400 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-900'; ?>" title="Users Directory & Push Notifications">
        <i data-lucide="users-round" class="w-3.5 h-3.5"></i> Users (<span id="count-USERS">Live</span>)
      </a>

      <a href="./reports.php" class="px-3.5 py-2 rounded-xl font-black text-xs uppercase transition whitespace-nowrap flex items-center gap-1.5 <?php echo $activeTab === 'REPORTS' ? 'bg-amber-400 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-900'; ?>">
        <i data-lucide="message-circle-warning" class="w-3.5 h-3.5"></i> Reports Desk
      </a>

      <?php if ($isSuperAdmin): ?>
        <a href="./team.php" class="px-3.5 py-2 rounded-xl font-black text-xs uppercase transition whitespace-nowrap flex items-center gap-1.5 <?php echo $activeTab === 'TEAM' ? 'bg-amber-400 text-slate-950 shadow-md' : 'text-slate-400 hover:text-white hover:bg-slate-900'; ?>">
          <i data-lucide="shield" class="w-3.5 h-3.5"></i> Team Staff
        </a>
      <?php endif; ?>
    </div>
