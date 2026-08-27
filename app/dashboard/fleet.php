<?php
/**
 * PAVANCAB GOA TAXI - Fleet & Driver Partners Desk
 * Path: app/dashboard/fleet.php
 */

$pageTitle = '🚖 Fleet Management • Driver Partners';
$activeTab = 'FLEET';
require_once __DIR__ . '/_layout_header.php';
?>

<div class="space-y-6">
  <!-- TOP STATS & QUICK ADD BAR -->
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-black text-white font-outfit uppercase tracking-tight flex items-center gap-2">
        <span>Goa Driver Partners Roster</span>
        <span class="text-xs bg-amber-400/20 text-amber-300 border border-amber-400/40 px-2.5 py-0.5 rounded-full font-black">
          <?php echo count($drivers); ?> Registered Drivers
        </span>
      </h2>
      <p class="text-xs text-slate-400">Manage fleet partners, register new drivers, and toggle live availability for dispatch.</p>
    </div>

    <button onclick="openFleetModal()" class="gradient-btn-gold text-slate-950 font-black text-xs px-4 py-2.5 rounded-xl uppercase flex items-center gap-1.5 shadow-lg">
      <i data-lucide="user-plus" class="w-4 h-4"></i> + Register New Fleet Driver
    </button>
  </div>

  <!-- FLEET GRID -->
  <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4" id="fleet-grid-container">
    <?php if (empty($drivers)): ?>
      <div class="col-span-full uber-card p-12 rounded-3xl text-center text-slate-400 border border-slate-800 space-y-3">
        <p class="text-base font-bold text-white">No fleet drivers registered yet.</p>
        <p class="text-xs text-slate-500">Click '+ Register New Fleet Driver' above to add your first driver partner.</p>
      </div>
    <?php else: ?>
      <?php foreach ($drivers as $d): 
        $st = strtolower($d['status'] ?? 'available');
        $isAvailable = ($st === 'available');
        $isOnTrip    = ($st === 'on_trip');
        $cleanPhone  = preg_replace('/\D/', '', $d['phone'] ?? '');
      ?>
        <div class="uber-card p-5 rounded-3xl border border-slate-800 space-y-4 shadow-xl hover:border-amber-400/40 transition" data-driver-id="<?php echo (int)$d['id']; ?>">
          <div class="flex items-start justify-between gap-2">
            <div class="flex items-center gap-3">
              <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400 text-xl font-black">
                👨‍✈️
              </div>
              <div>
                <h3 class="font-extrabold text-white text-base leading-snug"><?php echo htmlspecialchars($d['name']); ?></h3>
                <span class="text-xs text-amber-300 font-mono font-bold">🚖 <?php echo htmlspecialchars($d['plate_number'] ?? 'GA-03-T-1234'); ?></span>
                <span class="text-slate-400 text-[11px] block"><?php echo htmlspecialchars($d['car_model'] ?? 'Sedan / Hatchback'); ?></span>
              </div>
            </div>

            <button onclick="toggleDriverStatus(<?php echo $d['id']; ?>, '<?php echo $isAvailable ? 'inactive' : 'available'; ?>')" 
                    class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase border transition <?php 
                      if ($isAvailable) echo 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40';
                      elseif ($isOnTrip) echo 'bg-yellow-500/20 text-yellow-300 border-yellow-500/40 animate-pulse';
                      else echo 'bg-slate-800 text-slate-400 border-slate-700';
                    ?>">
              <?php echo strtoupper($d['status'] ?? 'available'); ?>
            </button>
          </div>

          <div class="bg-slate-950/70 p-3 rounded-2xl border border-slate-800/80 text-xs space-y-1.5">
            <div class="flex justify-between items-center">
              <span class="text-slate-400 font-bold">Direct Phone:</span>
              <a href="tel:<?php echo htmlspecialchars($d['phone']); ?>" class="text-amber-400 font-bold hover:underline">
                📞 <?php echo htmlspecialchars($d['phone']); ?>
              </a>
            </div>
            <div class="flex justify-between items-center">
              <span class="text-slate-400 font-bold">WhatsApp:</span>
              <a href="https://wa.me/<?php echo $cleanPhone; ?>" target="_blank" class="text-emerald-400 font-bold hover:underline flex items-center gap-1">
                <i data-lucide="message-circle" class="w-3.5 h-3.5"></i> Open Chat
              </a>
            </div>
          </div>

          <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-800/80">
            <a href="tel:<?php echo htmlspecialchars($d['phone']); ?>" class="flex-1 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold py-2 rounded-xl text-center flex items-center justify-center gap-1">
              <i data-lucide="phone" class="w-3.5 h-3.5 text-amber-400"></i> Call
            </a>
            <a href="https://wa.me/<?php echo $cleanPhone; ?>" target="_blank" class="flex-1 bg-emerald-600 hover:bg-emerald-500 text-slate-950 text-xs font-black py-2 rounded-xl text-center flex items-center justify-center gap-1 shadow">
              <i data-lucide="send" class="w-3.5 h-3.5"></i> WhatsApp
            </a>
            <button onclick="deleteDriver(<?php echo $d['id']; ?>)" class="p-2 text-slate-500 hover:text-red-400 rounded-xl transition" title="Delete Driver">
              <i data-lucide="trash-2" class="w-4 h-4"></i>
            </button>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
