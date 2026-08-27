<?php
/**
 * PAVANCAB GOA TAXI - Uber-Style Pure PHP Footer Template & WhatsApp Auth Modal
 * Path: app/footer.php
 */
$activePage   = $activePage ?? 'home';
$currentUser  = $_SESSION['user'] ?? null;
$isPrivileged = ($currentUser && in_array($currentUser['role'] ?? '', ['admin', 'team']));
?>
  </main>

  <!-- BRAND FOOTER -->
  <footer class="uber-card border-t border-slate-800/80 px-4 lg:px-8 py-8 mt-12 mb-16 md:mb-0">
    <div class="max-w-7xl mx-auto flex flex-wrap justify-between items-center gap-6">
      <div class="space-y-1">
        <div class="flex items-center gap-2">
          <div class="w-6 h-6 rounded-lg bg-amber-500/20 flex items-center justify-center p-0.5">
            <img src="./logo-pavancab.png" alt="PAVANCAB" class="w-full h-full object-contain" onError="this.onerror=null; this.src='https://pavancab.com/logo-pavancab.png';">
          </div>
          <span class="text-sm font-black text-white font-outfit uppercase tracking-widest">PAVANCAB GOA TAXI NETWORK</span>
        </div>
        <p class="text-xs text-slate-400">Guaranteed Lowest Fixed Fares â€¢ Mopa Airport, Dabolim Airport & North/South Goa Tours</p>
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
    <a href="./index.php" class="flex flex-col items-center gap-1 <?php echo $activePage === 'home' ? 'text-amber-400 font-extrabold' : 'text-slate-400 hover:text-white'; ?>">
      <i data-lucide="navigation" class="w-5 h-5"></i>
      <span class="text-[9px] uppercase font-black tracking-wider">Book Cab</span>
    </a>
    <a href="./rides.php" class="flex flex-col items-center gap-1 <?php echo $activePage === 'rides' ? 'text-amber-400 font-extrabold' : 'text-slate-400 hover:text-white'; ?>">
      <i data-lucide="clock" class="w-5 h-5"></i>
      <span class="text-[9px] uppercase font-black tracking-wider">My Rides</span>
    </a>
    <?php if ($isPrivileged): ?>
      <a href="./dashboard/index.php" class="flex flex-col items-center gap-1 <?php echo $activePage === 'dashboard' ? 'text-indigo-400 font-extrabold' : 'text-indigo-300 hover:text-white'; ?>">
        <i data-lucide="shield" class="w-5 h-5"></i>
        <span class="text-[9px] uppercase font-black tracking-wider">Dispatch</span>
      </a>
    <?php endif; ?>
    <?php if (!empty($currentUser)): ?>
      <a href="./auth.php?action=logout" class="flex flex-col items-center gap-1 text-slate-400 hover:text-red-400">
        <i data-lucide="log-out" class="w-5 h-5"></i>
        <span class="text-[9px] uppercase font-black tracking-wider">Logout</span>
      </a>
    <?php else: ?>
      <button id="mobile-login-btn" onclick="openAuthModal()" class="flex flex-col items-center gap-1 text-amber-400">
        <i data-lucide="user" class="w-5 h-5"></i>
        <span class="text-[9px] uppercase font-black tracking-wider">Login</span>
      </button>
    <?php endif; ?>
  </nav>

  <!-- âš ï¸ REPORT RIDE ISSUE MODAL -->
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
        <p class="text-xs text-slate-400">Pavan Cab Safety Desk â€¢ We investigate all reported rides 24/7</p>
      </div>

      <div id="report-msg" class="text-xs text-center font-bold min-h-[20px]"></div>

      <form id="report-form" onsubmit="submitRideReport(event)" class="space-y-4">
        <input type="hidden" id="report-booking-id" value="">
        
        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1.5">Issue Category</label>
          <div class="grid grid-cols-2 gap-2" id="report-category-group">
            <button type="button" onclick="selectReportCategory('SAFETY', this)" class="report-cat-btn bg-amber-400 text-slate-950 font-black text-[11px] py-2 px-3 rounded-xl border border-amber-400 text-left transition">
              ðŸ›¡ï¸ Safety Concern
            </button>
            <button type="button" onclick="selectReportCategory('DRIVER_BEHAVIOR', this)" class="report-cat-btn bg-slate-900 text-slate-300 font-bold text-[11px] py-2 px-3 rounded-xl border border-slate-800 hover:border-amber-400/50 text-left transition">
              ðŸ—£ï¸ Driver Behavior
            </button>
            <button type="button" onclick="selectReportCategory('OVERCHARGING', this)" class="report-cat-btn bg-slate-900 text-slate-300 font-bold text-[11px] py-2 px-3 rounded-xl border border-slate-800 hover:border-amber-400/50 text-left transition">
              ðŸ’° Fare Dispute
            </button>
            <button type="button" onclick="selectReportCategory('ROUTE_DEVIATION', this)" class="report-cat-btn bg-slate-900 text-slate-300 font-bold text-[11px] py-2 px-3 rounded-xl border border-slate-800 hover:border-amber-400/50 text-left transition">
              ðŸ—ºï¸ Route Deviation
            </button>
            <button type="button" onclick="selectReportCategory('VEHICLE_CONDITION', this)" class="report-cat-btn bg-slate-900 text-slate-300 font-bold text-[11px] py-2 px-3 rounded-xl border border-slate-800 hover:border-amber-400/50 text-left transition">
              ðŸš– Cab Condition
            </button>
            <button type="button" onclick="selectReportCategory('LOST_ITEM', this)" class="report-cat-btn bg-slate-900 text-slate-300 font-bold text-[11px] py-2 px-3 rounded-xl border border-slate-800 hover:border-amber-400/50 text-left transition">
              ðŸ’¼ Lost Item
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
      <!-- Close button -->
      <button onclick="closeAuthModal()" class="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full hover:bg-slate-800 transition">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>

      <!-- Modal Header -->
      <div class="text-center space-y-2">
        <div class="w-14 h-14 rounded-2xl bg-gradient-to-tr from-emerald-500/20 via-amber-500/20 to-emerald-500/10 border border-emerald-500/30 flex items-center justify-center mx-auto text-emerald-400 shadow-lg">
          <i data-lucide="message-circle" class="w-7 h-7"></i>
        </div>
        <h3 class="text-xl font-black text-white font-outfit uppercase tracking-tight">WhatsApp Login</h3>
        <p class="text-xs text-slate-400">Instant OTP verification for Riders, Drivers & Dispatch Admins</p>
      </div>

      <!-- Notification Message Box -->
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
                <option value="+91" selected>ðŸ‡®ðŸ‡³ +91 (India)</option>
                <option value="+44">ðŸ‡¬ðŸ‡§ +44 (UK)</option>
                <option value="+1">ðŸ‡ºðŸ‡¸ +1 (USA/CA)</option>
                <option value="+971">ðŸ‡¦ðŸ‡ª +971 (UAE)</option>
                <option value="+7">ðŸ‡·ðŸ‡º +7 (Russia)</option>
                <option value="+49">ðŸ‡©ðŸ‡ª +49 (Germany)</option>
                <option value="+61">ðŸ‡¦ðŸ‡º +61 (Australia)</option>
                <option value="+33">ðŸ‡«ðŸ‡· +33 (France)</option>
                <option value="+65">ðŸ‡¸ðŸ‡¬ +65 (Singapore)</option>
                <option value="+966">ðŸ‡¸ðŸ‡¦ +966 (Saudi)</option>
                <option value="+974">ðŸ‡¶ðŸ‡¦ +974 (Qatar)</option>
                <option value="+968">ðŸ‡´ðŸ‡² +968 (Oman)</option>
                <option value="+965">ðŸ‡°ðŸ‡¼ +965 (Kuwait)</option>
                <option value="+973">ðŸ‡§ðŸ‡­ +973 (Bahrain)</option>
                <option value="+66">ðŸ‡¹ðŸ‡­ +66 (Thailand)</option>
                <option value="+60">ðŸ‡²ðŸ‡¾ +60 (Malaysia)</option>
                <option value="+39">ðŸ‡®ðŸ‡¹ +39 (Italy)</option>
                <option value="+34">ðŸ‡ªðŸ‡¸ +34 (Spain)</option>
                <option value="+31">ðŸ‡³ðŸ‡± +31 (Netherlands)</option>
                <option value="+41">ðŸ‡¨ðŸ‡­ +41 (Switzerland)</option>
                <option value="+972">ðŸ‡®ðŸ‡± +972 (Israel)</option>
                <option value="+81">ðŸ‡¯ðŸ‡µ +81 (Japan)</option>
                <option value="+86">ðŸ‡¨ðŸ‡³ +86 (China)</option>
                <option value="+880">ðŸ‡§ðŸ‡© +880 (Bangladesh)</option>
                <option value="+977">ðŸ‡³ðŸ‡µ +977 (Nepal)</option>
                <option value="+94">ðŸ‡±ðŸ‡° +94 (Sri Lanka)</option>
                <option value="custom">ðŸŒ Other (+..)</option>
              </select>
              <div class="absolute right-2 top-4 pointer-events-none text-slate-400 text-[10px]">â–¼</div>
            </div>
            <div class="relative flex-1 min-w-0">
              <input type="tel" id="login-phone" placeholder="9876543210" maxlength="16" required class="w-full h-[46px] px-3.5 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400 placeholder:text-slate-600 tracking-wider">
            </div>
          </div>
          <div id="custom-country-code-container" class="hidden mt-2">
            <input type="text" id="custom-country-code" placeholder="Enter custom country code (e.g. +351)" class="w-full px-3.5 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-amber-300 font-mono focus:outline-none focus:border-amber-400">
          </div>
          <span class="text-[10px] text-slate-500 mt-1 block">Super Admin: 8199000000 â€¢ Team: Assigned Dispatchers</span>
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
            <button type="button" onclick="handleSendOtp(event)" class="text-[11px] text-amber-400 hover:underline font-bold">
              Resend OTP
            </button>
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

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      if (typeof lucide !== 'undefined') {
        lucide.createIcons();
      }
    });

    function openReportModal(bookingId) {
      document.getElementById('report-booking-id').value = bookingId;
      document.getElementById('report-modal').classList.remove('hidden');
      document.getElementById('report-msg').innerHTML = '';
      document.getElementById('report-description').value = '';
    }

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
      const category  = document.getElementById('report-selected-category').value;
      const description = document.getElementById('report-description').value.trim();
      const msg = document.getElementById('report-msg');
      const btn = document.getElementById('report-submit-btn');

      if (!bookingId || !description) {
        msg.innerHTML = '<span class="text-amber-400 font-bold">Please fill in details.</span>';
        return;
      }

      btn.disabled = true;
      btn.innerHTML = '<span class="animate-spin inline-block mr-1">â³</span> Submitting Report...';

      try {
        const res = await fetch('./api.php?action=submit_ride_report', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            action: 'submit_ride_report',
            booking_id: bookingId,
            issue_category: category,
            description: description
          })
        });
        const data = await res.json();

        if (data.success) {
          msg.innerHTML = `<div class="bg-emerald-500/20 border border-emerald-500 text-emerald-300 p-3 rounded-xl font-bold">âœ“ ${data.message}</div>`;
          btn.innerHTML = 'âœ“ Report Submitted';
          // #region agent log
          fetch('http://127.0.0.1:7677/ingest/a4c1ae9b-9c91-43a8-8e20-83b11c083cfd',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'43d22e'},body:JSON.stringify({sessionId:'43d22e',hypothesisId:'H3',location:'footer.php:submitReport',message:'report submit without reload',data:{ok:true},timestamp:Date.now()})}).catch(()=>{});
          // #endregion
          setTimeout(() => {
            closeReportModal();
            btn.disabled = false;
            btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Submit Report to Team';
            if (typeof lucide !== 'undefined') lucide.createIcons();
          }, 1200);
        } else {
          msg.innerHTML = `<span class="text-red-400 font-bold">${data.error || 'Failed to submit report'}</span>`;
          btn.disabled = false;
          btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Submit Report to Team';
        }
      } catch (err) {
        msg.innerHTML = '<span class="text-red-400 font-bold">Network error while sending report.</span>';
        btn.disabled = false;
        btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Submit Report to Team';
      }
    }

    function openAuthModal() {
      const modal = document.getElementById('auth-modal');
      modal.classList.remove('hidden');
      document.getElementById('auth-msg').innerHTML = '';
      document.getElementById('otp-form').classList.remove('hidden');
      document.getElementById('verify-otp-form').classList.add('hidden');
      setTimeout(() => document.getElementById('login-phone').focus(), 100);
    }
    window.openLoginModal = openAuthModal;

    function applySoftLoggedInHeader(user) {
      if (!user) return;
      window.currentUser = user;
      window.isUserLoggedIn = true;
      window.currentUserPhone = user.mobile || '';
      window.currentUserName = user.name || '';

      const headerBtn = document.getElementById('header-login-btn');
      if (headerBtn) {
        const wrap = document.createElement('div');
        wrap.className = 'flex items-center gap-2 bg-slate-900/90 border border-slate-800 px-2.5 sm:px-3.5 py-1.5 rounded-2xl shadow-inner';
        wrap.innerHTML = `<span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
          <span class="text-xs font-extrabold text-slate-200 hidden sm:inline-block">${user.name || user.mobile || 'User'}</span>
          <a href="./auth.php?action=logout" title="Logout" class="text-xs text-slate-400 hover:text-red-400 transition p-1 hover:bg-red-500/10 rounded-lg">
            <i data-lucide="log-out" class="w-3.5 h-3.5"></i>
          </a>`;
        headerBtn.replaceWith(wrap);
      }
      const mobileBtn = document.getElementById('mobile-login-btn');
      if (mobileBtn) {
        const a = document.createElement('a');
        a.href = './auth.php?action=logout';
        a.className = 'flex flex-col items-center gap-1 text-slate-400 hover:text-red-400';
        a.innerHTML = '<i data-lucide="log-out" class="w-5 h-5"></i><span class="text-[9px] uppercase font-black tracking-wider">Logout</span>';
        mobileBtn.replaceWith(a);
      }

      // Dynamically reveal Step 4 booking inputs
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

    function closeAuthModal() {
      document.getElementById('auth-modal').classList.add('hidden');
    }

    function handleCountryCodeChange() {
      const select = document.getElementById('login-country-code');
      const customContainer = document.getElementById('custom-country-code-container');
      const phoneInput = document.getElementById('login-phone');
      
      if (select && select.value === 'custom') {
        if (customContainer) customContainer.classList.remove('hidden');
        const customInput = document.getElementById('custom-country-code');
        if (customInput) setTimeout(() => customInput.focus(), 50);
      } else {
        if (customContainer) customContainer.classList.add('hidden');
      }

      if (phoneInput) {
        phoneInput.placeholder = (select && select.value === '+91') ? '9876543210' : 'Enter mobile number';
      }
    }

    function getNormalizedAuthPhone() {
      const select = document.getElementById('login-country-code');
      const customInput = document.getElementById('custom-country-code');
      const phoneInput = document.getElementById('login-phone');
      let rawPhone = (phoneInput ? phoneInput.value : '').trim();
      
      if (!rawPhone) return '';

      // If user typed full international number with + directly in the input
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

      btn.innerHTML = '<span class="animate-spin inline-block mr-1">â³</span> Sending...';
      btn.disabled = true;
      msg.innerHTML = '';

      try {
        const res = await fetch(`./auth.php?action=send_otp&phone=${encodeURIComponent(fullPhone)}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ phone: fullPhone })
        });
        const data = await res.json();

        if (data.success) {
          msg.innerHTML = `<span class="text-emerald-400 font-bold">âœ“ ${data.message}</span>`;
          document.getElementById('otp-form').classList.add('hidden');
          document.getElementById('verify-otp-form').classList.remove('hidden');
          
          if (typeof lucide !== 'undefined') lucide.createIcons();
          setTimeout(() => document.getElementById('login-otp').focus(), 100);
        } else {
          msg.innerHTML = `<span class="text-red-400">${data.error || 'Failed to send OTP'}</span>`;
          btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Send WhatsApp OTP';
          btn.disabled = false;
          if (typeof lucide !== 'undefined') lucide.createIcons();
        }
      } catch (err) {
        msg.innerHTML = '<span class="text-red-400">Network error. Please try again.</span>';
        btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Send WhatsApp OTP';
        btn.disabled = false;
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }
    }

    async function handleVerifyOtp(e) {
      if (e) e.preventDefault();
      const fullPhone = getNormalizedAuthPhone();
      const otp   = document.getElementById('login-otp').value.trim();
      const name  = document.getElementById('login-name').value.trim();
      const msg   = document.getElementById('auth-msg');
      const btn   = document.getElementById('verify-otp-btn');

      if (!otp || otp.length < 6) {
        msg.innerHTML = '<span class="text-amber-400">Please enter 6-digit OTP.</span>';
        return;
      }

      btn.innerHTML = '<span class="animate-spin inline-block mr-1">â³</span> Verifying...';
      btn.disabled = true;

      try {
        let tokenToSend = window.fcmTokenStored || '';
        if (!tokenToSend && 'Notification' in window && globalFcmMessaging && globalFcmReg) {
          try {
            if (Notification.permission === 'default') {
              const perm = await Notification.requestPermission();
              if (perm === 'granted') {
                tokenToSend = await globalFcmMessaging.getToken({
                  vapidKey: VAPID_KEY,
                  serviceWorkerRegistration: globalFcmReg
                });
                if (tokenToSend) {
                  window.fcmTokenStored = tokenToSend;
                  try { localStorage.setItem('pavancab_fcm_token', tokenToSend); } catch(e){}
                }
              }
            } else if (Notification.permission === 'granted') {
              tokenToSend = await globalFcmMessaging.getToken({
                vapidKey: VAPID_KEY,
                serviceWorkerRegistration: globalFcmReg
              });
              if (tokenToSend) {
                window.fcmTokenStored = tokenToSend;
                try { localStorage.setItem('pavancab_fcm_token', tokenToSend); } catch(e){}
              }
            }
          } catch(e) {}
        }

        const res = await fetch(`./auth.php?action=verify_otp&phone=${encodeURIComponent(fullPhone)}&otp=${encodeURIComponent(otp)}&name=${encodeURIComponent(name)}&fcm_token=${encodeURIComponent(tokenToSend)}`, {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ phone: fullPhone, otp: otp, name: name, fcm_token: tokenToSend })
        });
        const data = await res.json();

        if (data.success) {
          try { localStorage.setItem('pavancab_user_phone', data.user.mobile || fullPhone); } catch(e){}
          if (data.user.email) {
            try { localStorage.setItem('pavancab_user_email', data.user.email); } catch(e){}
          }
          const roleLabel = data.user.role === 'admin' ? 'ðŸ‘‘ Super Admin' : (data.user.role === 'team' ? 'ðŸ›¡ï¸ Team Dispatcher' : 'ðŸš– Passenger');
          msg.innerHTML = `<span class="text-emerald-400 font-black">âœ“ Welcome, ${data.user.name} (${roleLabel})!</span>`;
          window.currentUser = data.user;
          window.isUserLoggedIn = true;
          window.isLoggedIn = true;
          window.sessionUserPhone = data.user.mobile || fullPhone;
          window.sessionUserEmail = data.user.email || '';
          window.sessionUserRole  = data.user.role || 'user';
          window.currentUserPhone = data.user.mobile || '';
          window.currentUserName = data.user.name || '';

          if (typeof initFcmPush === 'function') {
            initFcmPush();
          }

          if (typeof window.syncFcmTokenWithUser === 'function') {
            window.syncFcmTokenWithUser(data.user);
          }

          setTimeout(() => {
            closeAuthModal();
            // Admin/team need dispatch shell once; passengers stay on page live
            if (data.user.role === 'admin' || data.user.role === 'team') {
              window.location.href = data.redirect || './dashboard/index.html';
            } else {
              applySoftLoggedInHeader(data.user);
              const nameInput = document.getElementById('customer_name');
              const phoneInput = document.getElementById('customer_phone');
              if (nameInput && data.user.name) nameInput.value = data.user.name;
              if (phoneInput && data.user.mobile) phoneInput.value = data.user.mobile;
            }
          }, 500);
        } else {
          msg.innerHTML = `<span class="text-red-400 font-bold">${data.error || 'Invalid OTP code'}</span>`;
          btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i> Verify & Login';
          btn.disabled = false;
          if (typeof lucide !== 'undefined') lucide.createIcons();
        }
      } catch (err) {
        msg.innerHTML = '<span class="text-red-400">Network connection error.</span>';
        btn.innerHTML = '<i data-lucide="check-circle" class="w-4 h-4"></i> Verify & Login';
        btn.disabled = false;
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }
    }

    function requestPushPermission() {
      if ('Notification' in window && Notification.permission === 'default') {
        Notification.requestPermission();
      }
    }

    function showBrowserNotification(title, body, targetUrl = null, eventType = 'default') {
      const FCM_TONES = {
        'NEW_BOOKING':     { url: 'https://assets.mixkit.co/active_storage/sfx/2771/2771-preview.mp3', vibrate: [200, 50, 200, 50, 300], icon: 'ðŸš•' },
        'DRIVER_ASSIGNED': { url: 'https://assets.mixkit.co/active_storage/sfx/2020/2020-preview.mp3', vibrate: [100, 50, 100, 50, 200, 50, 200], icon: 'ðŸš–' },
        'DRIVER_ACCEPTED': { url: 'https://assets.mixkit.co/active_storage/sfx/2000/2000-preview.mp3', vibrate: [100, 30, 100, 30, 100, 30, 300], icon: 'âœ…' },
        'RIDE_STARTED':    { url: 'https://assets.mixkit.co/active_storage/sfx/1999/1999-preview.mp3', vibrate: [300, 100, 300, 100, 400], icon: 'ðŸ' },
        'IN_TRANSIT':      { url: 'https://assets.mixkit.co/active_storage/sfx/1999/1999-preview.mp3', vibrate: [300, 100, 300, 100, 400], icon: 'ðŸ›£ï¸' },
        'RIDE_COMPLETED':  { url: 'https://assets.mixkit.co/active_storage/sfx/2770/2770-preview.mp3', vibrate: [50, 30, 50, 30, 200, 50, 200], icon: 'ðŸŽ‰' },
        'CANCELLED':       { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [400, 100, 400], icon: 'ðŸš«' },
        'FARE_BOOSTED':    { url: 'https://assets.mixkit.co/active_storage/sfx/2770/2770-preview.mp3', vibrate: [50, 30, 50, 30, 50, 30, 200], icon: 'ðŸ”¥' },
        'FARE_UPDATED':    { url: 'https://assets.mixkit.co/active_storage/sfx/2770/2770-preview.mp3', vibrate: [100, 50, 100, 50, 200], icon: 'ðŸ’°' },
        'DRIVER_DECLINED': { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [400, 200, 400, 200, 400], icon: 'ðŸ”„' },
        'RIDE_RESET':      { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [300, 100, 300, 100, 300], icon: 'ðŸ”„' },
        'default':         { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [300, 100, 300, 100, 400], icon: 'ðŸ””' }
      };
      const tone = FCM_TONES[eventType] || FCM_TONES['default'];

      try {
        const audio = new Audio(tone.url);
        audio.volume = 1.0;
        audio.play().catch(()=>{});
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
        } catch (e) {
          try { new Notification(title, { body: body, icon: 'https://pavancab.com/app/logo-pavancab.png' }); } catch(err){}
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
        toast.innerHTML = `
          <div class="w-8 h-8 rounded-xl bg-amber-400/20 text-amber-400 flex items-center justify-center font-bold text-base shrink-0">${tone.icon}</div>
          <div class="flex-1 space-y-0.5 min-w-0">
            <div class="font-black text-amber-300 text-xs uppercase tracking-wide truncate">${title}</div>
            <div class="text-[11px] text-slate-200 font-medium leading-tight">${body}</div>
            <div class="text-[9px] text-amber-400/80 font-mono flex items-center gap-1 pt-1">
              <span>Tap to open page âž”</span>
            </div>
          </div>
        `;
        container.appendChild(toast);
        setTimeout(() => toast.remove(), 8000);
      } catch(e){}
    }

    document.addEventListener('click', requestPushPermission, { once: true });
  </script>

  <!-- FIREBASE CLOUD MESSAGING CLIENT SDK -->
  <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
  <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js"></script>

  <!-- FCM permission banner removed - auto-connects silently for logged-in users -->

  <script>
    <?php 
      $sessUserPhone = cleanPhoneDigits($_SESSION['user']['mobile'] ?? $_SESSION['user']['phone'] ?? '');
      $sessUserEmail = addslashes($_SESSION['user']['email'] ?? '');
      $sessUserRole  = addslashes($_SESSION['user']['role'] ?? '');
      $isLoggedIn    = !empty($_SESSION['user']);
    ?>
    window.isLoggedIn       = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
    window.sessionUserPhone = '<?php echo $sessUserPhone; ?>';
    window.sessionUserEmail = '<?php echo $sessUserEmail; ?>';
    window.sessionUserRole  = '<?php echo $sessUserRole; ?>';

    if (window.sessionUserPhone) {
      try { localStorage.setItem('pavancab_user_phone', window.sessionUserPhone); } catch(e){}
    }
    if (window.sessionUserEmail) {
      try { localStorage.setItem('pavancab_user_email', window.sessionUserEmail); } catch(e){}
    }

    // Instant Diagnostic Reporter & Auto-Sync
    (function() {
      try {
        const hasSW = ('serviceWorker' in navigator);
        const hasNotif = ('Notification' in window);
        const notifPerm = hasNotif ? Notification.permission : 'N/A';
        const hasFirebase = (typeof firebase !== 'undefined');
        const phone = window.sessionUserPhone || (function(){ try { return localStorage.getItem('pavancab_user_phone') || ''; } catch(e){ return ''; } })();

        fetch('/app/api.php?action=fcm_debug', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({
            msg: `ENV: hasSW=${hasSW}, hasNotif=${hasNotif}, perm=${notifPerm}, firebase=${hasFirebase}`,
            user_mobile: phone
          })
        }).catch(()=>{});
      } catch(e){}
    })();

    const firebaseConfig = {
      apiKey: "AIzaSyA9_ZfLPnMew12a0g4EtikIne2jn7nEIkY",
      authDomain: "pavancab-4a1daa.firebaseapp.com",
      projectId: "pavancab-4a1daa",
      storageBucket: "pavancab-4a1daa.firebasestorage.app",
      messagingSenderId: "1064014558872",
      appId: "1:1064014558872:web:bb0b2d5ae8e4e79a0ac2ed"
    };

    const VAPID_KEY = "BBiDdmppgS12OFBx7KLwtb4eD7B2R57ESg5hLTXH384DUWwdoMzyAqhl2RyPWf_hVsSnZXt5uetIHhSOgyfccjA";

    window.fcmTokenStored = window.fcmTokenStored || (function(){ try{ return localStorage.getItem('pavancab_fcm_token'); }catch(e){ return null; } })() || null;
    let globalFcmMessaging = null;
    let globalFcmReg = null;

    // Define globally immediately so all scripts can queue or call anytime
    window.syncFcmTokenWithUser = function(user) {
      if (user && user.mobile) {
        try { localStorage.setItem('pavancab_user_phone', user.mobile); } catch(e){}
      }
      if (user && user.email) {
        try { localStorage.setItem('pavancab_user_email', user.email); } catch(e){}
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
            showBrowserNotification('ðŸ”” Push Notifications Active!', 'You will now receive live Goa ride updates & dispatch alerts.', null, 'default');
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

    // Native Android App / WebView Bridge Callback
    window.receiveNativeFcmToken = async function(token) {
      if (!token) return;
      window.fcmTokenStored = token;
      try { localStorage.setItem('pavancab_fcm_token', token); } catch(e){}
      const storedPhone = (function() { try { return localStorage.getItem('pavancab_user_phone') || ''; } catch(e){ return ''; } })();
      const mobile = window.sessionUserPhone || storedPhone || '';
      const email  = window.sessionUserEmail || '';
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

    async function initFcmPush() {
      try {
        if (typeof firebase === 'undefined') {
          console.warn('[FCM] Firebase SDK not loaded');
          return;
        }

        if (typeof firebase.messaging.isSupported === 'function') {
          const supported = await firebase.messaging.isSupported();
          if (!supported) {
            fetch('/app/api.php?action=fcm_debug', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json' },
              body: JSON.stringify({
                msg: 'FCM isSupported() returned FALSE on this device/browser',
                user_mobile: window.sessionUserPhone || localStorage.getItem('pavancab_user_phone') || ''
              })
            }).catch(()=>{});
            return;
          }
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

        // Auto-request notification permission for logged-in users (silent)
        if (window.isLoggedIn && window.sessionUserPhone) {
          if (Notification.permission === 'default') {
            try {
              const perm = await Notification.requestPermission();
              if (perm === 'granted') {
                fetchAndSaveFcmToken(messaging, globalFcmReg);
              }
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
              }).catch(()=>{});
            }
            fetchAndSaveFcmToken(messaging, globalFcmReg);
          }
        }

        // Foreground Message Handlers (Both Firebase SDK + Native Service Worker Channel)
        messaging.onMessage((payload) => {
          console.log('[FCM Client] Foreground push received via SDK:', payload);
          const title = payload.notification?.title || payload.data?.title || 'ðŸš• PAVANCAB Alert';
          const body  = payload.notification?.body || payload.data?.body || 'New ride update received!';
          const url   = payload.data?.url || payload.data?.click_action || '/app/rides.php';
          const evtType = payload.data?.event_type || payload.data?.status || 'default';
          showBrowserNotification(title, body, url, evtType);
        });

        if ('serviceWorker' in navigator) {
          navigator.serviceWorker.addEventListener('message', (event) => {
            console.log('[FCM Client] Message received from SW:', event.data);
            if (event.data && (event.data.type === 'FCM_PUSH_RECEIVED' || event.data.title)) {
              const title = event.data.title || 'ðŸš• PAVANCAB Alert';
              const body  = event.data.body  || 'New ride update received!';
              const url   = event.data.url   || event.data.click_action || '/app/rides.php';
              const evtType = event.data.event_type || 'default';
              showBrowserNotification(title, body, url, evtType);
            }
          });
        }

        // Periodic background re-sync
        setInterval(() => {
          if (Notification.permission === 'granted') {
            fetchAndSaveFcmToken(messaging, globalFcmReg);
          }
        }, 120000); // Every 2 minutes

      } catch (err) {
        console.warn('FCM Push Init warning:', err);
      }
    }

    function urlB64ToUint8Array(base64String) {
      const padding = '='.repeat((4 - base64String.length % 4) % 4);
      const base64 = (base64String + padding).replace(/\-/g, '+').replace(/_/g, '/');
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
          sub = await reg.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: appServerKey
          });
        }
        if (sub && sub.endpoint) {
          const parts = sub.endpoint.split('/');
          const rawToken = parts[parts.length - 1];
          if (rawToken && rawToken.length > 50) {
            console.log('[Native Push] Extracted FCM token from endpoint:', rawToken.substring(0, 20) + '...');
            return rawToken;
          }
        }
      } catch(err) {
        console.warn('[Native Push] subscription fallback warning:', err);
      }
      return null;
    }

    async function cleanFirebaseIndexedDBAndRetry(messaging, reg) {
      try {
        console.warn('[FCM] Auto-healing IndexedDB Version mismatch...');
        if ('indexedDB' in window) {
          try { indexedDB.deleteDatabase('firebase-installations-database'); } catch(e){}
          try { indexedDB.deleteDatabase('firebase-messaging-database'); } catch(e){}
          try { indexedDB.deleteDatabase('firebaseInstallations'); } catch(e){}
          try { indexedDB.deleteDatabase('fcm_vapid_details'); } catch(e){}
        }
        await new Promise(r => setTimeout(r, 200));
        let tok = null;
        if (reg) {
          try {
            tok = await messaging.getToken({
              vapidKey: VAPID_KEY,
              serviceWorkerRegistration: reg
            });
          } catch(e){}
        }
        if (!tok) {
          try {
            tok = await messaging.getToken({ vapidKey: VAPID_KEY });
          } catch(e){}
        }
        if (!tok && reg) {
          tok = await getNativeWebPushToken(reg);
        }
        return tok;
      } catch(e) {
        console.warn('[FCM] Retry after cleanup failed:', e);
        return null;
      }
    }

    async function fetchAndSaveFcmToken(messaging, reg, customUser = null) {
      try {
        if (!messaging) messaging = globalFcmMessaging;
        if (!reg) reg = globalFcmReg;
        if (!messaging && !reg) return;

        let token = null;
        try {
          if (messaging && reg) {
            token = await messaging.getToken({
              vapidKey: VAPID_KEY,
              serviceWorkerRegistration: reg
            });
          }
        } catch (tokErr1) {
          const errStr1 = (tokErr1.message || tokErr1.toString());
          console.warn('[FCM] getToken with reg warning:', errStr1);
          if (errStr1.includes('version') || errStr1.includes('less than') || errStr1.includes('IndexedDB')) {
            token = await cleanFirebaseIndexedDBAndRetry(messaging, reg);
          }
        }

        if (!token && messaging) {
          try {
            token = await messaging.getToken({ vapidKey: VAPID_KEY });
          } catch (tokErr2) {
            const errStr2 = (tokErr2.message || tokErr2.toString());
            console.warn('[FCM] Fallback getToken warning:', errStr2);
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
          try { localStorage.setItem('pavancab_fcm_token', token); } catch(e){}
          console.log('[FCM] Live Token synchronized:', token.substring(0, 20) + '...');

          const storedPhone = (function() { try { return localStorage.getItem('pavancab_user_phone') || ''; } catch(e){ return ''; } })();
          const storedEmail = (function() { try { return localStorage.getItem('pavancab_user_email') || ''; } catch(e){ return ''; } })();
          
          const mobile = customUser?.mobile || window.sessionUserPhone || window.currentUserPhone || (window.currentUser && (window.currentUser.mobile || window.currentUser.phone)) || storedPhone || '';
          const email  = customUser?.email || window.sessionUserEmail || (window.currentUser && window.currentUser.email) || storedEmail || '';
          const role   = customUser?.role || window.sessionUserRole || (window.currentUser && window.currentUser.role) || '';

          const apiUrl = '/app/api.php?action=save_fcm_token';

          await fetch(apiUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'save_fcm_token',
              fcm_token: token,
              user_mobile: mobile,
              user_email: email,
              role: role
            })
          });

          sendAppHeartbeat(1);
        }
      } catch (err) {
        console.warn('FCM Token sync error:', err);
      }
    }

    // Real-time App Open / Close (Online / Offline) Heartbeat Tracker
    function sendAppHeartbeat(isOnline = 1) {
      const storedPhone = (function() { try { return localStorage.getItem('pavancab_user_phone') || ''; } catch(e){ return ''; } })();
      const storedEmail = (function() { try { return localStorage.getItem('pavancab_user_email') || ''; } catch(e){ return ''; } })();
      const mobile = window.sessionUserPhone || window.currentUserPhone || (window.currentUser && (window.currentUser.mobile || window.currentUser.phone)) || storedPhone || '';
      const email  = window.sessionUserEmail || (window.currentUser && window.currentUser.email) || storedEmail || '';
      const token  = window.fcmTokenStored || (function() { try { return localStorage.getItem('pavancab_fcm_token') || ''; } catch(e){ return ''; } })() || '';
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
        }).catch(()=>{});
      }
    }

    // Initial heartbeat on page load & periodic refresh every 30s
    sendAppHeartbeat(1);
    setInterval(() => {
      if (document.visibilityState === 'visible') {
        sendAppHeartbeat(1);
      }
    }, 30000);

    // Refresh token sync & heartbeat when user switches tab
    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible') {
        sendAppHeartbeat(1);
        if (Notification.permission === 'granted') {
          fetchAndSaveFcmToken(globalFcmMessaging, globalFcmReg);
        }
      } else {
        sendAppHeartbeat(0);
      }
    });

    window.addEventListener('beforeunload', () => {
      sendAppHeartbeat(0);
    });
    window.addEventListener('pagehide', () => {
      sendAppHeartbeat(0);
    });

    // Intercept logout to cleanly purge FCM push tokens from server & browser
    document.addEventListener('click', function(e) {
      const link = e.target.closest('a[href*="action=logout"]');
      if (link) {
        e.preventDefault();
        const token = window.fcmTokenStored || (function(){ try{ return localStorage.getItem('pavancab_fcm_token'); }catch(e){ return ''; } })() || '';
        window.fcmTokenStored = null;
        try { localStorage.removeItem('pavancab_fcm_token'); } catch(e){}
        try { localStorage.removeItem('pavancab_user_phone'); } catch(e){}
        try { localStorage.removeItem('pavancab_user_email'); } catch(e){}
        
        if (globalFcmReg && globalFcmReg.pushManager) {
          try {
            globalFcmReg.pushManager.getSubscription().then(sub => {
              if (sub) sub.unsubscribe();
            }).catch(()=>{});
          } catch(e){}
        }

        if (globalFcmMessaging && typeof globalFcmMessaging.deleteToken === 'function') {
          try { globalFcmMessaging.deleteToken(); } catch(e){}
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
