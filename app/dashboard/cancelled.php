<?php
/**
 * PAVANCAB GOA TAXI - Cancelled Bookings Desk
 * Path: app/dashboard/cancelled.php
 */

$pageTitle = '🚫 Cancelled Bookings Desk • Audit Roster';
$activeTab = 'CANCELLED';
require_once __DIR__ . '/_layout_header.php';
?>

<!-- DYNAMIC EMPTY STATE -->
<div id="bookings-empty-state" class="<?php echo $statCancelled === 0 ? '' : 'hidden'; ?> uber-card p-10 sm:p-12 rounded-3xl border border-dashed border-slate-800 text-center space-y-4 my-4 animate-fadeIn">
  <div class="w-14 h-14 rounded-2xl bg-slate-800 border border-slate-700 flex items-center justify-center mx-auto text-slate-400 text-2xl">
    ✓
  </div>
  <div class="space-y-1.5">
    <h3 class="text-base sm:text-lg font-black text-white font-outfit uppercase tracking-wide" id="bookings-empty-title">Zero Cancelled Bookings</h3>
    <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed" id="bookings-empty-text">
      No rides have been cancelled or rejected.
    </p>
  </div>
</div>

<!-- BOOKINGS FEED CONTAINER -->
<div class="space-y-4" id="bookings-feed-container">
  <?php foreach ($bookings as $b): 
    $unifiedKey = classifyRideStatus($b['status'] ?? 'PENDING');
    $isCancelled = ($unifiedKey === 'CANCELLED');

    $searchString = strtolower("{$b['booking_ref']} {$b['customer_name']} {$b['customer_phone']} {$b['pickup_location']} {$b['drop_location']} {$b['cab_type']}");
  ?>
    <div class="booking-card uber-card p-5 sm:p-6 rounded-3xl border border-slate-800 bg-slate-900/40 space-y-4 shadow-xl transition relative overflow-hidden opacity-75 hover:opacity-100 <?php echo !$isCancelled ? 'hidden' : ''; ?>" 
         id="booking-card-<?php echo $b['id']; ?>"
         data-status="<?php echo $unifiedKey; ?>" 
         data-search="<?php echo htmlspecialchars($searchString); ?>">

      <!-- CARD TOP STRIP -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/80 pb-3.5">
        <div class="space-y-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-lg font-black text-white font-outfit tracking-wide">#<?php echo htmlspecialchars($b['booking_ref']); ?></span>
            <span class="bg-slate-800 text-slate-400 border border-slate-700 text-xs font-black px-2.5 py-0.5 rounded-lg uppercase">
              <?php echo htmlspecialchars($b['cab_type']); ?>
            </span>
            <span class="text-[10px] text-slate-500 font-bold"><?php echo htmlspecialchars($b['created_at'] ?? ''); ?></span>
          </div>

          <div class="text-xs font-bold text-slate-400 flex flex-wrap items-center gap-2 pt-0.5">
            <span>👤 <?php echo htmlspecialchars($b['customer_name']); ?></span>
            <span>•</span>
            <span>📞 <?php echo htmlspecialchars($b['customer_phone']); ?></span>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <div class="text-right">
            <span class="text-xl font-black text-slate-400 font-outfit block">₹<?php echo floatval($b['total_fare'] ?? 0); ?></span>
            <span class="text-[10px] text-slate-500 uppercase font-bold tracking-wider">Cancelled</span>
          </div>
          <span class="text-xs font-black uppercase px-3 py-1.5 rounded-xl border bg-red-500/10 text-red-400 border-red-500/20">
            CANCELLED
          </span>
        </div>
      </div>

      <!-- ROUTE DETAILS -->
      <div class="bg-slate-950/70 p-4 rounded-2xl border border-slate-800/80 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs opacity-80">
        <div class="space-y-1">
          <div>📍 Pickup: <strong class="text-slate-200"><?php echo htmlspecialchars($b['pickup_location']); ?></strong></div>
          <div>🏁 Drop: <strong class="text-slate-200"><?php echo htmlspecialchars($b['drop_location']); ?></strong></div>
        </div>
        <div class="space-y-1 md:text-right">
          <div>📅 Scheduled: <?php echo htmlspecialchars($b['pickup_date']); ?> at <?php echo htmlspecialchars($b['pickup_time'] ?? ''); ?></div>
          <?php if (!empty($b['special_notes'])): ?>
            <div class="text-[11px] text-slate-400 italic">📝 <?php echo nl2br(htmlspecialchars($b['special_notes'])); ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- ACTION CONTROLS & RE-OPEN -->
      <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-800/80">
        <div class="flex items-center gap-2">
          <button onclick="updateBookingStatus(<?php echo $b['id']; ?>, 'PENDING')" class="bg-amber-500/20 hover:bg-amber-500/30 border border-amber-400 text-amber-300 font-black text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 shadow transition">
            <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> 🔄 Re-Open Ride as Needs Driver
          </button>
        </div>

        <div class="flex items-center gap-2">
          <span class="text-[10px] text-slate-400 font-bold uppercase hidden sm:inline">Status:</span>
          <select onchange="updateBookingStatus(<?php echo $b['id']; ?>, this.value)" class="bg-slate-900 border border-slate-700 text-slate-200 text-xs font-bold px-3 py-2 rounded-xl focus:outline-none focus:border-amber-400">
            <option value="CANCELLED_BY_ADMIN" selected>CANCELLED</option>
            <option value="PENDING">Re-open as PENDING</option>
          </select>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
