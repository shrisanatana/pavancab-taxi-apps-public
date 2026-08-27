<?php
/**
 * PAVANCAB GOA TAXI - Team Staff Management Desk
 * Path: app/dashboard/team.php
 */

$pageTitle = 'ðŸ‘¥ Team Dispatchers Desk â€¢ Access Control';
$activeTab = 'TEAM';
require_once __DIR__ . '/_layout_header.php';

if (!$isSuperAdmin) {
    echo "<script>window.location.href = './index.php';</script>";
    exit;
}

// Fetch team members
$teamMembers = [];
$resT = $conn->query("SELECT * FROM app_team_members ORDER BY id DESC");
if ($resT) {
    while ($row = $resT->fetch_assoc()) {
        $teamMembers[] = $row;
    }
}
?>

<div class="space-y-6">
  <!-- HEADER & DESCRIPTION -->
  <div class="flex flex-wrap items-center justify-between gap-4">
    <div>
      <h2 class="text-xl font-black text-white font-outfit uppercase tracking-tight flex items-center gap-2">
        <span>Team Dispatch Staff Management</span>
        <span class="text-xs bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 px-2.5 py-0.5 rounded-full font-black">
          Super Admin Access
        </span>
      </h2>
      <p class="text-xs text-slate-400">Add dispatch staff to manage bookings and send OTPs. Team members login via WhatsApp OTP.</p>
    </div>
  </div>

  <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- ADD TEAM MEMBER CARD -->
    <div class="uber-card p-6 rounded-3xl border border-slate-800 space-y-4 shadow-xl h-fit">
      <div class="space-y-1">
        <h3 class="text-base font-black text-white uppercase font-outfit">+ Add Dispatch Staff</h3>
        <p class="text-xs text-slate-400">Enter team member credentials to grant dashboard dispatch rights.</p>
      </div>

      <form onsubmit="handleAddTeamMember(event)" class="space-y-3">
        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1">Staff Full Name *</label>
          <input type="text" id="new_team_name" required placeholder="e.g. Rahul Sharma" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
        </div>

        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1">WhatsApp Mobile Number *</label>
          <input type="tel" id="new_team_phone" required placeholder="e.g. 9822123456" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400 font-mono">
        </div>

        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1">Email Address (Optional)</label>
          <input type="email" id="new_team_email" placeholder="staff@pavancab.com" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
        </div>

        <div id="team-add-msg" class="text-xs font-bold text-center"></div>

        <button type="submit" class="w-full gradient-btn-gold text-slate-950 font-black text-xs py-3 rounded-xl uppercase flex items-center justify-center gap-1.5 shadow-lg">
          <i data-lucide="user-check" class="w-4 h-4"></i> Grant Dispatch Access
        </button>
      </form>
    </div>

    <!-- TEAM MEMBERS LIST -->
    <div class="lg:col-span-2 space-y-3">
      <h3 class="text-sm font-black text-slate-300 uppercase tracking-wide">
        Active Dispatchers (<span id="team-count"><?php echo count($teamMembers); ?></span>)
      </h3>

      <div class="space-y-2.5" id="team-list-container">
        <!-- Super Admin Tile -->
        <div class="uber-card p-4 rounded-2xl border border-amber-400/40 bg-amber-500/5 flex flex-wrap justify-between items-center gap-2 text-xs">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-amber-500/20 text-amber-300 flex items-center justify-center font-black">
              ðŸ‘‘
            </div>
            <div>
              <div class="flex items-center gap-2">
                <span class="font-black text-white text-sm">Super Admin</span>
                <span class="text-[10px] bg-amber-400/20 text-amber-300 border border-amber-400/40 px-2 py-0.5 rounded font-black uppercase">Root Owner</span>
              </div>
              <span class="text-slate-400">ðŸ“ž +91 8199000000 â€¢ admin@pavancab-demo.local</span>
            </div>
          </div>
        </div>

        <?php if (empty($teamMembers)): ?>
          <div class="uber-card p-8 rounded-2xl text-center text-slate-400 border border-slate-800 space-y-1" id="team-empty">
            <p class="text-xs font-bold text-white">No additional team members added yet.</p>
            <p class="text-[11px] text-slate-500">Use the form on the left to add dispatch staff.</p>
          </div>
        <?php else: ?>
          <?php foreach ($teamMembers as $tm): ?>
            <div class="uber-card p-4 rounded-2xl border border-slate-800 flex flex-wrap justify-between items-center gap-2 text-xs hover:border-slate-700 transition" data-team-id="<?php echo (int)$tm['id']; ?>">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center font-black">
                  ðŸ‘¤
                </div>
                <div>
                  <div class="flex items-center gap-2">
                    <span class="font-black text-white"><?php echo htmlspecialchars($tm['member_name'] ?? $tm['name'] ?? 'Staff'); ?></span>
                    <span class="text-[10px] bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 px-2 py-0.5 rounded font-bold uppercase">Dispatcher</span>
                  </div>
                  <span class="text-slate-400">ðŸ“ž +91 <?php echo htmlspecialchars($tm['member_phone'] ?? $tm['phone'] ?? ''); ?></span>
                  <?php if (!empty($tm['member_email'] ?? $tm['email'])): ?>
                    <span class="text-slate-500 ml-1">â€¢ <?php echo htmlspecialchars($tm['member_email'] ?? $tm['email']); ?></span>
                  <?php endif; ?>
                </div>
              </div>

              <div class="flex items-center gap-2">
                <button onclick="handleRemoveTeamMember(<?php echo $tm['id']; ?>)" class="text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 px-3 py-1.5 rounded-xl border border-red-500/30 transition flex items-center gap-1 font-bold text-xs">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Remove
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
function renderTeamMemberHTML(tm) {
  const name = tm.member_name || tm.name || 'Staff';
  const phone = tm.member_phone || tm.phone || '';
  const email = tm.member_email || tm.email || '';
  return `<div class="uber-card p-4 rounded-2xl border border-slate-800 flex flex-wrap justify-between items-center gap-2 text-xs hover:border-slate-700 transition" data-team-id="${tm.id}">
    <div class="flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center font-black">ðŸ‘¤</div>
      <div>
        <div class="flex items-center gap-2">
          <span class="font-black text-white">${name}</span>
          <span class="text-[10px] bg-indigo-500/20 text-indigo-300 border border-indigo-500/40 px-2 py-0.5 rounded font-bold uppercase">Dispatcher</span>
        </div>
        <span class="text-slate-400">ðŸ“ž +91 ${phone}</span>
        ${email ? `<span class="text-slate-500 ml-1">â€¢ ${email}</span>` : ''}
      </div>
    </div>
    <div class="flex items-center gap-2">
      <button onclick="handleRemoveTeamMember(${tm.id})" class="text-red-400 hover:text-red-300 bg-red-500/10 hover:bg-red-500/20 px-3 py-1.5 rounded-xl border border-red-500/30 transition flex items-center gap-1 font-bold text-xs">
        <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Remove
      </button>
    </div>
  </div>`;
}

async function refreshTeamLive() {
  try {
    const res = await fetch('../api_dashboard.php?action=team');
    const rows = await res.json();
    const list = document.getElementById('team-list-container');
    if (!list || !Array.isArray(rows)) return;
    const adminTile = list.querySelector('.border-amber-400\\/40') || list.firstElementChild;
    let html = adminTile ? adminTile.outerHTML : '';
    if (rows.length === 0) {
      html += `<div class="uber-card p-8 rounded-2xl text-center text-slate-400 border border-slate-800 space-y-1" id="team-empty">
        <p class="text-xs font-bold text-white">No additional team members added yet.</p>
        <p class="text-[11px] text-slate-500">Use the form on the left to add dispatch staff.</p>
      </div>`;
    } else {
      html += rows.map(renderTeamMemberHTML).join('');
    }
    list.innerHTML = html;
    const countEl = document.getElementById('team-count');
    if (countEl) countEl.innerText = rows.length;
    if (typeof lucide !== 'undefined') lucide.createIcons();
  } catch (e) {}
}

async function handleAddTeamMember(e) {
  e.preventDefault();
  const name = document.getElementById('new_team_name').value.trim();
  const phone = document.getElementById('new_team_phone').value.trim();
  const email = document.getElementById('new_team_email').value.trim();
  const msg = document.getElementById('team-add-msg');

  msg.innerHTML = '<span class="text-amber-400 font-bold">Saving team member...</span>';

  try {
    const res = await fetch(`../api_dashboard.php?action=team&name=${encodeURIComponent(name)}&phone=${encodeURIComponent(phone)}&email=${encodeURIComponent(email)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ member_name: name, member_phone: phone, member_email: email, role: 'team' })
    });
    const data = await res.json();
    if (data.success) {
      msg.innerHTML = '<span class="text-emerald-400 font-bold">âœ“ Team Member added!</span>';
      // #region agent log
      fetch('http://127.0.0.1:7677/ingest/a4c1ae9b-9c91-43a8-8e20-83b11c083cfd',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'43d22e'},body:JSON.stringify({sessionId:'43d22e',hypothesisId:'H2',location:'team.php:handleAddTeamMember',message:'team add without reload',data:{ok:true},timestamp:Date.now()})}).catch(()=>{});
      // #endregion
      document.getElementById('new_team_name').value = '';
      document.getElementById('new_team_phone').value = '';
      document.getElementById('new_team_email').value = '';
      await refreshTeamLive();
    } else {
      msg.innerHTML = `<span class="text-red-400 font-bold">${data.error || 'Failed to add member'}</span>`;
    }
  } catch (e) {
    msg.innerHTML = '<span class="text-red-400 font-bold">Network connection error</span>';
  }
}

async function handleRemoveTeamMember(memberId) {
  if (!confirm('Are you sure you want to revoke dispatch dashboard access for this team member?')) return;
  try {
    const res = await fetch(`../api_dashboard.php?action=remove-team&id=${encodeURIComponent(memberId)}`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ id: memberId })
    });
    const data = await res.json();
    if (data.success) await refreshTeamLive();
    else alert(data.error || 'Failed to remove team member');
  } catch(e) {
    alert('Network error removing team member');
  }
}
</script>

<?php require_once __DIR__ . '/_layout_footer.php'; ?>
