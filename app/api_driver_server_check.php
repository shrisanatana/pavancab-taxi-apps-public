<!DOCTYPE html>
<html lang="en" class="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>PAVANCAB - Premium Goa Taxi & Airport Dispatch</title>
  <link rel="shortcut icon" href="./logo-pavancab.png" type="image/png">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <style>
    html {
      font-size: 13.5px;
      -webkit-text-size-adjust: 100%;
    }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background-color: #070a12;
      color: #f8fafc;
    }
    .font-outfit { font-family: 'Outfit', sans-serif; }
    .uber-card {
      background: rgba(13, 19, 33, 0.85);
      backdrop-filter: blur(24px);
      -webkit-backdrop-filter: blur(24px);
      border: 1px solid rgba(255, 255, 255, 0.08);
    }
    .uber-card-hover {
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .uber-card-hover:hover {
      transform: translateY(-2px);
      border-color: rgba(245, 158, 11, 0.4);
      box-shadow: 0 12px 30px -10px rgba(245, 158, 11, 0.2);
    }
    .uber-btn {
      background: #ffffff;
      color: #070a12;
      font-weight: 800;
      transition: all 0.2s ease;
    }
    .uber-btn:hover {
      background: #f1f5f9;
      transform: translateY(-1px);
      box-shadow: 0 10px 25px -5px rgba(255, 255, 255, 0.25);
    }
    .gradient-btn-gold {
      background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 50%, #d97706 100%);
      box-shadow: 0 4px 20px rgba(245, 158, 11, 0.35);
      color: #070a12;
      transition: all 0.2s ease;
    }
    .gradient-btn-gold:hover {
      filter: brightness(1.08);
      transform: translateY(-1px);
      box-shadow: 0 8px 25px rgba(245, 158, 11, 0.45);
    }
    .badge-admin {
      background: linear-gradient(135deg, rgba(245,158,11,0.2) 0%, rgba(217,119,6,0.3) 100%);
      border: 1px solid rgba(245,158,11,0.5);
      color: #fbbf24;
    }
    .badge-team {
      background: linear-gradient(135deg, rgba(99,102,241,0.2) 0%, rgba(79,70,229,0.3) 100%);
      border: 1px solid rgba(99,102,241,0.5);
      color: #a5b4fc;
    }
    .radar-pulse {
      box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.7);
      animation: pulse-radar 1.6s infinite cubic-bezier(0.66, 0, 0, 1);
    }
    @keyframes pulse-radar {
      to { box-shadow: 0 0 0 20px rgba(245, 158, 11, 0); }
    }
    .pavan-wind-glow {
      background: radial-gradient(circle at 50% 0%, rgba(56, 189, 248, 0.15), transparent 70%);
    }
  </style>
  <script>
    window.currentUser = null;
    window.isUserLoggedIn = false;
    window.currentUserPhone = "";
    window.currentUserName = "";
  </script>
</head>
<body class="min-h-screen flex flex-col bg-[#070a12] text-slate-100 antialiased selection:bg-amber-400 selection:text-slate-950 pavan-wind-glow">

  <!-- TOP BRAND & NAVIGATION BAR -->
  <header class="sticky top-0 z-40 uber-card border-b border-slate-800/80 px-3 sm:px-6 py-3 shadow-2xl backdrop-blur-xl">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-2">
      <a href="./index.html" class="flex items-center gap-2.5 group">
        <div class="w-10 h-10 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center p-1 shadow-lg transition group-hover:scale-105">
          <img src="./logo-pavancab.png" alt="PAVANCAB Logo" class="w-full h-full object-contain" onerror="this.onerror=null; this.src='https://pavancab.com/logo-pavancab.png';">
        </div>
        <div>
          <span class="text-lg sm:text-xl font-black text-white tracking-wider font-outfit uppercase flex items-center gap-1">
            PAVAN<span class="bg-gradient-to-r from-amber-400 via-amber-300 to-sky-400 bg-clip-text text-transparent">CAB</span>
            <span class="hidden sm:inline-block text-[9px] bg-sky-500/20 text-sky-300 border border-sky-400/30 px-1.5 py-0.5 rounded font-black tracking-normal">&#x1F4A8; WIND SPEED</span>
          </span>
          <span class="block text-[9px] sm:text-[10px] font-bold text-amber-400/90 tracking-widest uppercase -mt-0.5">SHIELD OF SAFETY &#x2022; GOA</span>
        </div>
      </a>

      <nav class="hidden md:flex items-center gap-2 bg-slate-900/90 p-1.5 rounded-2xl border border-slate-800">
        <a href="./index.html" class="px-5 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 uppercase tracking-wider bg-amber-400 text-slate-950 shadow-md font-extrabold">
          <i data-lucide="navigation" class="w-4 h-4"></i> Request Ride
        </a>
        <a href="./rides.php" class="px-5 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 uppercase tracking-wider text-slate-300 hover:text-white hover:bg-slate-800/70">
          <i data-lucide="clock" class="w-4 h-4"></i> My Rides & Radar
        </a>
        <a href="./dashboard/index.php" id="nav-dispatch" class="hidden px-5 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 uppercase tracking-wider text-indigo-300 hover:text-white hover:bg-indigo-950/40 border border-indigo-500/30">
          <i data-lucide="shield" class="w-4 h-4 text-amber-400"></i> Dispatch Tower
        </a>
      </nav>

      <div class="flex items-center gap-2 sm:gap-3">
        <div id="header-user-area">
          <button id="header-login-btn" onclick="openAuthModal()" class="gradient-btn-gold text-slate-950 font-black text-xs px-3 sm:px-4 py-2 rounded-xl shadow-lg flex items-center gap-1.5 uppercase">
            <i data-lucide="message-square" class="w-4 h-4"></i> Login
          </button>
        </div>
      </div>
    </div>
  </header>

  <main class="flex-grow max-w-7xl mx-auto w-full px-4 lg:px-8 py-6 sm:py-10">

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

      <!-- 4-STEP PROGRESS INDICATOR -->
      <div class="uber-card p-1.5 rounded-xl border border-slate-800 flex items-center justify-between text-[9.5px] font-black uppercase tracking-tight shadow-md">
        <div id="badge-step-1" onclick="goToStep(1)" class="flex-1 text-center py-1.5 px-1 rounded-lg bg-amber-400 text-slate-950 font-extrabold shadow-sm transition cursor-pointer">
          1. Route & Service
        </div>
        <div class="px-0.5 text-slate-600 text-[8px]">&#x27A4;</div>
        <div id="badge-step-2" onclick="goToStep(2)" class="flex-1 text-center py-1.5 px-1 rounded-lg text-slate-400 transition cursor-pointer">
          2. Schedule Time
        </div>
        <div class="px-0.5 text-slate-600 text-[8px]">&#x27A4;</div>
        <div id="badge-step-3" onclick="goToStep(3)" class="flex-1 text-center py-1.5 px-1 rounded-lg text-slate-400 transition cursor-pointer">
          3. Choose Cab
        </div>
        <div class="px-0.5 text-slate-600 text-[8px]">&#x27A4;</div>
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

          <!-- STEP 1: SERVICE TYPE & ROUTE SELECTION -->
          <div id="step-1" class="space-y-5 animate-fadeIn">
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

            <!-- ONE-WAY MODE -->
            <div id="mode-one_way-section" class="space-y-4">
              <div>
                <label class="block text-xs font-black text-slate-300 uppercase mb-1.5 flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> Pickup Location / Airport
                </label>
                <select id="oneway_pickup" onchange="fetchDropLocations(this.value)" class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
                  <option value="">-- Select Pickup Location --</option>
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

            <!-- HOURLY MODE -->
            <div id="mode-hourly-section" class="hidden space-y-4">
              <div>
                <label class="block text-xs font-black text-slate-300 uppercase mb-1.5 flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> Starting Pickup Area / Hotel Location
                </label>
                <select id="hourly_pickup" onchange="fetchHourlyFares(this.value)" class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
                  <option value="">-- Select Pickup Area --</option>
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

            <!-- TOURS MODE -->
            <div id="mode-tour-section" class="hidden space-y-4">
              <div>
                <label class="block text-xs font-black text-slate-300 uppercase mb-1.5 flex items-center gap-1.5">
                  <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span> Hotel / Resort Pickup Location
                </label>
                <select id="tour_pickup" onchange="fetchTourFares(this.value)" class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
                  <option value="">-- Select Pickup Hotel Area --</option>
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
                Next: Schedule Time &#x27A4;
              </button>
            </div>
          </div>

          <!-- STEP 2: PICKUP DATE & TIME SCHEDULE -->
          <div id="step-2" class="hidden space-y-5 animate-fadeIn">
            <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 text-xs flex justify-between items-center">
              <div>
                <span class="text-[10px] text-slate-500 uppercase block font-bold">Selected Service & Route</span>
                <span class="text-amber-300 font-extrabold text-sm" id="summary-route-step2"></span>
              </div>
              <button type="button" onclick="goToStep(1)" class="text-xs text-amber-400 hover:underline font-bold">Change Route</button>
            </div>

            <div>
              <label class="block text-xs font-black text-slate-300 uppercase mb-2">When do you need the cab?</label>
              <div class="grid grid-cols-2 gap-3 mb-4">
                <button type="button" onclick="setScheduleOption('now')" id="btn-now" class="p-3 rounded-xl border font-bold text-xs uppercase transition border-amber-400 bg-amber-500/10 text-amber-300">
                  &#x26A1; Pick Up Now
                </button>
                <button type="button" onclick="setScheduleOption('later')" id="btn-later" class="p-3 rounded-xl border font-bold text-xs uppercase transition border-slate-800 bg-slate-900 text-slate-400">
                  &#x1F4C5; Schedule For Later
                </button>
              </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black text-slate-300 uppercase mb-1.5">Pickup Date</label>
                <input type="date" id="pickup_date" required class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
              </div>
              <div>
                <label class="block text-xs font-black text-slate-300 uppercase mb-1.5">Pickup Time</label>
                <input type="time" id="pickup_time" required class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
              </div>
            </div>

            <div class="pt-3 flex justify-between gap-3">
              <button type="button" onclick="goToStep(1)" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs px-6 py-3.5 rounded-xl uppercase">
                &#x2B05; Back
              </button>
              <button type="button" onclick="goToStep(3)" class="gradient-btn-gold text-slate-950 font-black text-xs px-8 py-3.5 rounded-xl uppercase tracking-wider flex items-center gap-2 shadow-xl">
                Next: Choose Cab &#x27A4;
              </button>
            </div>
          </div>

          <!-- STEP 3: CHOOSE CAB TIER -->
          <div id="step-3" class="hidden space-y-5 animate-fadeIn">
            <div class="bg-slate-950 p-4 rounded-2xl border border-slate-800 text-xs flex justify-between items-center">
              <div>
                <span class="text-[10px] text-slate-500 uppercase block font-bold">Selected Route & Time</span>
                <span class="text-amber-300 font-extrabold text-sm" id="summary-route"></span>
                <span class="text-slate-400 block font-semibold" id="summary-datetime"></span>
              </div>
              <button type="button" onclick="goToStep(2)" class="text-xs text-amber-400 hover:underline font-bold">Edit Time</button>
            </div>

            <div>
              <label class="block text-xs font-black text-slate-300 uppercase mb-3">Select Vehicle Tier</label>
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div onclick="selectVehicle('Sedan')" id="tier-Sedan" class="p-4 rounded-2xl border cursor-pointer border-amber-400 bg-amber-500/10 flex items-center justify-between transition uber-card-hover">
                  <div class="flex items-center gap-3">
                    <img src="./cabs/sedan.png" alt="Sedan" class="w-16 h-10 object-contain" onerror="this.onerror=null; this.src='https://pavancab.com/cabs/sedan.png';">
                    <div>
                      <h4 class="text-xs font-black text-white uppercase font-outfit">Sedan (4 Seater)</h4>
                      <p class="text-[10px] text-slate-400 font-semibold">Swift Dzire / Etios &#x2022; AC</p>
                    </div>
                  </div>
                  <span class="text-base font-black text-amber-300 font-outfit" id="fare-tier-Sedan">&#x20B9;0</span>
                </div>

                <div onclick="selectVehicle('Ertiga')" id="tier-Ertiga" class="p-4 rounded-2xl border cursor-pointer border-slate-800 bg-slate-900 flex items-center justify-between transition uber-card-hover">
                  <div class="flex items-center gap-3">
                    <img src="./cabs/ertiga.png" alt="Ertiga" class="w-16 h-10 object-contain" onerror="this.onerror=null; this.src='https://pavancab.com/cabs/ertiga.png';">
                    <div>
                      <h4 class="text-xs font-black text-white uppercase font-outfit">Ertiga (6 Seater)</h4>
                      <p class="text-[10px] text-slate-400 font-semibold">Maruti Ertiga &#x2022; AC</p>
                    </div>
                  </div>
                  <span class="text-base font-black text-amber-300 font-outfit" id="fare-tier-Ertiga">&#x20B9;0</span>
                </div>

                <div onclick="selectVehicle('SUV')" id="tier-SUV" class="p-4 rounded-2xl border cursor-pointer border-slate-800 bg-slate-900 flex items-center justify-between transition uber-card-hover">
                  <div class="flex items-center gap-3">
                    <img src="./cabs/innova.png" alt="SUV" class="w-16 h-10 object-contain" onerror="this.onerror=null; this.src='https://pavancab.com/cabs/innova.png';">
                    <div>
                      <h4 class="text-xs font-black text-white uppercase font-outfit">SUV (6-7 Seater)</h4>
                      <p class="text-[10px] text-slate-400 font-semibold">Toyota Innova / Marazzo</p>
                    </div>
                  </div>
                  <span class="text-base font-black text-amber-300 font-outfit" id="fare-tier-SUV">&#x20B9;0</span>
                </div>

                <div onclick="selectVehicle('Crysta')" id="tier-Crysta" class="p-4 rounded-2xl border cursor-pointer border-slate-800 bg-slate-900 flex items-center justify-between transition uber-card-hover">
                  <div class="flex items-center gap-3">
                    <img src="./cabs/innova.png" alt="Crysta" class="w-16 h-10 object-contain" onerror="this.onerror=null; this.src='https://pavancab.com/cabs/innova.png';">
                    <div>
                      <h4 class="text-xs font-black text-white uppercase font-outfit">Innova Crysta VIP</h4>
                      <p class="text-[10px] text-slate-400 font-semibold">Luxury Innova Crysta &#x2022; AC</p>
                    </div>
                  </div>
                  <span class="text-base font-black text-amber-300 font-outfit" id="fare-tier-Crysta">&#x20B9;0</span>
                </div>
              </div>
            </div>

            <div class="pt-3 flex justify-between gap-3">
              <button type="button" onclick="goToStep(2)" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs px-6 py-3.5 rounded-xl uppercase">
                &#x2B05; Back
              </button>
              <button type="button" onclick="goToStep(4)" class="gradient-btn-gold text-slate-950 font-black text-xs px-8 py-3.5 rounded-xl uppercase tracking-wider flex items-center gap-2 shadow-xl">
                Next: Contact Info &#x27A4;
              </button>
            </div>
          </div>

          <!-- STEP 4: PASSENGER DETAILS & CONFIRMATION -->
          <div id="step-4" class="hidden space-y-5 animate-fadeIn">
            <div class="bg-gradient-to-r from-amber-500/10 via-yellow-500/5 to-amber-500/10 border border-amber-500/40 p-4 rounded-2xl flex flex-wrap items-center justify-between gap-3 shadow-inner">
              <div>
                <span class="text-[10px] font-black text-slate-400 uppercase tracking-wider block">Guaranteed Fixed Fare</span>
                <span class="text-3xl font-black text-amber-400 font-outfit block" id="final-fare-amount">&#x20B9;0</span>
                <span class="text-[10px] text-slate-400 font-medium">Includes Fuel, Driver Allowance, Tolls & AC</span>
              </div>
              <div class="text-right">
                <span class="text-xs font-black text-amber-300 block" id="final-cab-label">Sedan AC Cab</span>
                <span class="text-[11px] text-slate-300 block font-semibold" id="final-route-label"></span>
              </div>
            </div>

            <!-- STEP 4 LOGIN REQUIRED BANNER -->
            <div id="step4-login-required" class="bg-gradient-to-r from-amber-500/20 via-amber-500/10 to-amber-500/20 border border-amber-500/40 p-5 rounded-2xl text-center space-y-3 shadow-xl">
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

            <!-- STEP 4 BOOKING FORM -->
            <div id="step4-booking-form" class="hidden space-y-4">
              <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                  <label class="block text-xs font-black text-slate-300 uppercase mb-1.5">Passenger Full Name</label>
                  <input type="text" id="customer_name" placeholder="e.g. Rahul Sharma" required class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
                </div>
                <div>
                  <label class="block text-xs font-black text-slate-300 uppercase mb-1.5">WhatsApp Mobile Number</label>
                  <input type="tel" id="customer_phone" placeholder="+919876543210" maxlength="20" required readonly class="w-full px-4 py-3 bg-slate-950 border border-amber-500/40 rounded-xl text-sm font-bold text-amber-300 tracking-wider">
                </div>
              </div>

              <div>
                <label class="block text-xs font-black text-slate-400 uppercase mb-1">Special Notes / Flight Number (Optional)</label>
                <input type="text" id="special_notes" placeholder="e.g. Flight 6E-542 arriving at Mopa Airport" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
              </div>

              <div id="booking-status-msg" class="text-xs text-center font-bold min-h-[16px]"></div>

              <div class="pt-3 flex justify-between gap-3">
                <button type="button" onclick="goToStep(3)" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs px-6 py-3.5 rounded-xl uppercase">
                  &#x2B05; Change Cab
                </button>
                <button type="submit" id="submit-booking-btn" class="gradient-btn-gold text-slate-950 font-black text-xs sm:text-sm px-8 py-3.5 rounded-xl uppercase tracking-wider flex items-center gap-2 shadow-2xl">
                  <i data-lucide="check-circle" class="w-5 h-5"></i> &#x26A1; Confirm & Book Goa Cab Now
                </button>
              </div>
            </div>
          </div>
        </form>
      </div>
    </div>

  </main>

  <!-- BRAND FOOTER -->
  <footer class="uber-card border-t border-slate-800/80 px-4 lg:px-8 py-8 mt-12 mb-16 md:mb-0">
    <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-6">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <div class="w-6 h-6 rounded-lg bg-amber-500/20 flex items-center justify-center p-0.5">
            <img src="./logo-pavancab.png" alt="PAVANCAB" class="w-full h-full object-contain" onerror="this.onerror=null; this.src='https://pavancab.com/logo-pavancab.png';">
          </div>
          <span class="text-sm font-black text-white font-outfit uppercase tracking-widest">PAVANCAB GOA TAXI NETWORK</span>
        </div>
        <p class="text-xs text-slate-400">Guaranteed Lowest Fixed Fares &#x2022; Mopa Airport, Dabolim Airport & North/South Goa Tours</p>
      </div>
      <div class="flex items-center gap-4 text-xs font-bold text-slate-300">
        <a href="tel:+919000000000" class="flex items-center gap-1.5 hover:text-amber-400 transition bg-slate-900 px-3 py-2 rounded-xl border border-slate-800">
          <i data-lucide="phone" class="w-4 h-4 text-amber-400"></i> +91 8199000000
        </a>
        <a href="https://wa.me/919000000000" target="_blank" class="flex items-center gap-1.5 hover:text-emerald-400 transition bg-slate-900 px-3 py-2 rounded-xl border border-slate-800">
          <i data-lucide="message-square" class="w-4 h-4 text-emerald-400"></i> WhatsApp Helpdesk
        </a>
      </div>
    </div>
  </footer>

  <!-- MOBILE BOTTOM NAVIGATION BAR -->
  <nav class="md:hidden fixed bottom-0 left-0 right-0 z-40 uber-card border-t border-slate-800/90 py-2 px-4 flex justify-around items-center shadow-2xl backdrop-blur-xl">
    <a href="./index.html" class="flex flex-col items-center gap-1 text-amber-400 font-extrabold">
      <i data-lucide="navigation" class="w-5 h-5"></i>
      <span class="text-[9px] uppercase font-black tracking-wider">Book Cab</span>
    </a>
    <a href="./rides.php" class="flex flex-col items-center gap-1 text-slate-400 hover:text-white">
      <i data-lucide="clock" class="w-5 h-5"></i>
      <span class="text-[9px] uppercase font-black tracking-wider">My Rides</span>
    </a>
    <a href="./dashboard/index.php" id="mobile-nav-dispatch" class="hidden flex-col items-center gap-1 text-indigo-300 hover:text-white">
      <i data-lucide="shield" class="w-5 h-5"></i>
      <span class="text-[9px] uppercase font-black tracking-wider">Dispatch</span>
    </a>
    <button id="mobile-login-btn" onclick="openAuthModal()" class="flex flex-col items-center gap-1 text-amber-400">
      <i data-lucide="user" class="w-5 h-5"></i>
      <span class="text-[9px] uppercase font-black tracking-wider">Login</span>
    </button>
  </nav>

  <!-- REPORT RIDE ISSUE MODAL -->
  <div id="report-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md animate-fadeIn">
    <div class="uber-card w-full max-w-md p-6 sm:p-7 rounded-3xl border border-amber-500/30 space-y-5 relative shadow-2xl">
      <button onclick="closeReportModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full hover:bg-slate-800 transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
      <div class="text-center space-y-1.5">
        <div class="w-12 h-12 rounded-2xl bg-amber-500/20 border border-amber-500/40 flex items-center justify-center mx-auto text-amber-400 shadow-md">
          <i data-lucide="alert-triangle" class="w-6 h-6"></i>
        </div>
        <h3 class="text-xl font-black text-white font-outfit uppercase tracking-tight">Report Ride Issue</h3>
        <p class="text-xs text-slate-400">Pavan Cab Safety Desk &#x2022; We investigate all reported rides 24/7</p>
      </div>
      <div id="report-msg" class="text-xs text-center font-bold min-h-[20px]"></div>
      <form id="report-form" onsubmit="submitRideReport(event)" class="space-y-4">
        <input type="hidden" id="report-booking-id" value="">
        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1.5">Issue Category</label>
          <div class="grid grid-cols-2 gap-2" id="report-category-group">
            <button type="button" onclick="selectReportCategory('SAFETY', this)" class="report-cat-btn bg-amber-400 text-slate-950 font-black text-[11px] py-2 px-3 rounded-xl border border-amber-400 text-left transition">
              &#x1F6E1;&#xFE0F; Safety Concern
            </button>
            <button type="button" onclick="selectReportCategory('DRIVER_BEHAVIOR', this)" class="report-cat-btn bg-slate-900 text-slate-300 font-bold text-[11px] py-2 px-3 rounded-xl border border-slate-800 hover:border-amber-400/50 text-left transition">
              &#x1F5E3;&#xFE0F; Driver Behavior
            </button>
            <button type="button" onclick="selectReportCategory('OVERCHARGING', this)" class="report-cat-btn bg-slate-900 text-slate-300 font-bold text-[11px] py-2 px-3 rounded-xl border border-slate-800 hover:border-amber-400/50 text-left transition">
              &#x1F4B0; Fare Dispute
            </button>
            <button type="button" onclick="selectReportCategory('ROUTE_DEVIATION', this)" class="report-cat-btn bg-slate-900 text-slate-300 font-bold text-[11px] py-2 px-3 rounded-xl border border-slate-800 hover:border-amber-400/50 text-left transition">
              &#x1F5FA;&#xFE0F; Route Deviation
            </button>
            <button type="button" onclick="selectReportCategory('VEHICLE_CONDITION', this)" class="report-cat-btn bg-slate-900 text-slate-300 font-bold text-[11px] py-2 px-3 rounded-xl border border-slate-800 hover:border-amber-400/50 text-left transition">
              &#x1F696; Cab Condition
            </button>
            <button type="button" onclick="selectReportCategory('LOST_ITEM', this)" class="report-cat-btn bg-slate-900 text-slate-300 font-bold text-[11px] py-2 px-3 rounded-xl border border-slate-800 hover:border-amber-400/50 text-left transition">
              &#x1F4BC; Lost Item
            </button>
          </div>
          <input type="hidden" id="report-selected-category" value="SAFETY">
        </div>
        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1.5">Describe What Happened</label>
          <textarea id="report-description" rows="3" required placeholder="Please provide details (e.g. overspeeding, wrong fare charged, driver issue)..." class="w-full p-3 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white font-medium focus:outline-none focus:border-amber-400 placeholder:text-slate-600"></textarea>
        </div>
        <button type="submit" id="report-submit-btn" class="w-full gradient-btn-gold text-slate-950 font-black text-xs py-3.5 rounded-xl uppercase tracking-wider flex items-center justify-center gap-2 shadow-xl">
          <i data-lucide="send" class="w-4 h-4"></i> Submit Report to Team
        </button>
      </form>
    </div>
  </div>

  <!-- WHATSAPP INSTANT OTP MODAL -->
  <div id="auth-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-md animate-fadeIn">
    <div class="uber-card w-full max-w-md p-6 sm:p-8 rounded-3xl border border-slate-800 space-y-5 relative shadow-2xl">
      <button onclick="closeAuthModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full hover:bg-slate-800 transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>
      <div class="text-center space-y-2">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-emerald-500/20 via-amber-500/20 to-emerald-500/10 border border-emerald-500/30 flex items-center justify-center mx-auto text-emerald-400 shadow-lg">
          <i data-lucide="message-circle" class="w-7 h-7"></i>
        </div>
        <h3 class="text-xl font-black text-white font-outfit uppercase tracking-tight">WhatsApp Login</h3>
        <p class="text-xs text-slate-400">Instant OTP verification for Riders, Drivers & Dispatch Admins</p>
      </div>
      <div id="auth-msg" class="text-xs text-center font-bold min-h-[20px] transition-all"></div>

      <!-- STEP 1: ENTER PHONE -->
      <form id="otp-form" onsubmit="handleSendOtp(event)" class="space-y-4">
        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1.5 flex items-center justify-between">
            <span class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-emerald-400"></span> WhatsApp Mobile Number
            </span>
            <span class="text-[10px] text-amber-400 font-bold">Country code changeable</span>
          </label>
          <div class="flex gap-2">
            <div class="relative w-32 sm:w-36 shrink-0">
              <select id="login-country-code" onchange="handleCountryCodeChange()" class="w-full h-[46px] px-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400 appearance-none cursor-pointer pr-6 font-mono">
                <option value="+91" selected>&#x1F1EE;&#x1F1F3; +91 (India)</option>
                <option value="+44">&#x1F1EC;&#x1F1E7; +44 (UK)</option>
                <option value="+1">&#x1F1FA;&#x1F1F8; +1 (USA/CA)</option>
                <option value="+971">&#x1F1E6;&#x1F1EA; +971 (UAE)</option>
                <option value="+7">&#x1F1F7;&#x1F1FA; +7 (Russia)</option>
                <option value="+49">&#x1F1E9;&#x1F1EA; +49 (Germany)</option>
                <option value="+61">&#x1F1E6;&#x1F1FA; +61 (Australia)</option>
                <option value="+33">&#x1F1EB;&#x1F1F7; +33 (France)</option>
                <option value="+65">&#x1F1F8;&#x1F1EC; +65 (Singapore)</option>
                <option value="+966">&#x1F1F8;&#x1F1E6; +966 (Saudi)</option>
                <option value="+974">&#x1F1F6;&#x1F1E6; +974 (Qatar)</option>
                <option value="+968">&#x1F1F4;&#x1F1F2; +968 (Oman)</option>
                <option value="+965">&#x1F1F0;&#x1F1FC; +965 (Kuwait)</option>
                <option value="+973">&#x1F1E7;&#x1F1ED; +973 (Bahrain)</option>
                <option value="+66">&#x1F1F9;&#x1F1ED; +66 (Thailand)</option>
                <option value="+60">&#x1F1F2;&#x1F1FE; +60 (Malaysia)</option>
                <option value="+39">&#x1F1EE;&#x1F1F9; +39 (Italy)</option>
                <option value="+34">&#x1F1EA;&#x1F1F8; +34 (Spain)</option>
                <option value="+31">&#x1F1F3;&#x1F1F1; +31 (Netherlands)</option>
                <option value="+41">&#x1F1E8;&#x1F1ED; +41 (Switzerland)</option>
                <option value="+972">&#x1F1EE;&#x1F1F1; +972 (Israel)</option>
                <option value="+81">&#x1F1EF;&#x1F1F5; +81 (Japan)</option>
                <option value="+86">&#x1F1E8;&#x1F1F3; +86 (China)</option>
                <option value="+880">&#x1F1E7;&#x1F1E9; +880 (Bangladesh)</option>
                <option value="+977">&#x1F1F3;&#x1F1F5; +977 (Nepal)</option>
                <option value="+94">&#x1F1F1;&#x1F1F0; +94 (Sri Lanka)</option>
                <option value="custom">&#x1F310; Other (+..)</option>
              </select>
              <div class="absolute right-2 top-4 pointer-events-none text-slate-400 text-[10px]">&#x25BC;</div>
            </div>
            <div class="relative flex-1 min-w-0">
              <input type="tel" id="login-phone" placeholder="9876543210" maxlength="16" required class="w-full h-[46px] px-3.5 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400 placeholder:text-slate-600 tracking-wider">
            </div>
          </div>
          <div id="custom-country-code-container" class="hidden mt-2">
            <input type="text" id="custom-country-code" placeholder="Enter custom country code (e.g. +351)" class="w-full px-3.5 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-amber-300 font-mono focus:outline-none focus:border-amber-400">
          </div>
          <span class="text-[10px] text-slate-500 mt-1 block">Super Admin: 8199000000 &#x2022; Team: Assigned Dispatchers</span>
        </div>
        <button type="submit" id="send-otp-btn" class="w-full gradient-btn-gold text-slate-950 font-black text-xs py-3.5 rounded-xl uppercase tracking-wider flex items-center justify-center gap-2 shadow-xl">
          <i data-lucide="send" class="w-4 h-4"></i> Send WhatsApp OTP
        </button>
      </form>

      <!-- STEP 2: VERIFY OTP -->
      <form id="verify-otp-form" onsubmit="handleVerifyOtp(event)" class="hidden space-y-4 animate-fadeIn">
        <div>
          <div class="flex items-center justify-between mb-1.5">
            <label class="text-xs font-black text-slate-300 uppercase">Enter 6-Digit OTP Code</label>
            <button type="button" onclick="handleSendOtp(event)" class="text-[11px] text-amber-400 hover:underline font-bold">Resend OTP</button>
          </div>
          <input type="text" id="login-otp" placeholder="123456" maxlength="6" required class="w-full px-4 py-3 bg-slate-900 border border-amber-500/60 rounded-xl text-lg font-black text-amber-300 text-center tracking-[0.4em] focus:outline-none focus:border-amber-400 shadow-inner">
        </div>
        <div>
          <label class="block text-xs font-black text-slate-400 uppercase mb-1">Your Name (Optional)</label>
          <input type="text" id="login-name" placeholder="Passenger / Dispatcher Name" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-800 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
        </div>
        <button type="submit" id="verify-otp-btn" class="w-full bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs py-3.5 rounded-xl uppercase tracking-wider flex items-center justify-center gap-2 shadow-xl transition">
          <i data-lucide="check-circle" class="w-4 h-4"></i> Verify & Login
        </button>
      </form>
    </div>
  </div>

  <script src="./api_client.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') lucide.createIcons();
    });

    /* ============================================================
       STATE
       ============================================================ */
    let currentFares = { Sedan: 0, Ertiga: 0, SUV: 0, Crysta: 0 };
    let currentBookingMode = 'one_way';
    let cachedHourlyFares = {};
    let cachedTourMap = {};
    let currentStep = 1;

    /* ============================================================
       INIT - Check auth & load pickups on page load
       ============================================================ */
    (async function init() {
      const now = new Date();
      const dateInput = document.getElementById('pickup_date');
      const timeInput = document.getElementById('pickup_time');
      if (dateInput) dateInput.value = now.toISOString().split('T')[0];
      if (timeInput) timeInput.value = now.toTimeString().substring(0, 5);

      try {
        const me = await API.me();
        if (me.success && me.user) {
          window.currentUser = me.user;
          window.isUserLoggedIn = true;
          window.currentUserPhone = me.user.mobile || '';
          window.currentUserName = me.user.name || '';
          window.isLoggedIn = true;
          window.sessionUserPhone = me.user.mobile || '';
          window.sessionUserEmail = me.user.email || '';
          window.sessionUserRole = me.user.role || '';
          applySoftLoggedInHeader(me.user);
        }
      } catch(e) {}

      loadPickups('oneway');
      loadPickups('hourly');
      loadPickups('tour');
    })();

    /* ============================================================
       LOAD PICKUPS VIA API
       ============================================================ */
    async function loadPickups(type) {
      try {
        const data = await API.pickups(type);
        let selectId, placeholder;
        if (type === 'oneway') { selectId = 'oneway_pickup'; placeholder = '-- Select Pickup Location --'; }
        else if (type === 'hourly') { selectId = 'hourly_pickup'; placeholder = '-- Select Pickup Area --'; }
        else { selectId = 'tour_pickup'; placeholder = '-- Select Pickup Hotel Area --'; }

        const sel = document.getElementById(selectId);
        sel.innerHTML = '<option value="">' + placeholder + '</option>';
        if (data.success && data.pickups) {
          data.pickups.forEach(p => {
            sel.innerHTML += '<option value="' + p.id + '">' + escapeHtml(p.name) + '</option>';
          });
        }
      } catch(e) {}
    }

    function escapeHtml(str) {
      const d = document.createElement('div');
      d.textContent = str || '';
      return d.innerHTML;
    }

    /* ============================================================
       BOOKING MODE SWITCHING
       ============================================================ */
    function switchBookingMode(mode) {
      currentBookingMode = mode;
      document.getElementById('booking_trip_type').value = mode;

      const activeClass = 'flex-1 py-2.5 px-2 rounded-xl font-black text-xs uppercase tracking-wider transition flex items-center justify-center gap-1.5 bg-amber-400 text-slate-950 shadow-md';
      const idleClass = 'flex-1 py-2.5 px-2 rounded-xl font-black text-xs uppercase tracking-wider transition flex items-center justify-center gap-1.5 text-slate-400 hover:text-white';

      document.getElementById('tab-one_way').className = (mode === 'one_way') ? activeClass : idleClass;
      document.getElementById('tab-hourly').className = (mode === 'hourly') ? activeClass : idleClass;
      document.getElementById('tab-tour').className = (mode === 'tour') ? activeClass : idleClass;

      document.getElementById('mode-one_way-section').classList.toggle('hidden', mode !== 'one_way');
      document.getElementById('mode-hourly-section').classList.toggle('hidden', mode !== 'hourly');
      document.getElementById('mode-tour-section').classList.toggle('hidden', mode !== 'tour');

      if (mode === 'one_way') calculateOneWayFare();
      else if (mode === 'hourly') {
        const pickupId = document.getElementById('hourly_pickup').value;
        if (pickupId) fetchHourlyFares(pickupId);
        else calculateHourlyFare();
      } else if (mode === 'tour') {
        const pickupId = document.getElementById('tour_pickup').value;
        if (pickupId) fetchTourFares(pickupId);
      }
    }

    /* ============================================================
       SCHEDULE OPTIONS
       ============================================================ */
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

    /* ============================================================
       STEP NAVIGATION
       ============================================================ */
    function goToStep(step) {
      if (step >= 2) {
        if (!window.isUserLoggedIn && !window.currentUser) {
          openAuthModal();
          const msg = document.getElementById('auth-msg');
          if (msg) msg.innerHTML = '<span class="text-amber-400 font-black">&#x1F512; Please log in with WhatsApp to view cab fares & schedule your trip!</span>';
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
          summaryText = pName + ' &#x27A4; ' + dName;
        } else if (currentBookingMode === 'hourly') {
          const pSelect = document.getElementById('hourly_pickup');
          if (!pSelect.value) {
            alert('Please select your Starting Pickup Area for hourly rental.');
            return;
          }
          const hours = document.getElementById('hourly_hours').value;
          summaryText = pSelect.options[pSelect.selectedIndex].text + ' (' + hours + ' Hours / ' + (hours * 10) + ' KM Rental)';
        } else if (currentBookingMode === 'tour') {
          const pSelect = document.getElementById('tour_pickup');
          const tSelect = document.getElementById('tour_package');
          if (!pSelect.value || !tSelect.value) {
            alert('Please select your Hotel / Pickup Location and Sightseeing Tour.');
            return;
          }
          const tourObj = cachedTourMap[tSelect.value];
          const tourTitle = tourObj ? tourObj.title : tSelect.value;
          summaryText = tourTitle + ' (from ' + pSelect.options[pSelect.selectedIndex].text + ')';
        }

        document.getElementById('summary-route-step2').innerText = summaryText;
        document.getElementById('summary-route').innerText = summaryText;
        document.getElementById('final-route-label').innerText = summaryText;
      }

      if (step >= 3) {
        const date = document.getElementById('pickup_date').value;
        const time = document.getElementById('pickup_time').value;
        document.getElementById('summary-datetime').innerText = date + ' at ' + time;
      }

      if (step >= 4) {
        if (!window.isUserLoggedIn && !window.currentUser) {
          openAuthModal();
          const msg = document.getElementById('auth-msg');
          if (msg) msg.innerHTML = '<span class="text-amber-400 font-black">&#x1F512; Please log in with WhatsApp first to complete booking!</span>';
          return;
        }
        const fare = parseFloat(document.getElementById('selected_total_fare').value || 0);
        if (!fare || fare <= 0) {
          alert('Please select a cab tier to continue.');
          return;
        }

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
        const el = document.getElementById('step-' + s);
        const badge = document.getElementById('badge-step-' + s);
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

    /* ============================================================
       ONE-WAY: FETCH DROPS & CALCULATE FARES
       ============================================================ */
    async function fetchDropLocations(pickupId) {
      if (!pickupId) return;
      const select = document.getElementById('oneway_drop');
      select.innerHTML = '<option value="">Loading destinations...</option>';
      try {
        const data = await API.drops(pickupId);
        select.innerHTML = '<option value="">-- Select Drop Destination --</option>';
        if (data.drops) {
          data.drops.forEach(d => {
            select.innerHTML += '<option value="' + d.id + '" data-sedan="' + d.sedan_fare + '" data-suv="' + d.suv_fare + '" data-name="' + escapeHtml(d.destination) + '">' + escapeHtml(d.destination) + ' (' + d.distance + ' km)</option>';
          });
        }
      } catch(e) {
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

    /* ============================================================
       HOURLY: FETCH FARES & SELECT PACKAGE
       ============================================================ */
    async function fetchHourlyFares(placeId) {
      if (!placeId) return;
      try {
        const data = await API.hourlyFares(placeId);
        if (data.success && data.fares) {
          cachedHourlyFares = data.fares;
        }
      } catch(e) {}
      calculateHourlyFare();
    }

    function selectHourlyPackage(hours) {
      document.getElementById('hourly_hours').value = hours;
      [4, 8, 12].forEach(h => {
        const el = document.getElementById('pkg-' + h);
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

    /* ============================================================
       TOURS: FETCH PACKAGES & CALCULATE FARES
       ============================================================ */
    async function fetchTourFares(placeId) {
      if (!placeId) return;
      const select = document.getElementById('tour_package');
      const descBox = document.getElementById('tour-details-box');
      select.innerHTML = '<option value="">Loading available sightseeing tours...</option>';
      descBox.classList.add('hidden');
      cachedTourMap = {};

      try {
        const data = await API.tourPackages(placeId);
        if (data.success && data.tours && data.tours.length > 0) {
          select.innerHTML = '<option value="">-- Choose Available Sightseeing Tour --</option>';
          data.tours.forEach(t => {
            cachedTourMap[t.tour_name] = t;
            select.innerHTML += '<option value="' + escapeHtml(t.tour_name) + '">' + escapeHtml(t.title) + '</option>';
          });
          select.selectedIndex = 1;
          calculateTourFare();
        } else {
          select.innerHTML = '<option value="">No tours available from this location</option>';
          updateFaresDisplay({ Sedan: 0, Ertiga: 0, SUV: 0, Crysta: 0 });
          descBox.classList.remove('hidden');
          document.getElementById('tour-highlights-title').innerText = 'No Tours from this location';
          document.getElementById('tour-highlights-desc').innerText = 'Sightseeing packages are not mapped for this pickup location. Please choose an adjacent beach/town pickup or select an Hourly Rental package.';
        }
      } catch(e) {
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

    /* ============================================================
       FARE DISPLAY UPDATE
       ============================================================ */
    function updateFaresDisplay(fares) {
      document.getElementById('fare-tier-Sedan').innerText = '\u20B9' + (fares.Sedan || 0);
      document.getElementById('fare-tier-Ertiga').innerText = '\u20B9' + (fares.Ertiga || 0);
      document.getElementById('fare-tier-SUV').innerText = '\u20B9' + (fares.SUV || 0);
      document.getElementById('fare-tier-Crysta').innerText = '\u20B9' + (fares.Crysta || 0);

      const selectedCab = document.getElementById('selected_cab_type').value || 'Sedan';
      const total = fares[selectedCab] || fares.Sedan || 0;

      document.getElementById('selected_total_fare').value = total;
      document.getElementById('final-fare-amount').innerText = '\u20B9' + total;
      document.getElementById('final-cab-label').innerText = selectedCab + ' AC Cab';
    }

    /* ============================================================
       VEHICLE SELECTION
       ============================================================ */
    function selectVehicle(cabType) {
      document.getElementById('selected_cab_type').value = cabType;
      ['Sedan', 'Ertiga', 'SUV', 'Crysta'].forEach(c => {
        const el = document.getElementById('tier-' + c);
        if (c === cabType) {
          el.className = 'p-4 rounded-2xl border cursor-pointer border-amber-400 bg-amber-500/10 flex items-center justify-between transition uber-card-hover';
        } else {
          el.className = 'p-4 rounded-2xl border cursor-pointer border-slate-800 bg-slate-900 flex items-center justify-between transition uber-card-hover';
        }
      });
      const total = currentFares[cabType] || 0;
      document.getElementById('selected_total_fare').value = total;
      document.getElementById('final-fare-amount').innerText = '\u20B9' + total;
      document.getElementById('final-cab-label').innerText = cabType + ' AC Cab';
    }

    /* ============================================================
       CONFIRM BOOKING
       ============================================================ */
    async function handleConfirmBooking(e) {
      e.preventDefault();

      if (!window.isUserLoggedIn && !window.currentUser) {
        openAuthModal();
        const msg = document.getElementById('auth-msg');
        if (msg) msg.innerHTML = '<span class="text-amber-400 font-black">&#x1F512; Please log in with WhatsApp first to book your cab!</span>';
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
        drop = dSelect.options[dSelect.selectedIndex]?.dataset?.name || dSelect.options[dSelect.selectedIndex]?.text || '';
      } else if (currentBookingMode === 'hourly') {
        const pSelect = document.getElementById('hourly_pickup');
        const hours = document.getElementById('hourly_hours').value;
        pickup = pSelect.options[pSelect.selectedIndex]?.text || 'Goa';
        drop = hours + ' Hours / ' + (hours * 10) + ' KM Local Hourly Rental';
      } else if (currentBookingMode === 'tour') {
        const pSelect = document.getElementById('tour_pickup');
        const tSelect = document.getElementById('tour_package');
        const tourObj = cachedTourMap[tSelect.value];
        pickup = pSelect.options[pSelect.selectedIndex]?.text || 'Goa Hotel';
        drop = tourObj ? tourObj.title : tSelect.value;
      }

      const name = document.getElementById('customer_name').value.trim();
      const phone = document.getElementById('customer_phone').value.trim();
      const date = document.getElementById('pickup_date').value;
      const time = document.getElementById('pickup_time').value;
      const cab = document.getElementById('selected_cab_type').value;
      const notes = document.getElementById('special_notes').value.trim();
      const msgEl = document.getElementById('booking-status-msg');
      const btn = document.getElementById('submit-booking-btn');

      if (phone) {
        try { localStorage.setItem('pavancab_user_phone', phone); } catch(e) {}
        if (typeof window.syncFcmTokenWithUser === 'function') {
          window.syncFcmTokenWithUser({ mobile: phone });
        }
      }

      if ('Notification' in window && Notification.permission === 'default') {
        try {
          Notification.requestPermission().then(perm => {
            if (perm === 'granted' && typeof window.syncFcmTokenWithUser === 'function') {
              window.syncFcmTokenWithUser({ mobile: phone });
            }
          });
        } catch(e) {}
      }

      btn.innerHTML = '<span class="animate-spin inline-block mr-1">&#x23F3;</span> Booking Cab & Dispatching...';
      btn.disabled = true;

      try {
        const fcmToken = window.fcmTokenStored || (function() { try { return localStorage.getItem('pavancab_fcm_token'); } catch(e) { return ''; } })() || '';
        const data = await API.createBooking({
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
          fcm_token: fcmToken
        });

        if (data.success) {
          msgEl.innerHTML = '<span class="text-emerald-400 font-black text-sm">&#x2713; Ride Booked Successfully! Opening Live Ride Radar...</span>';
          setTimeout(() => {
            const targetId = (data.booking && data.booking.id) ? data.booking.id : '';
            window.location.href = './rides.php?id=' + encodeURIComponent(targetId) + '&phone=' + encodeURIComponent(phone);
          }, 700);
        } else {
          msgEl.innerHTML = '<span class="text-red-400 font-bold">' + escapeHtml(data.error || 'Failed to book cab') + '</span>';
          btn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> &#x26A1; Confirm & Book Goa Cab Now';
          btn.disabled = false;
          if (typeof lucide !== 'undefined') lucide.createIcons();
        }
      } catch(err) {
        msgEl.innerHTML = '<span class="text-red-400 font-bold">Network error. Please try again.</span>';
        btn.innerHTML = '<i data-lucide="check-circle" class="w-5 h-5"></i> &#x26A1; Confirm & Book Goa Cab Now';
        btn.disabled = false;
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }
    }

    /* ============================================================
       AUTH MODAL FUNCTIONS
       ============================================================ */
    function openAuthModal() {
      document.getElementById('auth-modal').classList.remove('hidden');
      document.getElementById('auth-msg').innerHTML = '';
      document.getElementById('otp-form').classList.remove('hidden');
      document.getElementById('verify-otp-form').classList.add('hidden');
      setTimeout(() => document.getElementById('login-phone').focus(), 100);
    }
    window.openLoginModal = openAuthModal;

    function closeAuthModal() {
      document.getElementById('auth-modal').classList.add('hidden');
    }

    function handleCountryCodeChange() {
      const select = document.getElementById('login-country-code');
      const customContainer = document.getElementById('custom-country-code-container');
      const phoneInput = document.getElementById('login-phone');
      if (select.value === 'custom') {
        customContainer.classList.remove('hidden');
        setTimeout(() => document.getElementById('custom-country-code').focus(), 50);
      } else {
        customContainer.classList.add('hidden');
      }
      if (phoneInput) {
        phoneInput.placeholder = (select.value === '+91') ? '9876543210' : 'Enter mobile number';
      }
    }

    function getNormalizedAuthPhone() {
      const select = document.getElementById('login-country-code');
      const customInput = document.getElementById('custom-country-code');
      const phoneInput = document.getElementById('login-phone');
      let rawPhone = (phoneInput ? phoneInput.value : '').trim();
      if (!rawPhone) return '';
      if (rawPhone.startsWith('+')) {
        return '+' + rawPhone.replace(/\D/g, '');
      }
      let prefix = select ? select.value : '+91';
      if (prefix === 'custom' && customInput) {
        prefix = customInput.value.trim();
        if (prefix && !prefix.startsWith('+')) prefix = '+' + prefix;
      }
      const cleanPrefix = prefix.replace(/\D/g, '') || '91';
      const cleanDigits = rawPhone.replace(/\D/g, '');
      return '+' + cleanPrefix + cleanDigits;
    }

    async function handleSendOtp(e) {
      if (e) e.preventDefault();
      const fullPhone = getNormalizedAuthPhone();
      const msg = document.getElementById('auth-msg');
      const btn = document.getElementById('send-otp-btn');

      const digitsOnly = fullPhone.replace(/\D/g, '');
      if (!fullPhone || digitsOnly.length < 7) {
        msg.innerHTML = '<span class="text-amber-400">Please enter a valid WhatsApp mobile number.</span>';
        return;
      }

      btn.innerHTML = '<span class="animate-spin inline-block mr-1">&#x23F3;</span> Sending...';
      btn.disabled = true;
      msg.innerHTML = '';

      try {
        const data = await API.sendOTP(fullPhone);
        if (data.success) {
          msg.innerHTML = '<span class="text-emerald-400 font-bold">&#x2713; ' + escapeHtml(data.message) + '</span>';
          document.getElementById('otp-form').classList.add('hidden');
          document.getElementById('verify-otp-form').classList.remove('hidden');
          if (typeof lucide !== 'undefined') lucide.createIcons();
          setTimeout(() => document.getElementById('login-otp').focus(), 100);
        } else {
          msg.innerHTML = '<span class="text-red-400">' + escapeHtml(data.error || 'Failed to send OTP') + '</span>';
          btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Send WhatsApp OTP';
          btn.disabled = false;
          if (typeof lucide !== 'undefined') lucide.createIcons();
        }
      } catch(err) {
        msg.innerHTML = '<span class="text-red-400">Network error. Please try again.</span>';
        btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Send WhatsApp OTP';
        btn.disabled = false;
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }
    }

    async function handleVerifyOtp(e) {
      if (e) e.preventDefault();
      const fullPhone = getNormalizedAuthPhone();
      const otp = document.getElementById('login-otp').value.trim();
      const name = document.getElementById('login-name').value.trim();
      const msg = document.getElementById('auth-msg');
      const btn = document.getElementById('verify-otp-btn');

      if (!otp || otp.length < 6) {
        msg.innerHTML = '<span class="text-amber-400">Please enter 6-digit OTP.</span>';
        return;
      }

      btn.innerHTML = '<span class="animate-spin inline-block mr-1">&#x23F3;</span> Verifying...';
      btn.disabled = true;

      try {
        let tokenToSend = window.fcmTokenStored || '';
        if (!tokenToSend && 'Notification' in window && globalFcmMessaging && globalFcmReg) {
          try {
            if (Notification.permission === 'default') {
              const perm = await Notification.requestPermission();
              if (perm === 'granted') {
                tokenToSend = await globalFcmMessaging.getToken({ vapidKey: VAPID_KEY, serviceWorkerRegistration: globalFcmReg });
                if (tokenToSend) {
                  window.fcmTokenStored = tokenToSend;
                  try { localStorage.setItem('pavancab_fcm_token', tokenToSend); } catch(e) {}
                }
              }
            } else if (Notification.permission === 'granted') {
              tokenToSend = await globalFcmMessaging.getToken({ vapidKey: VAPID_KEY, serviceWorkerRegistration: globalFcmReg });
              if (tokenToSend) {
                window.fcmTokenStored = tokenToSend;
                try { localStorage.setItem('pavancab_fcm_token', tokenToSend); } catch(e) {}
              }
            }
          } catch(e) {}
        }

        const data = await API.verifyOTP(fullPhone, otp, name, '', tokenToSend);

        if (data.success) {
          try { localStorage.setItem('pavancab_user_phone', data.user.mobile || fullPhone); } catch(e) {}
          if (data.user.email) {
            try { localStorage.setItem('pavancab_user_email', data.user.email); } catch(e) {}
          }
          const roleLabel = data.user.role === 'admin' ? '\uD83D\uDC51 Super Admin' : (data.user.role === 'team' ? '\uD83D\uDEE1\uFE0F Team Dispatcher' : '\uD83D\uDE96 Passenger');
          msg.innerHTML = '<span class="text-emerald-400 font-black">&#x2713; Welcome, ' + escapeHtml(data.user.name) + ' (' + roleLabel + ')!</span>';
          window.currentUser = data.user;
          window.isUserLoggedIn = true;
          window.isLoggedIn = true;
          window.sessionUserPhone = data.user.mobile || fullPhone;
          window.sessionUserEmail = data.user.email || '';
          window.sessionUserRole = data.user.role || 'user';
          window.currentUserPhone = data.user.mobile || '';
          window.currentUserName = data.user.name || '';

          if (typeof initFcmPush === 'function') initFcmPush();
          if (typeof window.syncFcmTokenWithUser === 'function') window.syncFcmTokenWithUser(data.user);

          setTimeout(() => {
            closeAuthModal();
            if (data.user.role === 'admin' || data.user.role === 'team') {
              window.location.href = data.redirect || './dashboard/index.php';
            } else {
              applySoftLoggedInHeader(data.user);
              const nameInput = document.getElementById('customer_name');
              const phoneInput = document.getElementById('customer_phone');
              if (nameInput && data.user.name) nameInput.value = data.user.name;
              if (phoneInput && data.user.mobile) phoneInput.value = data.user.mobile;
            }
          }, 500);
        } else {
          msg.innerHTML = '<span class="text-red-400 font-bold">' + escapeHtml(data.error || 'Invalid OTP code') + '</span>';
          btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i> Verify & Login';
          btn.disabled = false;
          if (typeof lucide !== 'undefined') lucide.createIcons();
        }
      } catch(err) {
        msg.innerHTML = '<span class="text-red-400">Network connection error.</span>';
        btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i> Verify & Login';
        btn.disabled = false;
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }
    }

    function applySoftLoggedInHeader(user) {
      if (!user) return;
      window.currentUser = user;
      window.isUserLoggedIn = true;
      window.currentUserPhone = user.mobile || '';
      window.currentUserName = user.name || '';

      const area = document.getElementById('header-user-area');
      if (area) {
        const roleBadge = user.role === 'admin'
          ? '<span class="badge-admin text-[10px] font-black uppercase px-2 py-0.5 rounded-full flex items-center gap-1 shadow-sm">&#x1F451; Super Admin</span>'
          : (user.role === 'team'
            ? '<span class="badge-team text-[10px] font-black uppercase px-2 py-0.5 rounded-full flex items-center gap-1 shadow-sm">&#x1F6E1;&#xFE0F; Team</span>'
            : '<span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>');
        area.innerHTML = '<div class="flex items-center gap-2 bg-slate-900/90 border border-slate-800 px-2.5 sm:px-3.5 py-1.5 rounded-2xl shadow-inner">'
          + roleBadge
          + '<span class="text-xs font-extrabold text-slate-200 hidden sm:inline-block">' + escapeHtml(user.name || user.mobile || 'User') + '</span>'
          + '<a href="./auth.php?action=logout" title="Logout" class="text-xs text-slate-400 hover:text-red-400 transition p-1 hover:bg-red-500/10 rounded-lg"><i data-lucide="log-out" class="w-3.5 h-3.5"></i></a>'
          + '</div>';
      }

      const mobileBtn = document.getElementById('mobile-login-btn');
      if (mobileBtn) {
        const a = document.createElement('a');
        a.href = './auth.php?action=logout';
        a.className = 'flex flex-col items-center gap-1 text-slate-400 hover:text-red-400';
        a.innerHTML = '<i data-lucide="log-out" class="w-5 h-5"></i><span class="text-[9px] uppercase font-black tracking-wider">Logout</span>';
        mobileBtn.replaceWith(a);
      }

      if (user.role === 'admin' || user.role === 'team') {
        const navDisp = document.getElementById('nav-dispatch');
        if (navDisp) navDisp.classList.remove('hidden');
        const mobDisp = document.getElementById('mobile-nav-dispatch');
        if (mobDisp) mobDisp.classList.remove('hidden');
      }

      const loginReq = document.getElementById('step4-login-required');
      const bookForm = document.getElementById('step4-booking-form');
      if (loginReq) loginReq.classList.add('hidden');
      if (bookForm) bookForm.classList.remove('hidden');

      const nameInput = document.getElementById('customer_name');
      const phoneInput = document.getElementById('customer_phone');
      if (nameInput && user.name) nameInput.value = user.name;
      if (phoneInput && user.mobile) phoneInput.value = user.mobile;

      if (typeof lucide !== 'undefined') lucide.createIcons();
    }

    /* ============================================================
       REPORT MODAL
       ============================================================ */
    function openReportModal(bookingId) {
      document.getElementById('report-booking-id').value = bookingId;
      document.getElementById('report-modal').classList.remove('hidden');
      document.getElementById('report-msg').innerHTML = '';
      document.getElementById('report-description').value = '';
    }
    window.openReportModal = openReportModal;

    function closeReportModal() {
      document.getElementById('report-modal').classList.add('hidden');
    }

    function selectReportCategory(cat, btnElem) {
      document.getElementById('report-selected-category').value = cat;
      const buttons = document.querySelectorAll('.report-cat-btn');
      buttons.forEach(b => {
        b.classList.remove('bg-amber-400', 'text-slate-950', 'font-black');
        b.classList.add('bg-slate-900', 'text-slate-300', 'font-bold');
      });
      if (btnElem) {
        btnElem.classList.remove('bg-slate-900', 'text-slate-300', 'font-bold');
        btnElem.classList.add('bg-amber-400', 'text-slate-950', 'font-black');
      }
    }

    async function submitRideReport(e) {
      e.preventDefault();
      const bookingId = document.getElementById('report-booking-id').value;
      const category = document.getElementById('report-selected-category').value;
      const description = document.getElementById('report-description').value.trim();
      const msg = document.getElementById('report-msg');
      const btn = document.getElementById('report-submit-btn');

      if (!bookingId || !description) {
        msg.innerHTML = '<span class="text-amber-400 font-bold">Please fill in details.</span>';
        return;
      }

      btn.disabled = true;
      btn.innerHTML = '<span class="animate-spin inline-block mr-1">&#x23F3;</span> Submitting Report...';

      try {
        const data = await API.reportIssue({ action: 'submit_ride_report', booking_id: bookingId, issue_category: category, description: description });
        if (data.success) {
          msg.innerHTML = '<div class="bg-emerald-500/20 border border-emerald-500 text-emerald-300 p-3 rounded-xl font-bold">&#x2713; ' + escapeHtml(data.message) + '</div>';
          btn.innerHTML = '&#x2713; Report Submitted';
          setTimeout(() => {
            closeReportModal();
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Submit Report to Team';
            if (typeof lucide !== 'undefined') lucide.createIcons();
          }, 1200);
        } else {
          msg.innerHTML = '<span class="text-red-400 font-bold">' + escapeHtml(data.error || 'Failed to submit report') + '</span>';
          btn.disabled = false;
          btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Submit Report to Team';
        }
      } catch(err) {
        msg.innerHTML = '<span class="text-red-400 font-bold">Network error while sending report.</span>';
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Submit Report to Team';
      }
    }

    function requestPushPermission() {
      if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
      }
    }
    document.addEventListener('click', requestPushPermission, { once: true });
  </script>

  <!-- FIREBASE CLOUD MESSAGING CLIENT SDK -->
  <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
  <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js"></script>

  <script>
    window.isLoggedIn = false;
    window.sessionUserPhone = '';
    window.sessionUserEmail = '';
    window.sessionUserRole = '';

    if (window.sessionUserPhone) {
      try { localStorage.setItem('pavancab_user_phone', window.sessionUserPhone); } catch(e) {}
    }

    const firebaseConfig = {
      apiKey: "AIzaSyA9_ZfLPnMew12a0g4EtikIne2jn7nEIkY",
      authDomain: "pavancab-4a1daa.firebaseapp.com",
      projectId: "pavancab-4a1daa",
      storageBucket: "pavancab-4a1daa.firebasestorage.app",
      messagingSenderId: "1064014558872",
      appId: "1:1064014558872:web:bb0b2d5ae8e4e79a0ac2ed"
    };

    const VAPID_KEY = "BBiDdmppgS12OFBx7KLwtb4eD7B2R57ESg5hLTXH384DUWwdoMzyAqhl2RyPWf_hVsSnZXt5uetIHhSOgyfccjA";

    window.fcmTokenStored = window.fcmTokenStored || (function() { try { return localStorage.getItem('pavancab_fcm_token'); } catch(e) { return null; } })() || null;
    let globalFcmMessaging = null;
    let globalFcmReg = null;

    window.syncFcmTokenWithUser = function(user) {
      if (user && user.mobile) {
        try { localStorage.setItem('pavancab_user_phone', user.mobile); } catch(e) {}
      }
      if (user && user.email) {
        try { localStorage.setItem('pavancab_user_email', user.email); } catch(e) {}
      }
      fetchAndSaveFcmToken(globalFcmMessaging, globalFcmReg, user);
    };

    window.requestAppPushPermission = async function() {
      try {
        if (!('Notification' in window)) {
          alert('Push notifications are not supported on this browser.');
          return false;
        }
        const perm = await Notification.requestPermission();
        if (perm === 'granted') {
          if (globalFcmMessaging && globalFcmReg) {
            await fetchAndSaveFcmToken(globalFcmMessaging, globalFcmReg);
          }
          if (typeof showBrowserNotification === 'function') {
            showBrowserNotification('\uD83D\uDD14 Push Notifications Active!', 'You will now receive live Goa ride updates & dispatch alerts.', null, 'default');
          }
          return true;
        } else if (perm === 'denied') {
          alert('Notifications are blocked in your browser settings. Please enable notifications in your browser site permissions to receive trip alerts.');
          return false;
        }
      } catch(e) {
        console.warn('requestAppPushPermission error:', e);
      }
      return false;
    };

    window.receiveNativeFcmToken = async function(token) {
      if (!token) return;
      window.fcmTokenStored = token;
      try { localStorage.setItem('pavancab_fcm_token', token); } catch(e) {}
      const storedPhone = (function() { try { return localStorage.getItem('pavancab_user_phone') || ''; } catch(e) { return ''; } })();
      const mobile = window.sessionUserPhone || storedPhone || '';
      const email = window.sessionUserEmail || '';
      await fetch('/app/api.php?action=save_fcm_token', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          action: 'save_fcm_token',
          fcm_token: token,
          user_mobile: mobile,
          user_email: email,
          role: window.sessionUserRole || 'user'
        })
      });
      sendAppHeartbeat(1);
    };

    function showBrowserNotification(title, body, targetUrl, eventType) {
      const FCM_TONES = {
        'NEW_BOOKING':     { url: 'https://assets.mixkit.co/active_storage/sfx/2771/2771-preview.mp3', vibrate: [200, 50, 200, 50, 300], icon: '\uD83D\uDC85' },
        'DRIVER_ASSIGNED': { url: 'https://assets.mixkit.co/active_storage/sfx/2020/2020-preview.mp3', vibrate: [100, 50, 100, 50, 200, 50, 200], icon: '\uD83D\uDE96' },
        'DRIVER_ACCEPTED': { url: 'https://assets.mixkit.co/active_storage/sfx/2000/2000-preview.mp3', vibrate: [100, 30, 100, 30, 100, 30, 300], icon: '\u2705' },
        'RIDE_STARTED':    { url: 'https://assets.mixkit.co/active_storage/sfx/1999/1999-preview.mp3', vibrate: [300, 100, 300, 100, 400], icon: '\uD83C\uDFC1' },
        'IN_TRANSIT':      { url: 'https://assets.mixkit.co/active_storage/sfx/1999/1999-preview.mp3', vibrate: [300, 100, 300, 100, 400], icon: '\uD83D\uDEE3\uFE0F' },
        'RIDE_COMPLETED':  { url: 'https://assets.mixkit.co/active_storage/sfx/2770/2770-preview.mp3', vibrate: [50, 30, 50, 30, 200, 50, 200], icon: '\uD83C\uDF89' },
        'CANCELLED':       { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [400, 100, 400], icon: '\uD83D\uDEAB' },
        'FARE_BOOSTED':    { url: 'https://assets.mixkit.co/active_storage/sfx/2770/2770-preview.mp3', vibrate: [50, 30, 50, 30, 50, 30, 200], icon: '\uD83D\uDD25' },
        'FARE_UPDATED':    { url: 'https://assets.mixkit.co/active_storage/sfx/2770/2770-preview.mp3', vibrate: [100, 50, 100, 50, 200], icon: '\uD83D\uDCB0' },
        'DRIVER_DECLINED': { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [400, 200, 400, 200, 400], icon: '\uD83D\uDD04' },
        'RIDE_RESET':      { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [300, 100, 300, 100, 300], icon: '\uD83D\uDD04' },
        'default':         { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [300, 100, 300, 100, 400], icon: '\uD83D\uDD14' }
      };
      const tone = FCM_TONES[eventType] || FCM_TONES['default'];

      try {
        const audio = new Audio(tone.url);
        audio.volume = 1.0;
        audio.play().catch(() => {});
      } catch(e) {}

      const destination = targetUrl || '/app/rides.php';

      if ('Notification' in window && Notification.permission === 'granted') {
        try {
          if (globalFcmReg && globalFcmReg.showNotification) {
            globalFcmReg.showNotification(title, {
              body: body,
              icon: 'https://pavancab.com/app/logo-pavancab.png',
              badge: 'https://pavancab.com/app/logo-pavancab.png',
              vibrate: tone.vibrate,
              tag: 'pavancab-msg-' + Date.now(),
              data: { url: destination, event_type: eventType }
            });
          } else {
            new Notification(title, { body: body, icon: 'https://pavancab.com/app/logo-pavancab.png', data: { url: destination } });
          }
        } catch(e) {
          try { new Notification(title, { body: body, icon: 'https://pavancab.com/app/logo-pavancab.png' }); } catch(err) {}
        }
      }

      try {
        let container = document.getElementById('fcm-toast-container');
        if (!container) {
          container = document.createElement('div');
          container.id = 'fcm-toast-container';
          container.className = 'fixed top-4 right-4 z-[99999] max-w-sm w-full space-y-2 pointer-events-none px-3';
          document.body.appendChild(container);
        }
        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto bg-slate-900 border-2 border-amber-400 text-white p-3.5 rounded-2xl shadow-2xl animate-bounce flex items-start gap-3 border-l-8 border-l-amber-400 cursor-pointer hover:bg-slate-800 transition';
        toast.onclick = () => { window.location.href = destination; };
        toast.innerHTML = '<div class="w-8 h-8 rounded-xl bg-amber-400/20 text-amber-400 flex items-center justify-center font-bold text-base shrink-0">' + tone.icon + '</div>'
          + '<div class="flex-1 space-y-0.5 min-w-0">'
          + '<div class="font-black text-amber-300 text-xs uppercase tracking-wide truncate">' + title + '</div>'
          + '<div class="text-[11px] text-slate-200 font-medium leading-tight">' + body + '</div>'
          + '<div class="text-[9px] text-amber-400/80 font-mono flex items-center gap-1 pt-1"><span>Tap to open page &#x27A4;</span></div>'
          + '</div>';
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 8000);
      } catch(e) {}
    }

    async function initFcmPush() {
      try {
        if (typeof firebase === 'undefined') {
          console.warn('[FCM] Firebase SDK not loaded');
          return;
        }

        if (typeof firebase.messaging.isSupported === 'function') {
          const supported = await firebase.messaging.isSupported();
          if (!supported) return;
        }

        if (!('serviceWorker' in navigator) || !('Notification' in window)) return;

        if (!firebase.apps.length) {
          firebase.initializeApp(firebaseConfig);
        }
        const messaging = firebase.messaging();
        globalFcmMessaging = messaging;

        const reg = await navigator.serviceWorker.register('/app/firebase-messaging-sw.js?v=2.0.5', { scope: '/app/' });
        const readyReg = await navigator.serviceWorker.ready;
        globalFcmReg = readyReg || reg;

        if (window.isLoggedIn && window.sessionUserPhone) {
          if (Notification.permission === 'default') {
            try {
              const perm = await Notification.requestPermission();
              if (perm === 'granted') fetchAndSaveFcmToken(messaging, globalFcmReg);
            } catch(e) {}
          } else if (Notification.permission === 'granted') {
            if (window.fcmTokenStored) {
              fetch('/app/api.php?action=save_fcm_token', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                  action: 'save_fcm_token',
                  fcm_token: window.fcmTokenStored,
                  user_mobile: window.sessionUserPhone,
                  user_email: window.sessionUserEmail || '',
                  role: window.sessionUserRole || 'user'
                })
              }).catch(() => {});
            }
            fetchAndSaveFcmToken(messaging, globalFcmReg);
          }
        }

        messaging.onMessage((payload) => {
          console.log('[FCM Client] Foreground push received via SDK:', payload);
          const title = payload.notification?.title || payload.data?.title || '\uD83D\uDC85 PAVANCAB Alert';
          const body = payload.notification?.body || payload.data?.body || 'New ride update received!';
          const url = payload.data?.url || payload.data?.click_action || '/app/rides.php';
          const evtType = payload.data?.event_type || payload.data?.status || 'default';
          showBrowserNotification(title, body, url, evtType);
        });

        if ('serviceWorker' in navigator) {
          navigator.serviceWorker.addEventListener('message', (event) => {
            if (event.data && (event.data.type === 'FCM_PUSH_RECEIVED' || event.data.title)) {
              const title = event.data.title || '\uD83D\uDC85 PAVANCAB Alert';
              const body = event.data.body || 'New ride update received!';
              const url = event.data.url || event.data.click_action || '/app/rides.php';
              const evtType = event.data.event_type || 'default';
              showBrowserNotification(title, body, url, evtType);
            }
          });
        }

        setInterval(() => {
          if (Notification.permission === 'granted') {
            fetchAndSaveFcmToken(messaging, globalFcmReg);
          }
        }, 120000);

      } catch(err) {
        console.warn('FCM Push Init warning:', err);
      }
    }

    function urlB64ToUint8Array(base64String) {
      const padding = '='.repeat((4 - base64String.length % 4) % 4);
      const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
      const rawData = window.atob(base64);
      const outputArray = new Uint8Array(rawData.length);
      for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
      }
      return outputArray;
    }

    async function getNativeWebPushToken(reg) {
      try {
        if (!reg || !reg.pushManager) return null;
        const appServerKey = urlB64ToUint8Array(VAPID_KEY);
        let sub = await reg.pushManager.getSubscription();
        if (!sub) {
          sub = await reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: appServerKey });
        }
        if (sub && sub.endpoint) {
          const parts = sub.endpoint.split('/');
          const rawToken = parts[parts.length - 1];
          if (rawToken && rawToken.length > 50) return rawToken;
        }
      } catch(err) {}
      return null;
    }

    async function cleanFirebaseIndexedDBAndRetry(messaging, reg) {
      try {
        if ('indexedDB' in window) {
          try { indexedDB.deleteDatabase('firebase-installations-database'); } catch(e) {}
          try { indexedDB.deleteDatabase('firebase-messaging-database'); } catch(e) {}
          try { indexedDB.deleteDatabase('firebaseInstallations'); } catch(e) {}
          try { indexedDB.deleteDatabase('fcm_vapid_details'); } catch(e) {}
        }
        await new Promise(r => setTimeout(r, 200));
        let tok = null;
        if (reg) {
          try { tok = await messaging.getToken({ vapidKey: VAPID_KEY, serviceWorkerRegistration: reg }); } catch(e) {}
        }
        if (!tok) {
          try { tok = await messaging.getToken({ vapidKey: VAPID_KEY }); } catch(e) {}
        }
        if (!tok && reg) {
          tok = await getNativeWebPushToken(reg);
        }
        return tok;
      } catch(e) { return null; }
    }

    async function fetchAndSaveFcmToken(messaging, reg, customUser) {
      try {
        if (!messaging) messaging = globalFcmMessaging;
        if (!reg) reg = globalFcmReg;
        if (!messaging && !reg) return;

        let token = null;
        try {
          if (messaging && reg) {
            token = await messaging.getToken({ vapidKey: VAPID_KEY, serviceWorkerRegistration: reg });
          }
        } catch(tokErr1) {
          const errStr1 = (tokErr1.message || tokErr1.toString());
          if (errStr1.includes('version') || errStr1.includes('less than') || errStr1.includes('IndexedDB')) {
            token = await cleanFirebaseIndexedDBAndRetry(messaging, reg);
          }
        }

        if (!token && messaging) {
          try {
            token = await messaging.getToken({ vapidKey: VAPID_KEY });
          } catch(tokErr2) {
            const errStr2 = (tokErr2.message || tokErr2.toString());
            if (!token && (errStr2.includes('version') || errStr2.includes('less than') || errStr2.includes('IndexedDB'))) {
              token = await cleanFirebaseIndexedDBAndRetry(messaging, reg);
            }
          }
        }

        if (!token && reg) {
          token = await getNativeWebPushToken(reg);
        }

        if (token) {
          window.fcmTokenStored = token;
          try { localStorage.setItem('pavancab_fcm_token', token); } catch(e) {}

          const storedPhone = (function() { try { return localStorage.getItem('pavancab_user_phone') || ''; } catch(e) { return ''; } })();
          const storedEmail = (function() { try { return localStorage.getItem('pavancab_user_email') || ''; } catch(e) { return ''; } })();
          const mobile = customUser?.mobile || window.sessionUserPhone || window.currentUserPhone || (window.currentUser && (window.currentUser.mobile || window.currentUser.phone)) || storedPhone || '';
          const email = customUser?.email || window.sessionUserEmail || (window.currentUser && window.currentUser.email) || storedEmail || '';
          const role = customUser?.role || window.sessionUserRole || (window.currentUser && window.currentUser.role) || '';

          await fetch('/app/api.php?action=save_fcm_token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'save_fcm_token', fcm_token: token, user_mobile: mobile, user_email: email, role: role })
          });

          sendAppHeartbeat(1);
        }
      } catch(err) {
        console.warn('FCM Token sync error:', err);
      }
    }

    function sendAppHeartbeat(isOnline) {
      const storedPhone = (function() { try { return localStorage.getItem('pavancab_user_phone') || ''; } catch(e) { return ''; } })();
      const storedEmail = (function() { try { return localStorage.getItem('pavancab_user_email') || ''; } catch(e) { return ''; } })();
      const mobile = window.sessionUserPhone || window.currentUserPhone || (window.currentUser && (window.currentUser.mobile || window.currentUser.phone)) || storedPhone || '';
      const email = window.sessionUserEmail || (window.currentUser && window.currentUser.email) || storedEmail || '';
      const token = window.fcmTokenStored || (function() { try { return localStorage.getItem('pavancab_fcm_token') || ''; } catch(e) { return ''; } })() || '';
      if (!mobile && !email && !token) return;

      const apiUrl = '/app/api.php?action=heartbeat';
      const payload = JSON.stringify({
        user_mobile: mobile,
        user_email: email,
        fcm_token: token,
        is_online: isOnline,
        device_info: navigator.userAgent
      });

      if (navigator.sendBeacon && isOnline === 0) {
        const blob = new Blob([payload], { type: 'application/json' });
        navigator.sendBeacon(apiUrl, blob);
      } else {
        fetch(apiUrl, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: payload,
          keepalive: true
        }).catch(() => {});
      }
    }

    sendAppHeartbeat(1);
    setInterval(() => {
      if (document.visibilityState === 'visible') sendAppHeartbeat(1);
    }, 30000);

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        sendAppHeartbeat(1);
        if (Notification.permission === 'granted') fetchAndSaveFcmToken(globalFcmMessaging, globalFcmReg);
      } else {
        sendAppHeartbeat(0);
      }
    });

    window.addEventListener('beforeunload', () => { sendAppHeartbeat(0); });
    window.addEventListener('pagehide', () => { sendAppHeartbeat(0); });

    document.addEventListener('click', function(e) {
      const link = e.target.closest('a[href*="action=logout"]');
      if (link) {
        e.preventDefault();
        const token = window.fcmTokenStored || (function() { try { return localStorage.getItem('pavancab_fcm_token'); } catch(e) { return ''; } })() || '';
        window.fcmTokenStored = null;
        try { localStorage.removeItem('pavancab_fcm_token'); } catch(e) {}
        try { localStorage.removeItem('pavancab_user_phone'); } catch(e) {}
        try { localStorage.removeItem('pavancab_user_email'); } catch(e) {}

        if (globalFcmReg && globalFcmReg.pushManager) {
          try {
            globalFcmReg.pushManager.getSubscription().then(sub => {
              if (sub) sub.unsubscribe();
            }).catch(() => {});
          } catch(e) {}
        }

        if (globalFcmMessaging && typeof globalFcmMessaging.deleteToken === 'function') {
          try { globalFcmMessaging.deleteToken(); } catch(e) {}
        }
        sendAppHeartbeat(0);
        const href = link.getAttribute('href');
        const sep = href.includes('?') ? '&' : '?';
        window.location.href = href + sep + 'fcm_token=' + encodeURIComponent(token);
      }
    });

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
      initFcmPush();
    } else {
      document.addEventListener('DOMContentLoaded', initFcmPush);
    }
  </script>
</body>
</html>
