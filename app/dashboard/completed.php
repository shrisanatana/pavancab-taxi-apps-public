<?php
/**
 * PAVANCAB GOA TAXI - Completed Rides Desk
 * Path: app/dashboard/completed.php
 */

$pageTitle = '🟢 Completed Rides Desk • Trips History';
$activeTab = 'COMPLETED';
require_once __DIR__ . '/_layout_header.php';
?>

<!-- DYNAMIC EMPTY STATE -->
<div id="bookings-empty-state" class="<?php echo $statCompleted === 0 ? '' : 'hidden'; ?> uber-card p-10 sm:p-12 rounded-3xl border border-dashed border-slate-800 text-center space-y-4 my-4 animate-fadeIn">
  <div class="w-14 h-14 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center mx-auto text-emerald-400 text-2xl">
    🏁
  </div>
  <div class="space-y-1.5">
    <h3 class="text-base sm:text-lg font-black text-white font-outfit uppercase tracking-wide" id="bookings-empty-title">No Completed Trips Recorded Yet</h3>
    <p class="text-xs text-slate-400 max-w-sm mx-auto leading-relaxed" id="bookings-empty-text">
      Trips will archive here with fixed fare earnings, passenger star reviews, and driver history once completed.
    </p>
  </div>
</div>

<!-- BOOKINGS FEED CONTAINER -->
<div class="space-y-4" id="bookings-feed-container">
  <?php foreach ($bookings as $b): 
    $unifiedKey = classifyRideStatus($b['status'] ?? 'PENDING');
    $isDone     = ($unifiedKey === 'COMPLETED');

    $hasBoost = !empty($b['special_notes']) && strpos($b['special_notes'], '[PEAK BOOST]') !== false;
    $searchString = strtolower("{$b['booking_ref']} {$b['customer_name']} {$b['customer_phone']} {$b['pickup_location']} {$b['drop_location']} {$b['cab_type']} {$b['driver_name']} {$b['driver_phone']}");
  ?>
    <div class="booking-card uber-card p-5 sm:p-6 rounded-3xl border border-emerald-500/30 bg-emerald-950/10 space-y-4 shadow-xl transition relative overflow-hidden <?php echo !$isDone ? 'hidden' : ''; ?>" 
         id="booking-card-<?php echo $b['id']; ?>"
         data-status="<?php echo $unifiedKey; ?>" 
         data-search="<?php echo htmlspecialchars($searchString); ?>">

      <!-- CARD TOP STRIP -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/80 pb-3.5">
        <div class="space-y-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="text-lg font-black text-white font-outfit tracking-wide">#<?php echo htmlspecialchars($b['booking_ref']); ?></span>
            <span class="bg-amber-400/10 text-amber-300 border border-amber-400/30 text-xs font-black px-2.5 py-0.5 rounded-lg uppercase">
              <?php echo htmlspecialchars($b['cab_type']); ?>
            </span>
            <span class="text-[10px] bg-slate-800 text-slate-300 font-bold px-2 py-0.5 rounded-md uppercase">
              <?php echo htmlspecialchars($b['trip_type'] ?? 'one_way'); ?>
            </span>
            <?php if ($hasBoost): ?>
              <span class="text-[10px] bg-amber-500/20 text-amber-300 font-black px-2 py-0.5 rounded-md border border-amber-500/40 flex items-center gap-1">
                ⚡ Fare Boosted
              </span>
            <?php endif; ?>
            <span class="text-[10px] text-slate-500 font-bold"><?php echo htmlspecialchars($b['created_at'] ?? ''); ?></span>
          </div>

          <div class="text-xs font-bold text-slate-300 flex flex-wrap items-center gap-2 pt-0.5">
            <span>👤 <?php echo htmlspecialchars($b['customer_name']); ?></span>
            <span>•</span>
            <span>📞 <a href="tel:<?php echo htmlspecialchars($b['customer_phone']); ?>" class="text-amber-400 hover:underline"><?php echo htmlspecialchars($b['customer_phone']); ?></a></span>
          </div>
        </div>

        <div class="flex items-center gap-3">
          <div class="text-right">
            <span class="text-2xl font-black text-amber-400 font-outfit block">₹<?php echo floatval($b['total_fare'] ?? 0); ?></span>
            <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Fixed Fare</span>
          </div>
          <span class="text-xs font-black uppercase px-3 py-1.5 rounded-xl border bg-emerald-500/20 text-emerald-300 border-emerald-500/40">
            COMPLETED
          </span>
        </div>
      </div>

      <!-- ROUTE & SCHEDULE -->
      <div class="bg-slate-950/70 p-4 rounded-2xl border border-slate-800/80 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
        <div class="space-y-1">
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
            📅 Schedule: <span class="text-amber-300 font-black"><?php echo htmlspecialchars($b['pickup_date']); ?> at <?php echo htmlspecialchars($b['pickup_time'] ?? ''); ?></span>
          </div>
          <?php if (!empty($b['special_notes'])): ?>
            <div class="text-[11px] text-amber-400 font-semibold italic bg-amber-400/10 p-1.5 rounded-lg inline-block border border-amber-400/20 text-left">
              📝 <?php echo nl2br(htmlspecialchars($b['special_notes'])); ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- DRIVER DETAILS -->
      <?php if (!empty($b['driver_name'])): ?>
        <div class="bg-slate-900/60 border border-slate-800 p-3.5 rounded-2xl flex flex-wrap justify-between items-center gap-2 text-xs">
          <div class="flex items-center gap-2">
            <span class="text-emerald-400 font-black">👨‍✈️ Driver:</span>
            <span class="font-bold text-white"><?php echo htmlspecialchars($b['driver_name']); ?></span>
            <span class="text-slate-400">📞 <?php echo htmlspecialchars($b['driver_phone']); ?></span>
            <span class="text-amber-400 font-mono font-bold">🚖 <?php echo htmlspecialchars($b['vehicle_number'] ?? 'GA-03-T-1234'); ?></span>
          </div>
          <?php if (!empty($b['user_rating'])): ?>
            <div class="flex items-center gap-1.5 text-xs text-amber-400 font-black">
              <span>⭐ <?php echo intval($b['user_rating']); ?> / 5 Stars</span>
              <?php if (!empty($b['user_review'])): ?>
                <span class="text-slate-400 italic text-[11px]">"<?php echo htmlspecialchars($b['user_review']); ?>"</span>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <!-- ACTION CONTROLS & PRINT SLIP -->
      <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-800/80">
        <div class="flex items-center gap-2">
          <button onclick="openReceiptModal(<?php echo $b['id']; ?>)" class="bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 hover:text-white font-bold text-xs px-3 py-2 rounded-xl flex items-center gap-1.5 shadow" title="Print Slip">
            <i data-lucide="printer" class="w-3.5 h-3.5 text-amber-400"></i> Print Official Receipt Slip
          </button>
        </div>

        <div class="flex items-center gap-2">
          <span class="text-[10px] text-slate-400 font-bold uppercase hidden sm:inline">Status:</span>
          <select onchange="updateBookingStatus(<?php echo $b['id']; ?>, this.value)" class="bg-slate-900 border border-slate-700 text-slate-200 text-xs font-bold px-3 py-2 rounded-xl focus:outline-none focus:border-amber-400">
            <option value="COMPLETED" selected>COMPLETED (Finished)</option>
            <option value="PENDING">Re-open as PENDING</option>
            <option value="CANCELLED_BY_ADMIN">Mark CANCELLED</option>
          </select>
        </div>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
