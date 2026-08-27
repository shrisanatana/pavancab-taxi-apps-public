<?php
/**
 * PAVANCAB GOA TAXI - Individual User Dossier & Custom FCM Push Console
 * Path: app/dashboard/user_detail.php
 * Access: Super Admin & Team Dispatchers Only
 */

$pageTitle = '👤 User Profile & FCM Push Console';
$activeTab = 'USERS';
require_once __DIR__ . '/_layout_header.php';

$phone = cleanPhoneDigits($_GET['phone'] ?? '');
$email = trim($_GET['email'] ?? '');
$userId = intval($_GET['id'] ?? 0);
$clean10 = $phone ? substr($phone, -10) : '';

$conn = db();

// 1. Fetch User Record
$userRow = null;
if ($userId > 0) {
    $r = dbRows("SELECT * FROM app_users WHERE id = ? LIMIT 1", 'i', [$userId]);
    if (!empty($r)) $userRow = $r[0];
}
if (!$userRow && $clean10) {
    $r = dbRows("SELECT * FROM app_users WHERE RIGHT(REPLACE(REPLACE(REPLACE(mobile, '+', ''), ' ', ''), '-', ''), 10) = ? LIMIT 1", 's', [$clean10]);
    if (!empty($r)) $userRow = $r[0];
}
if (!$userRow && $email) {
    $r = dbRows("SELECT * FROM app_users WHERE LOWER(email) = ? LIMIT 1", 's', [strtolower($email)]);
    if (!empty($r)) $userRow = $r[0];
}

// 2. Fetch FCM Tokens
$tokens = [];
if ($clean10 && $email) {
    $tokens = dbRows("SELECT * FROM app_fcm_tokens WHERE is_online = 1 AND user_mobile IS NOT NULL AND (RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = ? OR LOWER(user_email) = ?) ORDER BY updated_at DESC", 'ss', [$clean10, strtolower($email)]);
} elseif ($clean10) {
    $tokens = dbRows("SELECT * FROM app_fcm_tokens WHERE is_online = 1 AND user_mobile IS NOT NULL AND RIGHT(REPLACE(REPLACE(REPLACE(user_mobile, '+', ''), ' ', ''), '-', ''), 10) = ? ORDER BY updated_at DESC", 's', [$clean10]);
} elseif ($email) {
    $tokens = dbRows("SELECT * FROM app_fcm_tokens WHERE is_online = 1 AND user_email IS NOT NULL AND LOWER(user_email) = ? ORDER BY updated_at DESC", 's', [strtolower($email)]);
}

// 3. Fetch Complete Booking History
$bookings = [];
if ($clean10 && $email) {
    $bookings = dbRows("SELECT * FROM app_bookings WHERE RIGHT(REPLACE(REPLACE(REPLACE(customer_phone, '+', ''), ' ', ''), '-', ''), 10) = ? OR LOWER(user_email) = ? ORDER BY id DESC", 'ss', [$clean10, strtolower($email)]);
} elseif ($clean10) {
    $bookings = dbRows("SELECT * FROM app_bookings WHERE RIGHT(REPLACE(REPLACE(REPLACE(customer_phone, '+', ''), ' ', ''), '-', ''), 10) = ? ORDER BY id DESC", 's', [$clean10]);
} elseif ($email) {
    $bookings = dbRows("SELECT * FROM app_bookings WHERE LOWER(user_email) = ? ORDER BY id DESC", 's', [strtolower($email)]);
}

// Synthesize user info if not in app_users
if (!$userRow) {
    $firstBk = $bookings[0] ?? null;
    $userRow = [
        'id' => 0,
        'name' => $firstBk['customer_name'] ?? 'Passenger',
        'mobile' => $phone ?: ($firstBk['customer_phone'] ?? ''),
        'email' => $email ?: ($firstBk['user_email'] ?? ''),
        'role' => 'user',
        'is_online' => !empty($tokens[0]['is_online']) ? intval($tokens[0]['is_online']) : 0,
        'last_active_at' => $tokens[0]['last_active_at'] ?? ($firstBk['created_at'] ?? date('Y-m-d H:i:s')),
        'device_info' => $tokens[0]['device_info'] ?? '',
        'created_at' => $firstBk['created_at'] ?? date('Y-m-d H:i:s')
    ];
}

// Calculate Metrics
$totalSpent = 0;
$completedCount = 0;
$cancelledCount = 0;
foreach ($bookings as $b) {
    $c = classifyRideStatus($b['status']);
    if ($c === 'COMPLETED') {
        $completedCount++;
        $totalSpent += floatval($b['total_fare'] ?? 0);
    } elseif ($c === 'CANCELLED') {
        $cancelledCount++;
    } elseif ($c === 'IN_TRANSIT' || $c === 'CONFIRMED') {
        $totalSpent += floatval($b['total_fare'] ?? 0);
    }
}

$nowTs = time();
$lastTs = !empty($userRow['last_active_at']) ? strtotime($userRow['last_active_at']) : 0;
$isLiveOnline = (!empty($userRow['is_online']) && ($nowTs - $lastTs <= 300));
$userDisplayPhone = $userRow['mobile'] ?: $phone;
$userDisplayEmail = $userRow['email'] ?: $email;
$userDisplayName = $userRow['name'] ?: 'Goa Customer';
?>

<!-- NAVIGATION TOP BAR -->
<div class="flex items-center justify-between gap-4 pb-2 border-b border-slate-800">
  <div class="flex items-center gap-3">
    <a href="./users.php" class="bg-slate-900 hover:bg-slate-800 text-slate-300 hover:text-white p-2.5 rounded-2xl border border-slate-700 transition flex items-center gap-1.5 text-xs font-bold shadow">
      <i data-lucide="arrow-left" class="w-4 h-4 text-amber-400"></i>
      <span>Back to Users</span>
    </a>
    <div>
      <h1 class="text-xl sm:text-2xl font-black text-white font-outfit uppercase tracking-tight flex items-center gap-2">
        <span><?php echo htmlspecialchars($userDisplayName); ?></span>
        <span class="text-xs font-bold px-2.5 py-0.5 rounded-full border <?php echo $isLiveOnline ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 animate-pulse' : 'bg-slate-800 text-slate-400 border-slate-700'; ?>" id="live-online-pill">
          <?php echo $isLiveOnline ? '🟢 App Opened (Online)' : '⚪ App Closed (Offline)'; ?>
        </span>
      </h1>
      <p class="text-xs text-slate-400">
        Customer Dossier • Push Notification Console • Ride History Roster
      </p>
    </div>
  </div>

  <div class="flex items-center gap-2">
    <?php if (!empty($clean10)): ?>
      <a href="tel:<?php echo htmlspecialchars($clean10); ?>" class="bg-slate-900 hover:bg-slate-800 border border-slate-700 text-amber-400 font-bold px-3 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow">
        <i data-lucide="phone" class="w-3.5 h-3.5"></i>
        <span class="hidden sm:inline">Call</span>
      </a>
      <a href="https://wa.me/<?php echo htmlspecialchars($clean10); ?>" target="_blank" class="bg-emerald-600 hover:bg-emerald-500 text-slate-950 font-black px-3 py-2 rounded-xl text-xs flex items-center gap-1.5 shadow">
        <i data-lucide="message-square" class="w-3.5 h-3.5"></i>
        <span class="hidden sm:inline">WhatsApp</span>
      </a>
    <?php endif; ?>
  </div>
</div>

<!-- 2-COLUMN MAIN GRID -->
<div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

  <!-- LEFT COLUMN: PROFILE & CUSTOM PUSH CENTER (5 cols) -->
  <div class="lg:col-span-5 space-y-6">

    <!-- CARD 1: CUSTOMER METRICS & PROFILE -->
    <div class="uber-card p-6 rounded-3xl border border-slate-800 space-y-5">
      <div class="flex items-center gap-4">
        <div class="relative">
          <div class="w-16 h-16 rounded-2xl bg-gradient-to-tr from-amber-500 to-amber-300 text-slate-950 flex items-center justify-center text-2xl font-black shadow-lg">
            <?php echo mb_substr($userDisplayName, 0, 1, 'UTF-8'); ?>
          </div>
          <span class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full border-2 border-slate-950 <?php echo $isLiveOnline ? 'bg-emerald-400 animate-pulse' : 'bg-slate-600'; ?>"></span>
        </div>

        <div class="space-y-1">
          <h2 class="font-extrabold text-white text-base font-outfit"><?php echo htmlspecialchars($userDisplayName); ?></h2>
          <div class="text-xs text-slate-400 font-mono">📞 <?php echo htmlspecialchars($userDisplayPhone ?: 'No Phone'); ?></div>
          <?php if (!empty($userDisplayEmail)): ?>
            <div class="text-xs text-slate-400 font-mono truncate max-w-[200px]">✉️ <?php echo htmlspecialchars($userDisplayEmail); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Quick Metrics Strip -->
      <div class="grid grid-cols-3 gap-2 bg-slate-950/80 p-3 rounded-2xl border border-slate-800/80 text-center">
        <div>
          <span class="text-[10px] text-slate-400 font-bold uppercase block">Rides</span>
          <span class="text-base font-black text-white font-outfit"><?php echo count($bookings); ?></span>
        </div>
        <div>
          <span class="text-[10px] text-slate-400 font-bold uppercase block">Completed</span>
          <span class="text-base font-black text-emerald-400 font-outfit"><?php echo $completedCount; ?></span>
        </div>
        <div>
          <span class="text-[10px] text-slate-400 font-bold uppercase block">Total Spent</span>
          <span class="text-base font-black text-amber-400 font-outfit">₹<?php echo number_format($totalSpent); ?></span>
        </div>
      </div>

      <!-- Details List -->
      <div class="space-y-2 text-xs">
        <div class="flex justify-between py-1 border-b border-slate-800">
          <span class="text-slate-400">Account Role:</span>
          <span class="text-white font-bold uppercase"><?php echo htmlspecialchars($userRow['role'] ?? 'Passenger'); ?></span>
        </div>
        <div class="flex justify-between py-1 border-b border-slate-800">
          <span class="text-slate-400">Last Active Time:</span>
          <span class="text-amber-300 font-bold"><?php echo htmlspecialchars($userRow['last_active_at'] ?? 'Never'); ?></span>
        </div>
        <div class="flex justify-between py-1 border-b border-slate-800">
          <span class="text-slate-400">Registered Devices:</span>
          <span class="text-white font-bold"><?php echo count($tokens); ?> Active FCM Tokens</span>
        </div>
        <div class="flex justify-between py-1">
          <span class="text-slate-400">Device/Browser:</span>
          <span class="text-slate-300 text-[11px] truncate max-w-[180px]" title="<?php echo htmlspecialchars($userRow['device_info'] ?? 'Web'); ?>">
            <?php echo htmlspecialchars(substr($userRow['device_info'] ?? 'Web App', 0, 25)); ?>
          </span>
        </div>
      </div>
    </div>

    <!-- CARD 2: CUSTOM FCM PUSH NOTIFICATION CONSOLE -->
    <div class="uber-card p-6 rounded-3xl border border-amber-500/30 bg-amber-950/10 space-y-5 shadow-xl">
      <div class="flex items-center justify-between border-b border-amber-500/20 pb-3">
        <div class="flex items-center gap-2">
          <div class="w-8 h-8 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-black">
            🔔
          </div>
          <div>
            <h3 class="text-sm font-black text-white font-outfit uppercase">Send Custom FCM Push</h3>
            <p class="text-[10px] text-amber-300/80">Delivered directly to user's logged-in device screen</p>
          </div>
        </div>
        <span class="text-[10px] bg-amber-400/20 text-amber-300 border border-amber-400/40 px-2 py-0.5 rounded-full font-black">
          HTTP v1 LIVE
        </span>
      </div>

      <!-- Quick Template Presets -->
      <div class="space-y-1.5">
        <label class="text-[10px] text-slate-400 font-bold uppercase">Quick Templates:</label>
        <div class="flex flex-wrap gap-1.5">
          <button type="button" onclick="setPushPreset('discount')" class="px-2 py-1 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-amber-300 rounded-lg text-[11px] font-bold">
            🎉 ₹200 OFF
          </button>
          <button type="button" onclick="setPushPreset('reminder')" class="px-2 py-1 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-indigo-300 rounded-lg text-[11px] font-bold">
            🚖 Cab Reminder
          </button>
          <button type="button" onclick="setPushPreset('vip')" class="px-2 py-1 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-emerald-300 rounded-lg text-[11px] font-bold">
            ⚡ VIP Priority
          </button>
        </div>
      </div>

      <form id="direct-push-form" onsubmit="handleSendDirectPush(event)" class="space-y-3.5 text-xs">
        <div class="space-y-1">
          <label class="font-bold text-slate-300">Notification Title *</label>
          <input type="text" id="dp-title" required value="🚕 PAVANCAB Alert for <?php echo htmlspecialchars($userDisplayName); ?>" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-bold focus:outline-none focus:border-amber-400">
        </div>

        <div class="space-y-1">
          <label class="font-bold text-slate-300">Message Body *</label>
          <textarea id="dp-body" rows="3" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-medium focus:outline-none focus:border-amber-400" placeholder="Enter push notification message..."><?php echo "Hello " . htmlspecialchars($userDisplayName) . ", your Goa cab is available with fixed rates and zero surge fees!"; ?></textarea>
        </div>

        <div class="space-y-1">
          <label class="font-bold text-slate-300">Action URL / Deep Link</label>
          <input type="text" id="dp-url" value="/app/rides.php" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono text-xs focus:outline-none focus:border-amber-400">
        </div>

        <div id="dp-result-box" class="text-xs font-bold"></div>

        <button type="submit" id="dp-submit-btn" class="w-full gradient-btn-gold text-slate-950 font-black py-2.5 rounded-xl uppercase shadow-lg flex items-center justify-center gap-2">
          <i data-lucide="send" class="w-4 h-4"></i>
          <span>🚀 Dispatch Custom Push Now</span>
        </button>
      </form>
    </div>

    <!-- CARD 3: REGISTERED FCM DEVICE TOKENS ROSTER -->
    <div class="uber-card p-6 rounded-3xl border border-slate-800 space-y-4">
      <div class="flex items-center justify-between border-b border-slate-800 pb-2">
        <h3 class="text-xs font-black text-white uppercase font-outfit flex items-center gap-1.5">
          <i data-lucide="smartphone" class="w-3.5 h-3.5 text-amber-400"></i>
          <span>Registered FCM Devices (<?php echo count($tokens); ?>)</span>
        </h3>
        <span class="text-[10px] text-slate-400">Firebase Tokens</span>
      </div>

      <?php if (empty($tokens)): ?>
        <div class="p-4 rounded-2xl bg-slate-950 border border-dashed border-slate-800 text-center text-xs text-slate-400">
          No device push tokens registered yet for this user.
        </div>
      <?php else: ?>
        <div class="space-y-2 max-h-56 overflow-y-auto custom-scrollbar">
          <?php foreach ($tokens as $idx => $t): ?>
            <div class="p-3 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-1.5 text-xs">
              <div class="flex items-center justify-between">
                <span class="font-bold text-white flex items-center gap-1.5">
                  <span class="w-2 h-2 rounded-full <?php echo (!empty($t['is_online']) ? 'bg-emerald-400' : 'bg-slate-600'); ?>"></span>
                  Device #<?php echo $idx + 1; ?>
                </span>
                <span class="text-[10px] text-slate-400 font-mono"><?php echo htmlspecialchars(substr($t['updated_at'], 0, 16)); ?></span>
              </div>
              <div class="font-mono text-[10px] text-slate-400 truncate bg-slate-900 p-1.5 rounded-lg">
                <?php echo htmlspecialchars(substr($t['fcm_token'], 0, 30)) . '...' . htmlspecialchars(substr($t['fcm_token'], -15)); ?>
              </div>
              <div class="flex items-center justify-between text-[10px] text-slate-400 pt-1">
                <span><?php echo htmlspecialchars(substr($t['device_info'] ?: 'Browser Web Push', 0, 30)); ?></span>
                <button type="button" onclick="pingSingleToken('<?php echo htmlspecialchars($t['fcm_token']); ?>')" class="text-amber-400 hover:text-amber-300 font-bold">
                  ⚡ Test Ping
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

  <!-- RIGHT COLUMN: COMPLETE RIDE HISTORY ROSTER (7 cols) -->
  <div class="lg:col-span-7 space-y-6">

    <div class="uber-card p-6 rounded-3xl border border-slate-800 space-y-5">
      <div class="flex items-center justify-between border-b border-slate-800 pb-3">
        <div>
          <h2 class="text-base font-black text-white font-outfit uppercase">Complete Ride History (<?php echo count($bookings); ?>)</h2>
          <p class="text-xs text-slate-400">All rides booked by this customer across the platform</p>
        </div>
        <span class="text-xs font-black text-amber-400 bg-amber-500/10 px-3 py-1 rounded-xl border border-amber-500/20">
          ₹<?php echo number_format($totalSpent); ?> Total Spend
        </span>
      </div>

      <?php if (empty($bookings)): ?>
        <div class="p-12 text-center border border-dashed border-slate-800 rounded-3xl space-y-2">
          <div class="text-3xl">🚖</div>
          <h3 class="text-sm font-bold text-white">No Bookings Recorded</h3>
          <p class="text-xs text-slate-400">This user has not booked any rides yet.</p>
        </div>
      <?php else: ?>
        <div class="space-y-4">
          <?php foreach ($bookings as $b): 
            $statusCat = classifyRideStatus($b['status']);
            $statusBadge = 'bg-slate-800 text-slate-300 border-slate-700';
            if ($statusCat === 'COMPLETED') $statusBadge = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40';
            elseif ($statusCat === 'CANCELLED') $statusBadge = 'bg-red-500/20 text-red-300 border-red-500/40';
            elseif ($statusCat === 'IN_TRANSIT') $statusBadge = 'bg-yellow-500/20 text-yellow-300 border-yellow-500/40';
            elseif ($statusCat === 'CONFIRMED') $statusBadge = 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40';
            elseif ($statusCat === 'PENDING') $statusBadge = 'bg-red-600 text-white font-black';
          ?>
            <div class="p-4 sm:p-5 rounded-2xl bg-slate-950 border border-slate-800/80 space-y-3 transition hover:border-slate-700">
              
              <!-- TOP ROW: REF & STATUS -->
              <div class="flex items-start justify-between gap-2">
                <div>
                  <div class="flex items-center gap-2">
                    <span class="font-mono font-black text-amber-400 text-sm">#<?php echo htmlspecialchars($b['booking_ref']); ?></span>
                    <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-lg border <?php echo $statusBadge; ?>">
                      <?php echo htmlspecialchars($b['status']); ?>
                    </span>
                  </div>
                  <p class="text-[10px] text-slate-400 mt-0.5">
                    📅 <?php echo htmlspecialchars($b['pickup_date'] . ' ' . $b['pickup_time']); ?> • Created: <?php echo htmlspecialchars(substr($b['created_at'], 0, 16)); ?>
                  </p>
                </div>

                <div class="text-right">
                  <div class="text-base font-black text-amber-400 font-outfit">₹<?php echo number_format(floatval($b['total_fare'])); ?></div>
                  <div class="text-[10px] text-slate-400 uppercase font-bold"><?php echo htmlspecialchars($b['cab_type'] ?: 'Sedan'); ?></div>
                </div>
              </div>

              <!-- ROUTE STRIP -->
              <div class="bg-slate-900/60 p-3 rounded-xl border border-slate-800/60 space-y-1.5 text-xs">
                <div class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0"></span>
                  <span class="text-slate-300 font-semibold truncate"><?php echo htmlspecialchars($b['pickup_location']); ?></span>
                </div>
                <div class="flex items-center gap-2">
                  <span class="w-2 h-2 rounded-full bg-red-400 shrink-0"></span>
                  <span class="text-slate-300 font-semibold truncate"><?php echo htmlspecialchars($b['drop_location']); ?></span>
                </div>
              </div>

              <!-- DRIVER & RATING ROW -->
              <div class="flex items-center justify-between text-xs pt-1">
                <div class="text-slate-400">
                  <?php if (!empty($b['driver_name'])): ?>
                    <span>Driver: <strong class="text-white"><?php echo htmlspecialchars($b['driver_name']); ?></strong> (<?php echo htmlspecialchars($b['vehicle_number'] ?: 'Goa Cab'); ?>)</span>
                  <?php else: ?>
                    <span class="text-slate-500 italic">No driver assigned</span>
                  <?php endif; ?>
                </div>

                <div class="flex items-center gap-2">
                  <?php if (!empty($b['user_rating'])): ?>
                    <span class="text-amber-400 font-bold text-xs">⭐ <?php echo $b['user_rating']; ?>/5</span>
                  <?php endif; ?>
                  <a href="../receipt.php?booking_ref=<?php echo urlencode($b['booking_ref']); ?>" target="_blank" class="bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white px-2.5 py-1 rounded-lg text-[10px] font-bold transition">
                    View Slip 🧾
                  </a>
                </div>
              </div>

            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>

</div>

<script>
function setPushPreset(preset) {
  const titleEl = document.getElementById('dp-title');
  const bodyEl = document.getElementById('dp-body');
  const urlEl = document.getElementById('dp-url');
  const userName = <?php echo json_encode($userDisplayName); ?>;

  if (preset === 'discount') {
    titleEl.value = `🎉 Exclusive ₹200 OFF for ${userName}!`;
    bodyEl.value = `Hello ${userName}, book your Goa cab today and get ₹200 flat discount with coupon GOA200.`;
    urlEl.value = '/app/index.php?coupon=GOA200';
  } else if (preset === 'reminder') {
    titleEl.value = `🚖 Ready for your next Goa trip, ${userName}?`;
    bodyEl.value = `Instant cab booking with verified Goa drivers, AC cabs, and 24/7 flight delay tracking.`;
    urlEl.value = '/app/index.php';
  } else if (preset === 'vip') {
    titleEl.value = `⚡ VIP Priority Dispatch for ${userName}`;
    bodyEl.value = `Priority vehicle dispatched on demand with zero wait time. Enjoy seamless travel in Goa!`;
    urlEl.value = '/app/rides.php';
  }
}

async function handleSendDirectPush(e) {
  e.preventDefault();
  const phone = <?php echo json_encode($clean10); ?>;
  const email = <?php echo json_encode($userDisplayEmail); ?>;
  const title = document.getElementById('dp-title').value.trim();
  const body = document.getElementById('dp-body').value.trim();
  const url = document.getElementById('dp-url').value.trim();
  const resultBox = document.getElementById('dp-result-box');
  const btn = document.getElementById('dp-submit-btn');

  btn.disabled = true;
  btn.innerHTML = '<i data-lucide="loader-2" class="w-4 h-4 animate-spin"></i> <span>Sending via FCM v1...</span>';
  resultBox.innerHTML = '<span class="text-amber-400 font-bold">Contacting Google FCM Gateway...</span>';

  try {
    const res = await fetch('../api_dashboard.php?action=send-custom-fcm', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        target_phone: phone,
        target_email: email,
        title: title,
        body: body,
        click_action: url
      })
    });
    const data = await res.json();
    if (data.success) {
      resultBox.innerHTML = `<div class="p-3 bg-emerald-500/10 border border-emerald-500/30 rounded-xl text-emerald-400 font-black">✓ ${data.message}</div>`;
    } else {
      resultBox.innerHTML = `<div class="p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 font-bold">❌ ${data.error || 'Failed to send notification'}</div>`;
    }
  } catch(err) {
    resultBox.innerHTML = '<div class="p-3 bg-red-500/10 border border-red-500/30 rounded-xl text-red-400 font-bold">❌ Network Error</div>';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> <span>🚀 Dispatch Custom Push Now</span>';
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }
}

async function pingSingleToken(token) {
  if (!confirm('Send instant test FCM ping to this device token?')) return;
  try {
    const res = await fetch('../api_dashboard.php?action=send-custom-fcm', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        target_token: token,
        title: '⚡ PAVANCAB Device Test Ping',
        body: 'Your device is successfully connected to Pavan Cab real-time push service!',
        click_action: '/app/index.php'
      })
    });
    const data = await res.json();
    alert(data.success ? `✓ Ping delivered successfully to device!` : `❌ Error: ${data.error}`);
  } catch(e) {
    alert('❌ Network connection error');
  }
}

// Live polling for this specific user's online state & FCM devices
async function checkUserOnlineStatusLive() {
  try {
    const res = await fetch('../api_dashboard.php?action=user-detail&phone=' + encodeURIComponent(<?php echo json_encode($clean10); ?>) + '&email=' + encodeURIComponent(<?php echo json_encode($userDisplayEmail); ?>));
    const data = await res.json();
    if (data && data.user) {
      const isOnline = (data.user.live_app_status === 'ONLINE_OPEN');
      const pill = document.getElementById('live-online-pill');
      if (pill) {
        pill.className = 'text-xs font-bold px-2.5 py-0.5 rounded-full border ' + (isOnline ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40 animate-pulse' : 'bg-slate-800 text-slate-400 border-slate-700');
        pill.innerText = isOnline ? '🟢 App Opened (Online)' : '⚪ App Closed (Offline)';
      }
      const tokCount = document.getElementById('stat-fcm-count');
      if (tokCount && data.fcm_tokens) {
        tokCount.innerText = data.fcm_tokens.length;
      }
    }
  } catch(e){}
}

setInterval(checkUserOnlineStatusLive, 3000);
</script>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
