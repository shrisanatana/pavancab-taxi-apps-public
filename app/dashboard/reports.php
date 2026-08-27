<?php
/**
 * PAVANCAB GOA TAXI - Ride Reports Desk
 * Path: app/dashboard/reports.php
 */

$pageTitle = '🚨 Ride Reports Desk • Customer Support';
$activeTab = 'REPORTS';
require_once __DIR__ . '/_layout_header.php';

// Fetch reports directly
$reportsList = [];
$resRep = $conn->query("
    SELECT r.*, b.booking_ref, b.customer_name, b.customer_phone, b.pickup_location, b.drop_location, b.driver_name, b.driver_phone 
    FROM app_ride_reports r 
    LEFT JOIN app_bookings b ON r.booking_id = b.id 
    ORDER BY r.id DESC
");
if ($resRep) {
    while ($row = $resRep->fetch_assoc()) {
        $reportsList[] = $row;
    }
}
?>

<div class="space-y-6">
  <!-- HEADER & SUMMARY -->
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-black text-white font-outfit uppercase tracking-tight flex items-center gap-2">
        <span>Customer Ride Reports Desk</span>
        <span class="text-xs bg-amber-400/20 text-amber-300 border border-amber-400/40 px-2.5 py-0.5 rounded-full font-black">
          <?php echo count($reportsList); ?> Total Inquiries
        </span>
      </h2>
      <p class="text-xs text-slate-400">Resolve passenger feedback, lost items, driver complaints, and fare dispute inquiries.</p>
    </div>
  </div>

  <!-- REPORTS LIST FEED -->
  <div class="space-y-4" id="reports-feed-container">
    <?php if (empty($reportsList)): ?>
      <div class="uber-card p-12 rounded-3xl text-center text-slate-400 border border-slate-800 space-y-2" id="reports-empty">
        <div class="w-12 h-12 rounded-2xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center mx-auto text-xl">
          ✓
        </div>
        <p class="text-base font-bold text-white">All Clear • No Open Reports</p>
        <p class="text-xs text-slate-500">Customer feedback and ride reports submitted from the tracking page will stream here.</p>
      </div>
    <?php else: ?>
      <?php foreach ($reportsList as $rep): 
        $st = strtoupper($rep['status'] ?? 'PENDING');
        $isResolved = ($st === 'RESOLVED');
        $isInvestigating = ($st === 'INVESTIGATING');
      ?>
        <div class="uber-card p-5 sm:p-6 rounded-3xl border <?php echo $isResolved ? 'border-emerald-500/30 opacity-75' : 'border-amber-500/40'; ?> space-y-4 shadow-xl" id="report-card-<?php echo (int)$rep['id']; ?>" data-report-id="<?php echo (int)$rep['id']; ?>">
          <div class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-800 pb-3">
            <div>
              <span class="text-sm font-black text-white font-outfit uppercase">
                #GTA-REP-<?php echo $rep['id']; ?> (Booking #<?php echo htmlspecialchars($rep['booking_ref'] ?? $rep['booking_id']); ?>)
              </span>
              <span class="text-[10px] bg-slate-800 text-amber-300 font-bold px-2 py-0.5 rounded-md ml-1.5 uppercase">
                <?php echo htmlspecialchars($rep['issue_type'] ?? $rep['issue_category'] ?? 'Issue'); ?>
              </span>
              <span class="text-[10px] text-slate-500 ml-2"><?php echo htmlspecialchars($rep['created_at'] ?? ''); ?></span>
            </div>

            <div class="flex items-center gap-2">
              <span id="report-status-badge-<?php echo (int)$rep['id']; ?>" class="text-[10px] font-black uppercase px-2.5 py-1 rounded-xl border <?php 
                if ($isResolved) echo 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40';
                elseif ($isInvestigating) echo 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40 animate-pulse';
                else echo 'bg-amber-400/20 text-amber-300 border-amber-400/40 animate-pulse';
              ?>">
                <?php echo $st; ?>
              </span>
            </div>
          </div>

          <div class="text-xs text-slate-300 bg-slate-950/70 p-3.5 rounded-2xl border border-slate-800 space-y-1.5">
            <div class="font-bold text-amber-300 uppercase text-[10px]">Passenger Feedback / Report:</div>
            <p class="leading-relaxed"><?php echo nl2br(htmlspecialchars($rep['description'] ?? '')); ?></p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs bg-slate-900/40 p-3 rounded-xl border border-slate-800">
            <div>
              <span class="text-slate-400 font-bold">Passenger:</span>
              <span class="text-white font-bold ml-1"><?php echo htmlspecialchars($rep['customer_name'] ?? 'N/A'); ?></span>
              <a href="tel:<?php echo htmlspecialchars($rep['customer_phone'] ?? ''); ?>" class="text-amber-400 hover:underline ml-1">
                (📞 <?php echo htmlspecialchars($rep['customer_phone'] ?? ''); ?>)
              </a>
            </div>
            <div>
              <span class="text-slate-400 font-bold">Driver:</span>
              <span class="text-white font-bold ml-1"><?php echo htmlspecialchars($rep['driver_name'] ?? 'Unassigned'); ?></span>
              <?php if (!empty($rep['driver_phone'])): ?>
                <a href="tel:<?php echo htmlspecialchars($rep['driver_phone']); ?>" class="text-amber-400 hover:underline ml-1">
                  (📞 <?php echo htmlspecialchars($rep['driver_phone']); ?>)
                </a>
              <?php endif; ?>
            </div>
          </div>

          <!-- ACTIONS -->
          <div class="flex flex-wrap items-center justify-between gap-2 pt-2 border-t border-slate-800">
            <div class="flex items-center gap-2">
              <?php if (!empty($rep['customer_phone'])): ?>
                <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $rep['customer_phone']); ?>" target="_blank" class="bg-emerald-600 hover:bg-emerald-500 text-slate-950 font-black text-xs px-3.5 py-1.5 rounded-xl flex items-center gap-1 shadow">
                  <i data-lucide="message-circle" class="w-3.5 h-3.5"></i> WhatsApp Passenger
                </a>
              <?php endif; ?>
              <?php if (!empty($rep['driver_phone'])): ?>
                <a href="https://wa.me/<?php echo preg_replace('/\D/', '', $rep['driver_phone']); ?>" target="_blank" class="bg-slate-800 hover:bg-slate-700 text-white font-bold text-xs px-3.5 py-1.5 rounded-xl flex items-center gap-1">
                  <i data-lucide="send" class="w-3.5 h-3.5 text-amber-400"></i> WhatsApp Driver
                </a>
              <?php endif; ?>
            </div>

            <div class="flex items-center gap-2">
              <span class="text-[10px] text-slate-400 font-bold uppercase">Status:</span>
              <select onchange="updateReportStatus(<?php echo $rep['id']; ?>, this.value)" class="bg-slate-900 border border-slate-700 text-slate-200 text-xs font-bold px-3 py-1.5 rounded-xl focus:outline-none focus:border-amber-400">
                <option value="PENDING" <?php if ($st === 'PENDING') echo 'selected'; ?>>PENDING</option>
                <option value="INVESTIGATING" <?php if ($st === 'INVESTIGATING') echo 'selected'; ?>>INVESTIGATING</option>
                <option value="RESOLVED" <?php if ($st === 'RESOLVED') echo 'selected'; ?>>RESOLVED</option>
              </select>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
async function updateReportStatus(reportId, newStatus) {
  try {
    const res = await fetch(`../api_dashboard.php?action=update-report`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ report_id: reportId, status: newStatus })
    });
    const data = await res.json();
    if (data.success) {
      // #region agent log
      fetch('http://127.0.0.1:7677/ingest/a4c1ae9b-9c91-43a8-8e20-83b11c083cfd',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'43d22e'},body:JSON.stringify({sessionId:'43d22e',hypothesisId:'H2',location:'reports.php:updateReportStatus',message:'report status live update',data:{reportId,newStatus},timestamp:Date.now()})}).catch(()=>{});
      // #endregion
      const badge = document.getElementById('report-status-badge-' + reportId);
      const card = document.getElementById('report-card-' + reportId);
      if (badge) {
        badge.innerText = newStatus;
        let cls = 'text-[10px] font-black uppercase px-2.5 py-1 rounded-xl border ';
        if (newStatus === 'RESOLVED') cls += 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40';
        else if (newStatus === 'INVESTIGATING') cls += 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40 animate-pulse';
        else cls += 'bg-amber-400/20 text-amber-300 border-amber-400/40 animate-pulse';
        badge.className = cls;
      }
      if (card) {
        card.className = card.className
          .replace(/border-emerald-500\/30|border-amber-500\/40|opacity-75/g, '')
          .trim() + (newStatus === 'RESOLVED' ? ' border-emerald-500/30 opacity-75' : ' border-amber-500/40');
      }
    } else {
      alert(data.error || 'Failed to update report status');
    }
  } catch(e) {
    alert('Network error updating report');
  }
}
</script>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
