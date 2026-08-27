<?php
/**
 * PAVANCAB GOA TAXI - Uber-Style Pure PHP Header Template
 * Path: app/header.php
 */
require_once __DIR__ . '/db.php';
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: Sat, 26 Jul 1997 05:00:00 GMT");

$activePage  = $activePage ?? 'home';
$currentUser = $_SESSION['user'] ?? null;
$isPrivileged = ($currentUser && in_array($currentUser['role'] ?? '', ['admin', 'team']));
?>
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
    window.currentUser = <?php echo json_encode($currentUser); ?>;
    window.isUserLoggedIn = <?php echo (!empty($currentUser) ? 'true' : 'false'); ?>;
    window.currentUserPhone = "<?php echo htmlspecialchars($currentUser['mobile'] ?? ''); ?>";
    window.currentUserName = "<?php echo htmlspecialchars($currentUser['name'] ?? ''); ?>";
  </script>
</head>
<body class="min-h-screen flex flex-col bg-[#070a12] text-slate-100 antialiased selection:bg-amber-400 selection:text-slate-950 pavan-wind-glow">

  <!-- TOP BRAND & NAVIGATION BAR -->
  <header class="sticky top-0 z-40 uber-card border-b border-slate-800/80 px-3 sm:px-6 py-3 shadow-2xl backdrop-blur-xl">
    <div class="max-w-7xl mx-auto flex items-center justify-between gap-2">
      <!-- BRAND LOGO -->
      <a href="./index.php" class="flex items-center gap-2.5 group">
        <div class="w-10 h-10 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center p-1 shadow-lg transition group-hover:scale-105">
          <img src="./logo-pavancab.png" alt="PAVANCAB Logo" class="w-full h-full object-contain" onError="this.onerror=null; this.src='https://pavancab.com/logo-pavancab.png';">
        </div>
        <div>
          <span class="text-lg sm:text-xl font-black text-white tracking-wider font-outfit uppercase flex items-center gap-1">
            PAVAN<span class="bg-gradient-to-r from-amber-400 via-amber-300 to-sky-400 bg-clip-text text-transparent">CAB</span>
            <span class="hidden sm:inline-block text-[9px] bg-sky-500/20 text-sky-300 border border-sky-400/30 px-1.5 py-0.5 rounded font-black tracking-normal">💨 WIND SPEED</span>
          </span>
          <span class="block text-[9px] sm:text-[10px] font-bold text-amber-400/90 tracking-widest uppercase -mt-0.5">SHIELD OF SAFETY • GOA</span>
        </div>
      </a>

      <!-- NAVIGATION TABS -->
      <nav class="hidden md:flex items-center gap-2 bg-slate-900/90 p-1.5 rounded-2xl border border-slate-800">
        <a href="./index.php" class="px-5 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 uppercase tracking-wider <?php echo $activePage === 'home' ? 'bg-amber-400 text-slate-950 shadow-md font-extrabold' : 'text-slate-300 hover:text-white hover:bg-slate-800/70'; ?>">
          <i data-lucide="navigation" class="w-4 h-4"></i> Request Ride
        </a>
        <a href="./rides.php" class="px-5 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 uppercase tracking-wider <?php echo $activePage === 'rides' ? 'bg-amber-400 text-slate-950 shadow-md font-extrabold' : 'text-slate-300 hover:text-white hover:bg-slate-800/70'; ?>">
          <i data-lucide="clock" class="w-4 h-4"></i> My Rides & Radar
        </a>

        <?php if ($isPrivileged): ?>
          <a href="./dashboard/index.php" class="px-5 py-2 rounded-xl text-xs font-black transition flex items-center gap-2 uppercase tracking-wider <?php echo $activePage === 'dashboard' ? 'bg-gradient-to-r from-indigo-600 to-violet-600 text-white shadow-md font-extrabold' : 'text-indigo-300 hover:text-white hover:bg-indigo-950/40 border border-indigo-500/30'; ?>">
            <i data-lucide="shield" class="w-4 h-4 text-amber-400"></i> Dispatch Tower
          </a>
        <?php endif; ?>
      </nav>

      <!-- USER PROFILE & LOGIN BUTTON -->
      <div class="flex items-center gap-2 sm:gap-3">
        <?php if ($currentUser): ?>
          <div class="flex items-center gap-2 bg-slate-900/90 border border-slate-800 px-2.5 sm:px-3.5 py-1.5 rounded-2xl shadow-inner">
            <?php if (($currentUser['role'] ?? '') === 'admin'): ?>
              <span class="badge-admin text-[10px] font-black uppercase px-2 py-0.5 rounded-full flex items-center gap-1 shadow-sm">
                👑 Super Admin
              </span>
            <?php elseif (($currentUser['role'] ?? '') === 'team'): ?>
              <span class="badge-team text-[10px] font-black uppercase px-2 py-0.5 rounded-full flex items-center gap-1 shadow-sm">
                🛡️ Team
              </span>
            <?php else: ?>
              <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
            <?php endif; ?>

            <span class="text-xs font-extrabold text-slate-200 hidden sm:inline-block"><?php echo htmlspecialchars($currentUser['name'] ?? $currentUser['mobile'] ?? 'User'); ?></span>
            
            <a href="./auth.php?action=logout" title="Logout" class="text-xs text-slate-400 hover:text-red-400 transition p-1 hover:bg-red-500/10 rounded-lg">
              <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
            </a>
          </div>
        <?php else: ?>
          <button id="header-login-btn" onclick="openAuthModal()" class="gradient-btn-gold text-slate-950 font-black text-xs px-3 sm:px-4 py-2 rounded-xl shadow-lg flex items-center gap-1.5 uppercase">
            <i data-lucide="message-square" class="w-4 h-4"></i> Login
          </button>
        <?php endif; ?>
      </div>
    </div>
  </header>

  <!-- MAIN VIEW CONTENT -->
  <main class="flex-grow max-w-7xl mx-auto w-full px-4 lg:px-8 py-6 sm:py-10">
