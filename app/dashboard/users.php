<?php
/**
 * PAVANCAB GOA TAXI - App Users Directory & Real-time Live Radar
 * Path: app/dashboard/users.php
 * Access: Super Admin & Team Dispatchers Only
 */

$pageTitle = '👥 App Users Directory • Live Online/Offline Radar';
$activeTab = 'USERS';
require_once __DIR__ . '/_layout_header.php';

// Fetch users list from backend API logic
$conn = db();
$usersMap = [];

$resUsers = $conn->query("SELECT id, name, mobile, email, role, fcm_token, is_online, last_active_at, device_info, created_at FROM app_users ORDER BY id DESC");
if ($resUsers) {
    while ($u = $resUsers->fetch_assoc()) {
        $rawPhone = $u['mobile'] ?: '';
        $clean10 = substr(preg_replace('/\D/', '', $rawPhone), -10);
        $key = $clean10 ?: ('email_' . strtolower(trim($u['email'] ?? '')));
        if (!$key || $key === 'email_') $key = 'user_' . $u['id'];

        $usersMap[$key] = [
            'user_id' => intval($u['id']),
            'name' => $u['name'] ?: 'Goa Customer',
            'mobile' => $u['mobile'] ?: '',
            'clean_phone' => $clean10,
            'email' => $u['email'] ?: '',
            'role' => $u['role'] ?: 'user',
            'is_online' => intval($u['is_online'] ?? 0),
            'last_active_at' => $u['last_active_at'] ?: $u['created_at'],
            'device_info' => $u['device_info'] ?: '',
            'created_at' => $u['created_at'],
            'total_bookings' => 0,
            'completed_bookings' => 0,
            'cancelled_bookings' => 0,
            'total_spent' => 0,
            'fcm_tokens_count' => !empty($u['fcm_token']) ? 1 : 0,
            'latest_booking_ref' => '',
            'latest_booking_date' => ''
        ];
    }
}

$resFcm = $conn->query("SELECT fcm_token, user_email, user_mobile, is_online, last_active_at, device_info, updated_at FROM app_fcm_tokens ORDER BY updated_at DESC");
if ($resFcm) {
    while ($f = $resFcm->fetch_assoc()) {
        $rawPhone = $f['user_mobile'] ?: '';
        $clean10 = substr(preg_replace('/\D/', '', $rawPhone), -10);
        $key = $clean10 ?: ('email_' . strtolower(trim($f['user_email'] ?? '')));
        if (!$key || $key === 'email_') continue;

        if (!isset($usersMap[$key])) {
            $usersMap[$key] = [
                'user_id' => 0,
                'name' => 'Goa App User',
                'mobile' => $f['user_mobile'] ?: '',
                'clean_phone' => $clean10,
                'email' => $f['user_email'] ?: '',
                'role' => 'user',
                'is_online' => intval($f['is_online'] ?? 0),
                'last_active_at' => $f['last_active_at'] ?: $f['updated_at'],
                'device_info' => $f['device_info'] ?: '',
                'created_at' => $f['updated_at'],
                'total_bookings' => 0,
                'completed_bookings' => 0,
                'cancelled_bookings' => 0,
                'total_spent' => 0,
                'fcm_tokens_count' => 1,
                'latest_booking_ref' => '',
                'latest_booking_date' => ''
            ];
        } else {
            $usersMap[$key]['fcm_tokens_count']++;
            if (!empty($f['is_online'])) $usersMap[$key]['is_online'] = 1;
            if (!empty($f['last_active_at']) && (empty($usersMap[$key]['last_active_at']) || strtotime($f['last_active_at']) > strtotime($usersMap[$key]['last_active_at']))) {
                $usersMap[$key]['last_active_at'] = $f['last_active_at'];
            }
            if (!empty($f['device_info']) && empty($usersMap[$key]['device_info'])) {
                $usersMap[$key]['device_info'] = $f['device_info'];
            }
        }
    }
}

$resBk = $conn->query("SELECT id, booking_ref, customer_name, customer_phone, user_email, total_fare, status, created_at, pickup_date FROM app_bookings ORDER BY id DESC");
if ($resBk) {
    while ($bRow = $resBk->fetch_assoc()) {
        $rawPhone = $bRow['customer_phone'] ?: '';
        $clean10 = substr(preg_replace('/\D/', '', $rawPhone), -10);
        $key = $clean10 ?: ('email_' . strtolower(trim($bRow['user_email'] ?? '')));
        if (!$key || $key === 'email_') continue;

        if (!isset($usersMap[$key])) {
            $usersMap[$key] = [
                'user_id' => 0,
                'name' => $bRow['customer_name'] ?: 'Passenger',
                'mobile' => $bRow['customer_phone'] ?: '',
                'clean_phone' => $clean10,
                'email' => $bRow['user_email'] ?: '',
                'role' => 'user',
                'is_online' => 0,
                'last_active_at' => $bRow['created_at'],
                'device_info' => '',
                'created_at' => $bRow['created_at'],
                'total_bookings' => 0,
                'completed_bookings' => 0,
                'cancelled_bookings' => 0,
                'total_spent' => 0,
                'fcm_tokens_count' => 0,
                'latest_booking_ref' => $bRow['booking_ref'],
                'latest_booking_date' => $bRow['created_at']
            ];
        }

        $usersMap[$key]['total_bookings']++;
        $uKey = classifyRideStatus($bRow['status']);
        if ($uKey === 'COMPLETED') {
            $usersMap[$key]['completed_bookings']++;
            $usersMap[$key]['total_spent'] += floatval($bRow['total_fare'] ?? 0);
        } elseif ($uKey === 'CANCELLED') {
            $usersMap[$key]['cancelled_bookings']++;
        } elseif ($uKey === 'IN_TRANSIT' || $uKey === 'CONFIRMED') {
            $usersMap[$key]['total_spent'] += floatval($bRow['total_fare'] ?? 0);
        }

        if (empty($usersMap[$key]['latest_booking_ref'])) {
            $usersMap[$key]['latest_booking_ref'] = $bRow['booking_ref'];
            $usersMap[$key]['latest_booking_date'] = $bRow['created_at'];
        }
        if ($usersMap[$key]['name'] === 'Goa Customer' || $usersMap[$key]['name'] === 'Goa App User') {
            if (!empty($bRow['customer_name'])) $usersMap[$key]['name'] = $bRow['customer_name'];
        }
    }
}

$nowTs = time();
$onlineCount = 0;
$fcmActiveCount = 0;
$totalSpentAll = 0;
$usersList = array_values($usersMap);

foreach ($usersList as &$uObj) {
    $lastTs = !empty($uObj['last_active_at']) ? strtotime($uObj['last_active_at']) : 0;
    if ($uObj['is_online'] && ($nowTs - $lastTs <= 300)) {
        $uObj['live_app_status'] = 'ONLINE_OPEN';
        $onlineCount++;
    } else {
        $uObj['live_app_status'] = 'OFFLINE_CLOSED';
        $uObj['is_online'] = 0;
    }
    if ($uObj['fcm_tokens_count'] > 0) $fcmActiveCount++;
    $totalSpentAll += $uObj['total_spent'];
}

usort($usersList, function($a, $b) {
    if ($a['is_online'] !== $b['is_online']) {
        return $b['is_online'] - $a['is_online'];
    }
    return strtotime($b['last_active_at'] ?? '1970-01-01') - strtotime($a['last_active_at'] ?? '1970-01-01');
});
?>

<!-- USERS DIRECTORY HEADER & METRICS STRIP -->
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
  <div class="uber-card p-4 rounded-2xl border border-slate-800 space-y-1">
    <div class="flex items-center justify-between">
      <span class="text-[10px] uppercase font-black tracking-wider text-slate-400">Total Users</span>
      <i data-lucide="users" class="w-4 h-4 text-slate-500"></i>
    </div>
    <div class="text-2xl font-black text-white font-outfit" id="metric-total-users"><?php echo count($usersList); ?></div>
  </div>

  <div class="uber-card p-4 rounded-2xl border border-emerald-500/30 bg-emerald-950/20 space-y-1">
    <div class="flex items-center justify-between">
      <span class="text-[10px] uppercase font-black tracking-wider text-emerald-400">🟢 App Opened (Online)</span>
      <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
    </div>
    <div class="text-2xl font-black text-emerald-400 font-outfit" id="metric-online-users"><?php echo $onlineCount; ?></div>
  </div>

  <div class="uber-card p-4 rounded-2xl border border-amber-500/30 bg-amber-950/20 space-y-1">
    <div class="flex items-center justify-between">
      <span class="text-[10px] uppercase font-black tracking-wider text-amber-400">🔔 Push Notifications Active</span>
      <i data-lucide="bell-ring" class="w-4 h-4 text-amber-400"></i>
    </div>
    <div class="text-2xl font-black text-amber-400 font-outfit" id="metric-fcm-users"><?php echo $fcmActiveCount; ?></div>
  </div>

  <div class="uber-card p-4 rounded-2xl border border-slate-800 space-y-1">
    <div class="flex items-center justify-between">
      <span class="text-[10px] uppercase font-black tracking-wider text-slate-400">Lifetime Revenue</span>
      <i data-lucide="wallet" class="w-4 h-4 text-amber-400"></i>
    </div>
    <div class="text-2xl font-black text-amber-400 font-outfit">₹<?php echo number_format($totalSpentAll); ?></div>
  </div>
</div>

<!-- ACTIONS & SEARCH STRIP -->
<div class="flex flex-wrap items-center justify-between gap-3 bg-slate-900/60 p-4 rounded-2xl border border-slate-800">
  <div class="flex items-center gap-2 flex-wrap">
    <div class="relative w-full sm:w-64">
      <input type="text" id="user-search-input" onkeyup="filterUsersFeed()" placeholder="Search name, phone, email, device..." class="w-full bg-slate-950 border border-slate-800 rounded-xl pl-9 pr-3 py-2 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-amber-400">
      <i data-lucide="search" class="w-4 h-4 text-slate-400 absolute left-3 top-2.5"></i>
    </div>

    <!-- Filter chips -->
    <div class="flex items-center gap-1.5 overflow-x-auto custom-scrollbar">
      <button type="button" onclick="setUserFilter('ALL')" id="filter-btn-ALL" class="user-filter-chip px-3 py-1.5 rounded-xl font-bold text-xs bg-amber-400 text-slate-950 shadow">
        All (<span id="chip-count-ALL"><?php echo count($usersList); ?></span>)
      </button>
      <button type="button" onclick="setUserFilter('ONLINE')" id="filter-btn-ONLINE" class="user-filter-chip px-3 py-1.5 rounded-xl font-bold text-xs bg-slate-950 text-emerald-400 border border-emerald-500/30 hover:bg-slate-800">
        🟢 App Open (<span id="chip-count-ONLINE"><?php echo $onlineCount; ?></span>)
      </button>
      <button type="button" onclick="setUserFilter('OFFLINE')" id="filter-btn-OFFLINE" class="user-filter-chip px-3 py-1.5 rounded-xl font-bold text-xs bg-slate-950 text-slate-400 border border-slate-800 hover:bg-slate-800">
        ⚪ App Closed (<span id="chip-count-OFFLINE"><?php echo count($usersList) - $onlineCount; ?></span>)
      </button>
      <button type="button" onclick="setUserFilter('FCM')" id="filter-btn-FCM" class="user-filter-chip px-3 py-1.5 rounded-xl font-bold text-xs bg-slate-950 text-amber-300 border border-amber-500/30 hover:bg-slate-800">
        🔔 Push Enabled (<span id="chip-count-FCM"><?php echo $fcmActiveCount; ?></span>)
      </button>
    </div>
  </div>

  <!-- Broadcast Action Button -->
  <button onclick="openBroadcastModal()" class="gradient-btn-gold text-slate-950 font-black text-xs px-4 py-2.5 rounded-xl uppercase flex items-center gap-1.5 shadow-lg">
    <i data-lucide="megaphone" class="w-4 h-4"></i>
    <span>📢 Broadcast Custom Push</span>
  </button>
</div>

<!-- USERS GRID FEED CONTAINER -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="users-feed-container">
  <?php foreach ($usersList as $u): 
    $isOnline = ($u['is_online'] === 1);
    $cleanPhone = $u['clean_phone'] ?: preg_replace('/\D/', '', $u['mobile']);
    $roleLabel = ($u['role'] === 'admin') ? '👑 Admin' : (($u['role'] === 'team') ? '🛡️ Team' : '🚖 Passenger');
    $searchStr = strtolower("{$u['name']} {$u['mobile']} {$u['email']} {$u['device_info']} {$cleanPhone}");
  ?>
    <div class="user-card uber-card p-5 rounded-3xl border <?php echo $isOnline ? 'border-emerald-500/40 bg-emerald-950/10 shadow-emerald-500/10 shadow-lg' : 'border-slate-800 bg-slate-900/30'; ?> space-y-4 transition hover:border-amber-400/50"
         id="user-card-<?php echo $cleanPhone ?: $u['user_id']; ?>"
         data-search="<?php echo htmlspecialchars($searchStr); ?>"
         data-online="<?php echo $isOnline ? '1' : '0'; ?>"
         data-fcm="<?php echo ($u['fcm_tokens_count'] > 0) ? '1' : '0'; ?>">

      <!-- TOP USER ROW -->
      <div class="flex items-start justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="relative">
            <div class="w-12 h-12 rounded-2xl bg-gradient-to-tr from-slate-800 to-slate-700 flex items-center justify-center text-xl font-black text-amber-400 border border-slate-700 shadow">
              <?php echo mb_substr($u['name'], 0, 1, 'UTF-8'); ?>
            </div>
            <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-slate-950 <?php echo $isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-slate-600'; ?>" title="<?php echo $isOnline ? 'App Open / Online Now' : 'App Closed / Offline'; ?>"></span>
          </div>

          <div class="space-y-0.5">
            <div class="flex items-center gap-1.5 flex-wrap">
              <h3 class="font-extrabold text-white text-sm font-outfit"><?php echo htmlspecialchars($u['name']); ?></h3>
              <span class="text-[9px] bg-slate-800 text-slate-300 font-bold px-1.5 py-0.5 rounded border border-slate-700 uppercase">
                <?php echo $roleLabel; ?>
              </span>
            </div>
            <p class="text-xs text-slate-400 font-mono font-semibold">
              📞 <?php echo htmlspecialchars($u['mobile'] ?: 'No phone'); ?>
            </p>
          </div>
        </div>

        <div class="text-right space-y-1">
          <span class="text-[10px] font-black uppercase px-2 py-0.5 rounded-lg border <?php echo $isOnline ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-slate-800 text-slate-400 border-slate-700'; ?>">
            <?php echo $isOnline ? '🟢 Open Now' : '⚪ Closed'; ?>
          </span>
          <div class="text-[10px] text-slate-400 font-bold">
            <?php echo ($u['fcm_tokens_count'] > 0) ? "🔔 {$u['fcm_tokens_count']} FCM Device" : '🔕 No Push Token'; ?>
          </div>
        </div>
      </div>

      <!-- METRICS & STATUS PILL -->
      <div class="bg-slate-950/80 p-3 rounded-2xl border border-slate-800/80 grid grid-cols-2 gap-2 text-xs">
        <div>
          <span class="text-[10px] text-slate-400 font-bold uppercase block">Rides Booked</span>
          <span class="text-white font-black"><?php echo $u['total_bookings']; ?> Rides</span>
          <span class="text-[10px] text-emerald-400 font-bold block">(₹<?php echo number_format($u['total_spent']); ?> spent)</span>
        </div>
        <div class="text-right">
          <span class="text-[10px] text-slate-400 font-bold uppercase block">Last Active</span>
          <span class="text-amber-300 font-bold text-[11px] block">
            <?php echo !empty($u['last_active_at']) ? htmlspecialchars(substr($u['last_active_at'], 0, 16)) : 'Never'; ?>
          </span>
          <span class="text-[9px] text-slate-400 truncate block max-w-[120px] ml-auto" title="<?php echo htmlspecialchars($u['device_info'] ?: 'Browser'); ?>">
            <?php echo htmlspecialchars(substr($u['device_info'] ?: 'Web App', 0, 20)); ?>
          </span>
        </div>
      </div>

      <!-- ACTION BUTTONS -->
      <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-800/80">
        <div class="flex items-center gap-1.5">
          <?php if (!empty($cleanPhone)): ?>
            <a href="tel:<?php echo htmlspecialchars($cleanPhone); ?>" class="bg-slate-800 hover:bg-slate-700 text-white font-bold p-2 rounded-xl text-xs flex items-center justify-center transition" title="Call Customer">
              <i data-lucide="phone" class="w-3.5 h-3.5 text-amber-400"></i>
            </a>
            <a href="https://wa.me/<?php echo htmlspecialchars($cleanPhone); ?>" target="_blank" class="bg-emerald-600 hover:bg-emerald-500 text-slate-950 font-bold p-2 rounded-xl text-xs flex items-center justify-center transition shadow" title="WhatsApp Message">
              <i data-lucide="message-square" class="w-3.5 h-3.5"></i>
            </a>
          <?php endif; ?>

          <button onclick="openCustomPushModal('<?php echo htmlspecialchars($cleanPhone); ?>', '<?php echo htmlspecialchars($u['name']); ?>', '<?php echo htmlspecialchars($u['email']); ?>')" class="bg-amber-500/10 hover:bg-amber-500/20 text-amber-300 border border-amber-500/30 text-xs font-black px-2.5 py-1.5 rounded-xl transition flex items-center gap-1" title="Send Direct Custom Push">
            <i data-lucide="bell" class="w-3.5 h-3.5"></i> Push
          </button>
        </div>

        <a href="./user_detail.php?phone=<?php echo urlencode($cleanPhone); ?>&email=<?php echo urlencode($u['email']); ?>" class="gradient-btn-gold text-slate-950 font-black text-xs px-3.5 py-1.5 rounded-xl uppercase flex items-center gap-1 shadow">
          <span>View Page</span>
          <i data-lucide="arrow-right" class="w-3 h-3"></i>
        </a>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<!-- DYNAMIC EMPTY STATE -->
<div id="users-empty-state" class="hidden uber-card p-12 rounded-3xl border border-dashed border-slate-800 text-center space-y-3 my-4">
  <div class="w-12 h-12 rounded-2xl bg-amber-500/10 text-amber-400 flex items-center justify-center mx-auto text-xl font-black">
    🔍
  </div>
  <h3 class="text-base font-bold text-white">No Users Found Matching Filter</h3>
  <p class="text-xs text-slate-400">Try adjusting your search keywords or switching filter chips.</p>
</div>

<!-- ========================================================= -->
<!-- MODAL: SEND CUSTOM FCM PUSH NOTIFICATION                  -->
<!-- ========================================================= -->
<div id="custom-push-modal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
  <div class="uber-card p-6 sm:p-8 rounded-3xl border border-amber-500/40 w-full max-w-lg space-y-5 shadow-2xl relative">
    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
      <div class="flex items-center gap-2">
        <div class="w-9 h-9 rounded-xl bg-amber-500/20 text-amber-400 flex items-center justify-center font-black">
          🔔
        </div>
        <div>
          <h2 class="text-base font-black text-white font-outfit uppercase">Send Custom FCM Push</h2>
          <p class="text-xs text-slate-400" id="push-target-subtitle">Target: Specific Customer</p>
        </div>
      </div>
      <button onclick="closeCustomPushModal()" class="text-slate-400 hover:text-white text-lg font-black">&times;</button>
    </div>

    <form id="custom-push-form" onsubmit="handleSendCustomPush(event)" class="space-y-4 text-xs">
      <input type="hidden" id="push-target-phone" value="">
      <input type="hidden" id="push-target-email" value="">
      <input type="hidden" id="push-broadcast-mode" value="single">

      <!-- Preset Quick Templates -->
      <div class="space-y-1.5">
        <label class="text-[10px] text-slate-400 font-bold uppercase">Quick Message Presets:</label>
        <div class="flex flex-wrap gap-1.5">
          <button type="button" onclick="applyPushPreset('discount')" class="px-2.5 py-1 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-amber-300 rounded-lg font-bold">
            🎉 ₹200 OFF Voucher
          </button>
          <button type="button" onclick="applyPushPreset('reminder')" class="px-2.5 py-1 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-indigo-300 rounded-lg font-bold">
            🚖 Book Goa Cab Reminder
          </button>
          <button type="button" onclick="applyPushPreset('airport')" class="px-2.5 py-1 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-emerald-300 rounded-lg font-bold">
            ✈️ Mopa Airport Cab Ready
          </button>
        </div>
      </div>

      <div class="space-y-1">
        <label class="font-bold text-slate-300">Notification Title *</label>
        <input type="text" id="push-title" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-bold focus:outline-none focus:border-amber-400" placeholder="e.g. 🚕 Special Goa Cab Offer For You!">
      </div>

      <div class="space-y-1">
        <label class="font-bold text-slate-300">Push Message Body *</label>
        <textarea id="push-body" rows="3" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-medium focus:outline-none focus:border-amber-400" placeholder="e.g. Book your taxi today and get fixed rates with zero surge pricing!"></textarea>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <div class="space-y-1">
          <label class="font-bold text-slate-300">Click Action URL</label>
          <input type="text" id="push-url" value="/app/rides.php" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono text-xs focus:outline-none focus:border-amber-400">
        </div>
        <div class="space-y-1">
          <label class="font-bold text-slate-300">Banner Image URL (Optional)</label>
          <input type="text" id="push-image" placeholder="https://..." class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono text-xs focus:outline-none focus:border-amber-400">
        </div>
      </div>

      <div id="push-result-msg" class="text-xs font-bold"></div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
        <button type="button" onclick="closeCustomPushModal()" class="bg-slate-900 hover:bg-slate-800 text-slate-300 font-bold px-4 py-2 rounded-xl">Cancel</button>
        <button type="submit" id="push-submit-btn" class="gradient-btn-gold text-slate-950 font-black px-5 py-2 rounded-xl uppercase shadow">
          🚀 Send Live Push Now
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ========================================================= -->
<!-- MODAL: BROADCAST CUSTOM PUSH TO ALL / ONLINE USERS        -->
<!-- ========================================================= -->
<div id="broadcast-modal" class="hidden fixed inset-0 z-50 bg-black/80 backdrop-blur-md flex items-center justify-center p-4">
  <div class="uber-card p-6 sm:p-8 rounded-3xl border border-amber-500/40 w-full max-w-lg space-y-5 shadow-2xl relative">
    <div class="flex items-center justify-between border-b border-slate-800 pb-3">
      <div class="flex items-center gap-2">
        <div class="w-9 h-9 rounded-xl bg-gradient-to-tr from-amber-500 to-amber-300 text-slate-950 flex items-center justify-center font-black">
          📢
        </div>
        <div>
          <h2 class="text-base font-black text-white font-outfit uppercase">Broadcast FCM Notification</h2>
          <p class="text-xs text-slate-400">Send custom announcement to multiple customer devices</p>
        </div>
      </div>
      <button onclick="closeBroadcastModal()" class="text-slate-400 hover:text-white text-lg font-black">&times;</button>
    </div>

    <form id="broadcast-form" onsubmit="handleSendBroadcastPush(event)" class="space-y-4 text-xs">
      <div class="space-y-1">
        <label class="font-bold text-slate-300">Target Audience *</label>
        <div class="grid grid-cols-2 gap-2">
          <label class="bg-slate-950 p-3 rounded-xl border border-slate-700 flex items-center gap-2 cursor-pointer hover:border-amber-400">
            <input type="radio" name="broadcast_target" value="online" checked class="text-amber-400">
            <div>
              <span class="font-extrabold text-white block">🟢 Online Users Only</span>
              <span class="text-[10px] text-slate-400">Active app opened now</span>
            </div>
          </label>

          <label class="bg-slate-950 p-3 rounded-xl border border-slate-700 flex items-center gap-2 cursor-pointer hover:border-amber-400">
            <input type="radio" name="broadcast_target" value="all" class="text-amber-400">
            <div>
              <span class="font-extrabold text-white block">📢 All Registered</span>
              <span class="text-[10px] text-slate-400">All registered devices</span>
            </div>
          </label>
        </div>
      </div>

      <div class="space-y-1">
        <label class="font-bold text-slate-300">Broadcast Title *</label>
        <input type="text" id="bc-title" required value="🚕 PAVANCAB Special Announcement" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-bold focus:outline-none focus:border-amber-400">
      </div>

      <div class="space-y-1">
        <label class="font-bold text-slate-300">Announcement Message Body *</label>
        <textarea id="bc-body" rows="3" required class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3.5 py-2 text-white font-medium focus:outline-none focus:border-amber-400" placeholder="Write your broadcast message to passengers..."></textarea>
      </div>

      <div class="space-y-1">
        <label class="font-bold text-slate-300">Deep Link Action URL</label>
        <input type="text" id="bc-url" value="/app/index.php" class="w-full bg-slate-950 border border-slate-700 rounded-xl px-3 py-2 text-white font-mono text-xs focus:outline-none focus:border-amber-400">
      </div>

      <div id="bc-result-msg" class="text-xs font-bold"></div>

      <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-800">
        <button type="button" onclick="closeBroadcastModal()" class="bg-slate-900 hover:bg-slate-800 text-slate-300 font-bold px-4 py-2 rounded-xl">Cancel</button>
        <button type="submit" id="bc-submit-btn" class="gradient-btn-gold text-slate-950 font-black px-5 py-2 rounded-xl uppercase shadow">
          🚀 Send Broadcast Push
        </button>
      </div>
    </form>
  </div>
</div>

<script>
let currentUserFilter = 'ALL';

function setUserFilter(mode) {
  currentUserFilter = mode;
  document.querySelectorAll('.user-filter-chip').forEach(btn => {
    btn.className = 'user-filter-chip px-3 py-1.5 rounded-xl font-bold text-xs bg-slate-950 text-slate-400 border border-slate-800 hover:bg-slate-800';
  });
  const activeBtn = document.getElementById(`filter-btn-${mode}`);
  if (activeBtn) {
    activeBtn.className = 'user-filter-chip px-3 py-1.5 rounded-xl font-bold text-xs bg-amber-400 text-slate-950 shadow';
  }
  filterUsersFeed();
}

function filterUsersFeed() {
  const query = (document.getElementById('user-search-input')?.value || '').toLowerCase().trim();
  const cards = document.querySelectorAll('.user-card');
  let visibleCount = 0;

  cards.forEach(card => {
    const searchStr = card.dataset.search || '';
    const isOnline = card.dataset.online === '1';
    const hasFcm = card.dataset.fcm === '1';

    let matchesFilter = true;
    if (currentUserFilter === 'ONLINE') matchesFilter = isOnline;
    else if (currentUserFilter === 'OFFLINE') matchesFilter = !isOnline;
    else if (currentUserFilter === 'FCM') matchesFilter = hasFcm;

    const matchesSearch = (!query || searchStr.includes(query));

    if (matchesFilter && matchesSearch) {
      card.classList.remove('hidden');
      visibleCount++;
    } else {
      card.classList.add('hidden');
    }
  });

  const emptyEl = document.getElementById('users-empty-state');
  if (emptyEl) {
    if (visibleCount === 0) emptyEl.classList.remove('hidden');
    else emptyEl.classList.add('hidden');
  }
}

function openCustomPushModal(phone, name, email) {
  document.getElementById('push-target-phone').value = phone || '';
  document.getElementById('push-target-email').value = email || '';
  document.getElementById('push-broadcast-mode').value = 'single';
  document.getElementById('push-target-subtitle').innerText = `Target: ${name || 'Customer'} (${phone || email})`;
  document.getElementById('push-title').value = '🚕 PAVANCAB Alert';
  document.getElementById('push-body').value = `Hello ${name || 'there'}, your Goa cab is ready with exclusive fixed rates!`;
  document.getElementById('push-result-msg').innerHTML = '';
  document.getElementById('custom-push-modal').classList.remove('hidden');
}

function closeCustomPushModal() {
  document.getElementById('custom-push-modal').classList.add('hidden');
}

function openBroadcastModal() {
  document.getElementById('bc-result-msg').innerHTML = '';
  document.getElementById('broadcast-modal').classList.remove('hidden');
}

function closeBroadcastModal() {
  document.getElementById('broadcast-modal').classList.add('hidden');
}

function applyPushPreset(preset) {
  const titleEl = document.getElementById('push-title');
  const bodyEl = document.getElementById('push-body');
  const urlEl = document.getElementById('push-url');

  if (preset === 'discount') {
    titleEl.value = '🎉 ₹200 OFF on your next Goa Ride!';
    bodyEl.value = 'Book your airport taxi or sightseeing trip today with code GOA200. Instant confirmation!';
    urlEl.value = '/app/index.php?coupon=GOA200';
  } else if (preset === 'reminder') {
    titleEl.value = '🚖 Need a ride in Goa? PAVANCAB is ready!';
    bodyEl.value = 'Nearest verified Goa taxi drivers available 24/7 with zero surge pricing.';
    urlEl.value = '/app/index.php';
  } else if (preset === 'airport') {
    titleEl.value = '✈️ Goa Airport (Mopa / Dabolim) Transfer Special';
    bodyEl.value = 'Pre-book your smooth airport pickup with flight delay tracking and air-conditioned cabs.';
    urlEl.value = '/app/index.php#airport-transfer';
  }
}

async function handleSendCustomPush(e) {
  e.preventDefault();
  const phone = document.getElementById('push-target-phone').value;
  const email = document.getElementById('push-target-email').value;
  const title = document.getElementById('push-title').value.trim();
  const body = document.getElementById('push-body').value.trim();
  const url = document.getElementById('push-url').value.trim();
  const image = document.getElementById('push-image').value.trim();
  const msgEl = document.getElementById('push-result-msg');
  const btn = document.getElementById('push-submit-btn');

  btn.disabled = true;
  btn.innerText = '⏳ Dispatching FCM Push...';
  msgEl.innerHTML = '<span class="text-amber-400 font-bold">Contacting Google FCM HTTP v1 gateway...</span>';

  try {
    const res = await fetch('../api_dashboard.php?action=send-custom-fcm', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        target_phone: phone,
        target_email: email,
        title: title,
        body: body,
        click_action: url,
        image_url: image
      })
    });
    const data = await res.json();
    if (data.success) {
      msgEl.innerHTML = `<span class="text-emerald-400 font-black">✓ ${data.message}</span>`;
      setTimeout(() => closeCustomPushModal(), 2000);
    } else {
      msgEl.innerHTML = `<span class="text-red-400 font-bold">❌ ${data.error || 'Failed to dispatch'}</span>`;
    }
  } catch(err) {
    msgEl.innerHTML = '<span class="text-red-400 font-bold">❌ Network Error</span>';
  } finally {
    btn.disabled = false;
    btn.innerText = '🚀 Send Live Push Now';
  }
}

async function handleSendBroadcastPush(e) {
  e.preventDefault();
  const mode = document.querySelector('input[name="broadcast_target"]:checked').value;
  const title = document.getElementById('bc-title').value.trim();
  const body = document.getElementById('bc-body').value.trim();
  const url = document.getElementById('bc-url').value.trim();
  const msgEl = document.getElementById('bc-result-msg');
  const btn = document.getElementById('bc-submit-btn');

  btn.disabled = true;
  btn.innerText = '⏳ Broadcasting...';

  try {
    const payload = {
      title: title,
      body: body,
      click_action: url
    };
    if (mode === 'online') payload.broadcast_online = true;
    else payload.broadcast_all = true;

    const res = await fetch('../api_dashboard.php?action=send-custom-fcm', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    });
    const data = await res.json();
    if (data.success) {
      msgEl.innerHTML = `<span class="text-emerald-400 font-black">✓ Broadcast Dispatched! Sent: ${data.sent}, Failed: ${data.failed}</span>`;
      setTimeout(() => closeBroadcastModal(), 2500);
    } else {
      msgEl.innerHTML = `<span class="text-red-400 font-bold">❌ ${data.error || 'Broadcast failed'}</span>`;
    }
  } catch(err) {
    msgEl.innerHTML = '<span class="text-red-400 font-bold">❌ Network Error</span>';
  } finally {
    btn.disabled = false;
    btn.innerText = '🚀 Send Broadcast Push';
  }
}

// Live background poll for users online states (every 3s)
async function refreshUsersLive() {
  try {
    const res = await fetch('../api_dashboard.php?action=users');
    const users = await res.json();
    if (!Array.isArray(users)) return;

    let onlineCount = 0;
    let fcmCount = 0;
    users.forEach(u => {
      const isOnline = (u.is_online === 1 || u.live_app_status === 'ONLINE_OPEN');
      const hasFcm = (u.fcm_tokens_count > 0);
      if (isOnline) onlineCount++;
      if (hasFcm) fcmCount++;

      const card = document.getElementById(`user-card-${u.clean_phone || u.user_id}`);
      if (card) {
        card.dataset.online = isOnline ? '1' : '0';
        card.dataset.fcm = hasFcm ? '1' : '0';

        // Update card border and shadow
        if (isOnline) {
          card.classList.add('border-emerald-500/40', 'bg-emerald-950/10', 'shadow-emerald-500/10', 'shadow-lg');
          card.classList.remove('border-slate-800', 'bg-slate-900/30');
        } else {
          card.classList.remove('border-emerald-500/40', 'bg-emerald-950/10', 'shadow-emerald-500/10', 'shadow-lg');
          card.classList.add('border-slate-800', 'bg-slate-900/30');
        }

        // Update avatar pulse dot
        const dot = card.querySelector('.relative span.rounded-full');
        if (dot) {
          dot.className = 'absolute -bottom-1 -right-1 w-4 h-4 rounded-full border-2 border-slate-950 ' + (isOnline ? 'bg-emerald-400 animate-pulse' : 'bg-slate-600');
          dot.title = isOnline ? 'App Open / Online Now' : 'App Closed / Offline';
        }

        // Update status badge
        const badge = card.querySelector('.text-right span.uppercase');
        if (badge) {
          badge.className = 'text-[10px] font-black uppercase px-2 py-0.5 rounded-lg border ' + (isOnline ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-slate-800 text-slate-400 border-slate-700');
          badge.innerText = isOnline ? '🟢 Open Now' : '⚪ Closed';
        }

        // Update FCM device label
        const fcmLabel = card.querySelector('.text-right div.font-bold');
        if (fcmLabel) {
          fcmLabel.innerText = hasFcm ? `🔔 ${u.fcm_tokens_count} FCM Device` : '🔕 No Push Token';
        }
      }
    });

    const mOnline = document.getElementById('metric-online-users');
    if (mOnline) mOnline.innerText = onlineCount;
    const cOnline = document.getElementById('chip-count-ONLINE');
    if (cOnline) cOnline.innerText = onlineCount;
    const mFcm = document.getElementById('metric-fcm-users');
    if (mFcm) mFcm.innerText = fcmCount;
    const cFcm = document.getElementById('chip-count-FCM');
    if (cFcm) cFcm.innerText = fcmCount;
  } catch(e){}
}

setInterval(refreshUsersLive, 3000);
</script>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
