  </main>

  <style>
    .wa-phone-wrap{position:relative;display:flex;align-items:stretch;border-radius:12px;overflow:hidden;border:1px solid #334155;background:#0f172a}
    .wa-phone-wrap:focus-within{border-color:#f59e0b}
    .wa-cc-btn{display:flex;align-items:center;gap:6px;padding:0 12px;background:#1e293b;border-right:1px solid #334155;cursor:pointer;user-select:none;min-width:80px;white-space:nowrap}
    .wa-cc-btn:hover{background:#334155}
    .wa-cc-btn .flag{font-size:16px;line-height:1}
    .wa-cc-btn .code{font-size:12px;font-weight:700;color:#f8fafc}
    .wa-cc-btn .chevron{font-size:8px;color:#94a3b8;margin-left:2px}
    .wa-phone-input{flex:1;border:none;outline:none;background:transparent;padding:10px 14px;font-size:13px;font-weight:700;color:#f8fafc;letter-spacing:.5px;font-family:inherit}
    .wa-phone-input::placeholder{color:#64748b;font-weight:500}
    .wa-country-dropdown{position:absolute;top:calc(100% + 4px);left:0;right:0;max-height:280px;background:#1e293b;border:1px solid #334155;border-radius:12px;z-index:100;overflow:hidden;display:none;box-shadow:0 20px 40px rgba(0,0,0,.5)}
    .wa-country-dropdown.open{display:block}
    .wa-country-search{width:100%;padding:10px 14px;border:none;border-bottom:1px solid #334155;background:#0f172a;color:#f8fafc;font-size:12px;font-weight:600;outline:none}
    .wa-country-search::placeholder{color:#64748b}
    .wa-country-list{max-height:220px;overflow-y:auto}
    .wa-country-list::-webkit-scrollbar{width:4px}
    .wa-country-list::-webkit-scrollbar-thumb{background:#475569;border-radius:4px}
    .wa-country-item{display:flex;align-items:center;gap:10px;padding:9px 14px;cursor:pointer;font-size:12px;font-weight:600;color:#e2e8f0;transition:background .1s}
    .wa-country-item:hover{background:#334155}
    .wa-country-item.active{background:#f59e0b;color:#0f172a}
    .wa-country-item .cflag{font-size:18px;line-height:1;width:24px;text-align:center}
    .wa-country-item .cname{flex:1}
    .wa-country-item .ccode{color:#94a3b8;font-size:11px;font-weight:700}
    .wa-country-item.active .ccode{color:#0f172a}
  </style>

  <!-- ========================================================= -->
  <!-- MODAL 1: ASSIGN / DISPATCH DRIVER MODAL                   -->
  <!-- ========================================================= -->
  <div id="assign-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md animate-fadeIn">
    <div class="uber-card w-full max-w-lg p-6 sm:p-8 rounded-3xl border border-slate-800 space-y-6 relative shadow-2xl">
      <button onclick="closeModal('assign-modal')" class="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>

      <div class="space-y-1">
        <h3 class="text-xl font-black text-white font-outfit uppercase tracking-tight">Assign Fleet Driver</h3>
        <p class="text-xs text-slate-400" id="assign-modal-ref">Select a saved driver partner or enter contact info.</p>
      </div>

      <form onsubmit="handleAssignDriver(event)" class="space-y-4">
        <input type="hidden" id="assign_booking_id">
        <input type="hidden" id="assign_driver_id">

        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1">Select from Fleet</label>
          <select onchange="handleSelectSavedDriver(this)" class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-sm font-bold text-white focus:outline-none focus:border-amber-400">
            <option value="">-- Choose Registered Fleet Driver --</option>
            <?php foreach ($drivers as $d): ?>
              <option value="<?php echo $d['id']; ?>" 
                      data-name="<?php echo htmlspecialchars($d['name']); ?>" 
                      data-phone="<?php echo htmlspecialchars($d['phone']); ?>" 
                      data-plate="<?php echo htmlspecialchars($d['plate_number'] ?? 'GA-03-T-1234'); ?>"
                      data-status="<?php echo htmlspecialchars($d['status'] ?? 'available'); ?>">
                <?php echo htmlspecialchars($d['name']); ?> (<?php echo htmlspecialchars($d['phone']); ?>) â€¢ <?php echo htmlspecialchars($d['plate_number'] ?? 'GA-03-T-1234'); ?> [<?php echo strtoupper($d['status'] ?? 'available'); ?>]
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Driver Name *</label>
            <input type="text" id="assign_driver_name" required placeholder="e.g. Ramesh Naik" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Driver Phone *</label>
            <div class="wa-phone-wrap" id="wa-assign">
              <div class="wa-cc-btn" onclick="toggleWaDropdown('assign')">
                <span class="flag">ðŸ‡®ðŸ‡³</span>
                <span class="code">+91</span>
                <span class="chevron">â–¼</span>
              </div>
              <input type="tel" id="assign_driver_phone" required placeholder="phone number" maxlength="15" class="wa-phone-input">
              <input type="hidden" id="assign_cc" value="91">
              <div class="wa-country-dropdown" id="wa-dd-assign">
                <input type="text" class="wa-country-search" placeholder="Search country..." oninput="filterWaCountries('assign',this.value)">
                <div class="wa-country-list" id="wa-list-assign"></div>
              </div>
            </div>
          </div>
        </div>

        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1">Vehicle Plate / Cab Number</label>
          <input type="text" id="assign_vehicle_number" placeholder="GA-03-T-1234" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400 uppercase font-mono">
        </div>

        <div id="assign-driver-warn" class="hidden text-xs bg-yellow-500/10 border border-yellow-500/30 text-yellow-300 p-2.5 rounded-xl font-medium"></div>
        <div id="assign-msg" class="text-xs font-bold text-center"></div>

        <button type="submit" id="assign-btn" class="w-full gradient-btn-gold text-slate-950 font-black text-xs py-3 rounded-xl uppercase flex items-center justify-center gap-2 shadow-xl">
          <i data-lucide="send" class="w-4 h-4"></i> Dispatch Driver via WhatsApp
        </button>
      </form>
    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 2: ADJUST / BOOST FARE MODAL                        -->
  <!-- ========================================================= -->
  <div id="edit-fare-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md animate-fadeIn">
    <div class="uber-card w-full max-w-md p-6 sm:p-8 rounded-3xl border border-slate-800 space-y-6 relative shadow-2xl">
      <button onclick="closeModal('edit-fare-modal')" class="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>

      <div class="space-y-1">
        <h3 class="text-xl font-black text-white font-outfit uppercase">Adjust / Boost Driver Fare</h3>
        <p class="text-xs text-slate-400" id="fare-modal-ref">Increase fixed fare to attract drivers or apply peak surge.</p>
      </div>

      <form onsubmit="handleSaveFareAdjustment(event)" class="space-y-4">
        <input type="hidden" id="fare_modal_booking_id">

        <div class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 flex justify-between items-center">
          <span class="text-xs text-slate-400 font-bold uppercase">Current Fare:</span>
          <span class="text-xl font-black text-amber-400 font-outfit" id="fare_modal_current_text">â‚¹0</span>
        </div>

        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1">New Total Fixed Fare (â‚¹)</label>
          <input type="number" id="fare_modal_new_amount" required min="100" step="50" class="w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-lg font-black text-white focus:outline-none focus:border-amber-400 font-outfit">
        </div>

        <!-- Quick Boost Shortcuts -->
        <div class="flex gap-2">
          <button type="button" onclick="quickAddBoost(100)" class="flex-1 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-amber-300 text-xs font-black py-2 rounded-xl transition">
            +â‚¹100
          </button>
          <button type="button" onclick="quickAddBoost(200)" class="flex-1 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-amber-300 text-xs font-black py-2 rounded-xl transition">
            +â‚¹200
          </button>
          <button type="button" onclick="quickAddBoost(500)" class="flex-1 gradient-btn-gold text-slate-950 text-xs font-black py-2 rounded-xl shadow transition">
            +â‚¹500 Peak
          </button>
        </div>

        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1">Surge Reason / Special Note</label>
          <input type="text" id="fare_modal_reason" placeholder="e.g. Late night surge / Monsoon peak" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400">
        </div>

        <div id="fare-edit-msg" class="text-xs font-bold text-center"></div>

        <button type="submit" class="w-full gradient-btn-gold text-slate-950 font-black text-xs py-3 rounded-xl uppercase flex items-center justify-center gap-2 shadow-xl">
          <i data-lucide="check" class="w-4 h-4"></i> Apply Updated Fare
        </button>
      </form>
    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 3: EDIT FULL BOOKING DETAILS                        -->
  <!-- ========================================================= -->
  <div id="edit-booking-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md animate-fadeIn">
    <div class="uber-card w-full max-w-xl p-6 sm:p-8 rounded-3xl border border-slate-800 space-y-5 relative shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
      <button onclick="closeModal('edit-booking-modal')" class="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>

      <div class="space-y-1">
        <h3 class="text-xl font-black text-white font-outfit uppercase">Edit Booking Itinerary</h3>
        <p class="text-xs text-slate-400" id="eb-modal-ref">Update customer, locations, or schedule.</p>
      </div>

      <form onsubmit="handleSaveEditBooking(event)" class="space-y-3.5">
        <input type="hidden" id="eb_id">

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Customer Name</label>
            <input type="text" id="eb_name" required class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Customer Phone</label>
            <div class="wa-phone-wrap" id="wa-eb">
              <div class="wa-cc-btn" onclick="toggleWaDropdown('eb')">
                <span class="flag">ðŸ‡®ðŸ‡³</span>
                <span class="code">+91</span>
                <span class="chevron">â–¼</span>
              </div>
              <input type="tel" id="eb_phone" required placeholder="phone number" maxlength="15" class="wa-phone-input">
              <input type="hidden" id="eb_cc" value="91">
              <div class="wa-country-dropdown" id="wa-dd-eb">
                <input type="text" class="wa-country-search" placeholder="Search country..." oninput="filterWaCountries('eb',this.value)">
                <div class="wa-country-list" id="wa-list-eb"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Pickup Location</label>
            <input type="text" id="eb_pickup" required class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Drop Location</label>
            <input type="text" id="eb_drop" required class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Pickup Date</label>
            <input type="date" id="eb_date" required class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Pickup Time</label>
            <input type="time" id="eb_time" required class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Cab Category</label>
            <select id="eb_cab" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
              <option value="Hatchback">Hatchback (WagonR / Tiago)</option>
              <option value="Sedan">Sedan (Dzire / Etios)</option>
              <option value="SUV">SUV (Ertiga / Carens)</option>
              <option value="Innova Crysta">Innova Crysta (Luxury 7-Seater)</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Total Fixed Fare (â‚¹)</label>
            <input type="number" id="eb_fare" required min="100" step="50" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
        </div>

        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1">Special Notes / Flight Number</label>
          <textarea id="eb_notes" rows="2" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400"></textarea>
        </div>

        <div id="eb-msg" class="text-xs font-bold text-center"></div>

        <button type="submit" class="w-full gradient-btn-gold text-slate-950 font-black text-xs py-3 rounded-xl uppercase flex items-center justify-center gap-2 shadow-xl">
          <i data-lucide="save" class="w-4 h-4"></i> Save Booking Updates
        </button>
      </form>
    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 4: MANUAL PHONE BOOKING DISPATCH                    -->
  <!-- ========================================================= -->
  <div id="manual-booking-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md animate-fadeIn">
    <div class="uber-card w-full max-w-xl p-6 sm:p-8 rounded-3xl border border-slate-800 space-y-5 relative shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
      <button onclick="closeModal('manual-booking-modal')" class="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>

      <div class="space-y-1">
        <h3 class="text-xl font-black text-white font-outfit uppercase">Create Manual Phone Booking</h3>
        <p class="text-xs text-slate-400">Instantly register customer call bookings into the live dispatch stream.</p>
      </div>

      <form onsubmit="handleCreateManualBooking(event)" class="space-y-3.5">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Passenger Name *</label>
            <input type="text" id="mb_name" required placeholder="e.g. Vikram Sharma" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Customer Phone (WhatsApp) *</label>
            <div class="wa-phone-wrap" id="wa-mb">
              <div class="wa-cc-btn" onclick="toggleWaDropdown('mb')">
                <span class="flag">ðŸ‡®ðŸ‡³</span>
                <span class="code">+91</span>
                <span class="chevron">â–¼</span>
              </div>
              <input type="tel" id="mb_phone" required placeholder="phone number" maxlength="15" class="wa-phone-input">
              <input type="hidden" id="mb_cc" value="91">
              <div class="wa-country-dropdown" id="wa-dd-mb">
                <input type="text" class="wa-country-search" placeholder="Search country..." oninput="filterWaCountries('mb',this.value)">
                <div class="wa-country-list" id="wa-list-mb"></div>
              </div>
            </div>
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Pickup Location *</label>
            <input type="text" id="mb_pickup" required placeholder="e.g. Mopa Airport (GOX)" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Destination Drop *</label>
            <input type="text" id="mb_drop" required placeholder="e.g. Candolim Beach Resort" class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Pickup Date *</label>
            <input type="date" id="mb_date" required class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Pickup Time *</label>
            <input type="time" id="mb_time" required class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Trip Type</label>
            <select id="mb_trip_type" class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
              <option value="one_way">One-Way Drop</option>
              <option value="round_trip">Round Trip</option>
              <option value="hourly">Hourly Rental</option>
              <option value="tour">Sightseeing Tour</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Cab Type</label>
            <select id="mb_cab" class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
              <option value="Sedan">Sedan (Dzire / Etios)</option>
              <option value="SUV">SUV (Ertiga / Carens)</option>
              <option value="Innova Crysta">Innova Crysta</option>
              <option value="Hatchback">Hatchback</option>
            </select>
          </div>
          <div>
            <label class="block text-xs font-black text-slate-300 uppercase mb-1">Total Fixed Fare (â‚¹) *</label>
            <input type="number" id="mb_fare" required min="100" step="50" placeholder="1500" class="w-full px-3 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-black text-white focus:outline-none focus:border-amber-400 font-outfit">
          </div>
        </div>

        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1">Flight Number / Dispatch Notes</label>
          <textarea id="mb_notes" rows="2" placeholder="e.g. Flight 6E 123 â€¢ Call passenger on arrival" class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs text-white focus:outline-none focus:border-amber-400"></textarea>
        </div>

        <div id="mb-msg" class="text-xs font-bold text-center"></div>

        <button type="submit" class="w-full gradient-btn-gold text-slate-950 font-black text-xs py-3 rounded-xl uppercase flex items-center justify-center gap-2 shadow-xl">
          âš¡ Create & Dispatch Phone Booking
        </button>
      </form>
    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 5: FLEET DRIVER MANAGER                             -->
  <!-- ========================================================= -->
  <div id="fleet-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md animate-fadeIn">
    <div class="uber-card w-full max-w-2xl p-6 sm:p-8 rounded-3xl border border-slate-800 space-y-6 relative shadow-2xl max-h-[90vh] overflow-y-auto custom-scrollbar">
      <button onclick="closeModal('fleet-modal')" class="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>

      <div class="space-y-1">
        <h3 class="text-xl font-black text-white font-outfit uppercase">Fleet & Driver Partner Roster</h3>
        <p class="text-xs text-slate-400">Register new drivers and toggle live operational availability.</p>
      </div>

      <!-- Add New Driver Form -->
      <form onsubmit="handleAddDriver(event)" class="bg-slate-900/80 p-4 rounded-2xl border border-slate-800 space-y-3">
        <span class="text-xs font-black text-amber-300 uppercase block">+ Register New Driver Partner</span>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
          <input type="text" id="nd_name" required placeholder="Driver Full Name" class="px-3.5 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
          <div class="wa-phone-wrap" id="wa-nd">
            <div class="wa-cc-btn" onclick="toggleWaDropdown('nd')">
              <span class="flag">ðŸ‡®ðŸ‡³</span>
              <span class="code">+91</span>
              <span class="chevron">â–¼</span>
            </div>
            <input type="tel" id="nd_phone" required placeholder="phone number" maxlength="15" class="wa-phone-input">
            <input type="hidden" id="nd_cc" value="91">
            <div class="wa-country-dropdown" id="wa-dd-nd">
              <input type="text" class="wa-country-search" placeholder="Search country..." oninput="filterWaCountries('nd',this.value)">
              <div class="wa-country-list" id="wa-list-nd"></div>
            </div>
          </div>
          <input type="text" id="nd_plate" required placeholder="Plate (GA-03-T-1234)" class="px-3.5 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400 uppercase font-mono">
          <input type="text" id="nd_model" placeholder="Model (Innova / Dzire)" class="px-3.5 py-2 bg-slate-950 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400">
        </div>
        <div id="nd-msg" class="text-xs font-bold text-center"></div>
        <button type="submit" class="w-full gradient-btn-gold text-slate-950 font-black text-xs py-2.5 rounded-xl uppercase">
          + Save Driver to Fleet
        </button>
      </form>

      <!-- Active Driver Roster -->
      <div class="space-y-2">
        <span class="text-xs font-black text-slate-300 uppercase block">Registered Fleet (<?php echo count($drivers); ?>)</span>
        <div class="space-y-2 max-h-64 overflow-y-auto custom-scrollbar">
          <?php foreach ($drivers as $d): ?>
            <div class="bg-slate-900/60 p-3 rounded-xl border border-slate-800 flex flex-wrap justify-between items-center gap-2 text-xs">
              <div>
                <span class="font-black text-white"><?php echo htmlspecialchars($d['name']); ?></span>
                <span class="text-slate-400 ml-2">ðŸ“ž <?php echo htmlspecialchars($d['phone']); ?></span>
                <span class="text-amber-400 font-mono ml-2 font-bold"><?php echo htmlspecialchars($d['plate_number'] ?? 'GA-03-T-1234'); ?></span>
                <span class="text-slate-500 ml-1">(<?php echo htmlspecialchars($d['car_model'] ?? 'Sedan'); ?>)</span>
              </div>
              <div class="flex items-center gap-2">
                <button onclick="toggleDriverStatus(<?php echo $d['id']; ?>, '<?php echo ($d['status'] ?? '') === 'available' ? 'inactive' : 'available'; ?>')" class="px-2.5 py-1 rounded-lg text-[10px] font-black uppercase border <?php echo ($d['status'] ?? '') === 'available' ? 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40' : 'bg-slate-800 text-slate-400 border-slate-700'; ?>">
                  <?php echo strtoupper($d['status'] ?? 'available'); ?>
                </button>
                <button onclick="deleteDriver(<?php echo $d['id']; ?>)" class="text-red-400 hover:text-red-300 p-1" title="Delete Driver">
                  <i data-lucide="trash-2" class="w-3.5 h-3.5"></i>
                </button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 6: TRIP VOUCHER / SLIP PRINTER                      -->
  <!-- ========================================================= -->
  <div id="receipt-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md animate-fadeIn">
    <div class="bg-white text-slate-950 w-full max-w-sm p-6 rounded-3xl space-y-4 shadow-2xl relative">
      <button onclick="closeModal('receipt-modal')" class="absolute top-4 right-4 text-slate-400 hover:text-slate-800 p-1">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>

      <div class="text-center border-b pb-3 space-y-1">
        <h3 class="text-lg font-black tracking-tight font-outfit uppercase">PAVANCAB GOA</h3>
        <p class="text-[10px] text-slate-500 uppercase tracking-widest font-bold">Official Dispatch Voucher</p>
      </div>

      <div class="space-y-2 text-xs" id="receipt-content"></div>

      <div class="pt-3 border-t flex gap-2">
        <button onclick="printReceipt()" class="flex-1 bg-slate-950 text-white font-black text-xs py-2.5 rounded-xl uppercase flex items-center justify-center gap-1.5 shadow">
          <i data-lucide="printer" class="w-3.5 h-3.5"></i> Print Slip
        </button>
        <button onclick="closeModal('receipt-modal')" class="bg-slate-200 text-slate-700 font-bold text-xs px-4 py-2.5 rounded-xl">
          Close
        </button>
      </div>
    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 7: META WHATSAPP CLOUD API SETTINGS                 -->
  <!-- ========================================================= -->
  <div id="wa-config-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md animate-fadeIn">
    <div class="uber-card w-full max-w-lg p-6 sm:p-8 rounded-3xl border border-slate-800 space-y-5 relative shadow-2xl">
      <button onclick="closeModal('wa-config-modal')" class="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>

      <div class="space-y-1">
        <h3 class="text-xl font-black text-white font-outfit uppercase">Meta WhatsApp API Settings</h3>
        <p class="text-xs text-slate-400">Update Meta WhatsApp Cloud API credentials for automated live notifications.</p>
      </div>

      <form onsubmit="handleSaveWaConfig(event)" class="space-y-4">
        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1">Meta Phone Number ID</label>
          <input type="text" id="cfg_phone_id" required class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400 font-mono">
        </div>
        <div>
          <label class="block text-xs font-black text-slate-300 uppercase mb-1">Meta Access Token</label>
          <textarea id="cfg_token" rows="3" required class="w-full px-4 py-2 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400 font-mono"></textarea>
        </div>

        <div id="wa-cfg-msg" class="text-xs font-bold text-center"></div>

        <button type="submit" class="w-full gradient-btn-gold text-slate-950 font-black text-xs py-3 rounded-xl uppercase flex items-center justify-center gap-2 shadow-xl">
          <i data-lucide="save" class="w-4 h-4"></i> Save Credentials
        </button>
      </form>
    </div>
  </div>

  <!-- ========================================================= -->
  <!-- MODAL 8: FIREBASE FCM HTTP v1 PUSH & TEST CONSOLE          -->
  <!-- ========================================================= -->
  <div id="fcm-config-modal" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/85 backdrop-blur-md animate-fadeIn">
    <div class="uber-card w-full max-w-xl p-6 sm:p-8 rounded-3xl border border-slate-800 space-y-5 relative shadow-2xl max-h-[90vh] overflow-y-auto">
      <button onclick="closeModal('fcm-config-modal')" class="absolute top-4 right-4 text-slate-400 hover:text-white p-1 rounded-full">
        <i data-lucide="x" class="w-5 h-5"></i>
      </button>

      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-2xl bg-amber-400/20 text-amber-400 flex items-center justify-center font-bold text-xl">ðŸ””</div>
        <div>
          <h3 class="text-xl font-black text-white font-outfit uppercase">Firebase FCM (HTTP v1) Push</h3>
          <p class="text-xs text-slate-400">Manage modern Firebase HTTP v1 push notifications & trigger live diagnostic tests.</p>
        </div>
      </div>

      <!-- Live Status Badges -->
      <div class="grid grid-cols-2 sm:grid-cols-3 gap-2.5 bg-slate-900/80 p-3.5 rounded-2xl border border-slate-800">
        <div>
          <span class="block text-[10px] font-bold text-slate-400 uppercase">Engine Status</span>
          <span id="fcm-status-badge" class="text-xs font-black text-amber-400">Checking...</span>
        </div>
        <div>
          <span class="block text-[10px] font-bold text-slate-400 uppercase">Admin Devices</span>
          <span id="fcm-admin-count" class="text-xs font-black text-white">0</span>
        </div>
        <div class="col-span-2 sm:col-span-1">
          <span class="block text-[10px] font-bold text-slate-400 uppercase">Total Tokens</span>
          <span id="fcm-total-count" class="text-xs font-black text-emerald-400">0</span>
        </div>
      </div>

      <!-- Live Test Push Section -->
      <div class="bg-gradient-to-br from-amber-500/10 via-slate-900/60 to-slate-900/90 p-4 rounded-2xl border border-amber-500/30 space-y-3">
        <div class="flex items-center justify-between">
          <span class="text-xs font-black text-amber-300 uppercase tracking-wide flex items-center gap-1.5">
            <i data-lucide="zap" class="w-3.5 h-3.5 text-amber-400"></i> Instant Live Test Push
          </span>
          <span class="text-[10px] text-slate-400">Rings browser chime</span>
        </div>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
          <button type="button" onclick="handleSendFcmTestPush('my_token')" class="bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs py-2.5 px-3 rounded-xl transition flex items-center justify-center gap-1.5 shadow">
            <i data-lucide="bell" class="w-3.5 h-3.5"></i> Test My Browser
          </button>
          <button type="button" onclick="handleSendFcmTestPush('admins')" class="bg-slate-800 hover:bg-slate-700 border border-slate-600 text-white font-black text-xs py-2.5 px-3 rounded-xl transition flex items-center justify-center gap-1.5 shadow">
            <i data-lucide="send" class="w-3.5 h-3.5 text-amber-400"></i> Test All Admins
          </button>
        </div>

        <div id="fcm-test-result" class="hidden text-xs p-2.5 rounded-xl border font-mono"></div>
      </div>

      <!-- Service Account Form -->
      <form onsubmit="handleSaveFcmConfig(event)" class="space-y-3">
        <div>
          <div class="flex items-center justify-between mb-1">
            <label class="block text-xs font-black text-slate-300 uppercase">Firebase Service Account JSON</label>
            <span class="text-[10px] text-amber-400">Firebase Console > Project Settings > Service accounts</span>
          </div>
          <textarea id="cfg_fcm_sa_json" rows="4" placeholder='Paste contents of your Firebase service account .json here (contains "project_id", "private_key", "client_email")' class="w-full px-4 py-2.5 bg-slate-900 border border-slate-700 rounded-xl text-xs font-bold text-white focus:outline-none focus:border-amber-400 font-mono"></textarea>
        </div>

        <div id="fcm-cfg-msg" class="text-xs font-bold text-center"></div>

        <div class="flex items-center gap-2">
          <button type="submit" class="flex-1 gradient-btn-gold text-slate-950 font-black text-xs py-3 rounded-xl uppercase flex items-center justify-center gap-2 shadow-xl">
            <i data-lucide="save" class="w-4 h-4"></i> Save Service Account
          </button>
          <button type="button" onclick="handleRemoveFcmConfig()" class="bg-red-500/10 hover:bg-red-500/20 border border-red-500/30 text-red-400 font-bold text-xs px-3 py-3 rounded-xl transition flex items-center justify-center" title="Remove custom JSON key">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
          </button>
        </div>
      </form>
    </div>
  </div>

  <footer class="mt-12 py-6 border-t border-slate-900 text-center text-xs text-slate-500 font-medium">
    PAVANCAB Goa â€¢ Dispatch Command Tower v2.5 â€¢ All Rights Reserved
  </footer>

  <!-- ========================================================= -->
  <!-- JAVASCRIPT BUSINESS LOGIC & LIVE SYNC ENGINE              -->
  <!-- ========================================================= -->
  <script>
  let currentFilter = '<?php echo $activeTab; ?>';
  let lastKnownDataHash = '';
  let isModalActive = false;
  let globalBookingsCache = <?php echo json_encode($bookings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'; ?>;
  let globalDriversCache = <?php echo json_encode($drivers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '[]'; ?>;

  function findBookingById(idOrObj) {
    if (!idOrObj) return null;
    if (typeof idOrObj === 'object' && idOrObj !== null) return idOrObj;
    const idNum = parseInt(idOrObj);
    if (globalBookingsCache && Array.isArray(globalBookingsCache)) {
      return globalBookingsCache.find(item => parseInt(item.id) === idNum) || null;
    }
    return null;
  }

  function closeModal(id) {
    const el = document.getElementById(id);
    if (el) el.classList.add('hidden');
    isModalActive = false;
  }

  function cleanDigits(phone) {
    return String(phone || '').replace(/\D/g, '');
  }

  const deskTitles = {
    ALL: 'Master Overview â€¢ All Rides',
    PENDING: 'ðŸ”´ Needs Driver Desk â€¢ Immediate Dispatch',
    CONFIRMED: 'ðŸ”µ Assigned Cabs Desk â€¢ Dispatched Drivers',
    IN_TRANSIT: 'ðŸŸ¡ On Trip Desk â€¢ Live Active Radar',
    COMPLETED: 'ðŸŸ¢ Completed Rides Desk â€¢ Trips History',
    CANCELLED: 'ðŸš« Cancelled Bookings Desk â€¢ Audit Roster'
  };

  function filterBookings(statusKey) {
    if (!statusKey) statusKey = currentFilter || 'ALL';
    currentFilter = statusKey;

    const cards = document.querySelectorAll('.booking-card');
    const query = (document.getElementById('booking-search-input')?.value || '').toLowerCase().trim();
    let visibleCount = 0;

    cards.forEach(c => {
      const cardStatus = c.dataset.status;
      const cardSearch = c.dataset.search || '';
      
      const matchesFilter = (statusKey === 'ALL' || cardStatus === statusKey);
      const matchesSearch = (!query || cardSearch.includes(query));

      if (matchesFilter && matchesSearch) {
        c.classList.remove('hidden');
        visibleCount++;
      } else {
        c.classList.add('hidden');
      }
    });

    const emptyEl = document.getElementById('bookings-empty-state');
    if (emptyEl) {
      if (visibleCount === 0) {
        emptyEl.classList.remove('hidden');
        const titleEl = document.getElementById('bookings-empty-title');
        const textEl = document.getElementById('bookings-empty-text');
        if (statusKey === 'PENDING') {
          if (titleEl) titleEl.innerText = 'âœ¨ All Drivers Dispatched â€¢ Zero Rides Pending';
          if (textEl) textEl.innerText = 'Great news! There are currently 0 rides waiting for driver assignment. All current bookings have drivers assigned or are already completed.';
        } else if (statusKey === 'CONFIRMED') {
          if (titleEl) titleEl.innerText = 'No Assigned Rides Waiting to Start';
          if (textEl) textEl.innerText = 'No upcoming rides currently in confirmed state. Check Needs Driver to assign new incoming trips.';
        } else if (statusKey === 'IN_TRANSIT') {
          if (titleEl) titleEl.innerText = 'No Cabs Currently On Trip';
          if (textEl) textEl.innerText = 'All drivers are currently available or idle. When an assigned ride starts, it streams here on the live in-transit radar.';
        } else if (statusKey === 'COMPLETED') {
          if (titleEl) titleEl.innerText = 'No Completed Trips Recorded Yet';
          if (textEl) textEl.innerText = 'Trips will archive here with fixed fare earnings, passenger star reviews, and driver history once completed.';
        } else if (statusKey === 'CANCELLED') {
          if (titleEl) titleEl.innerText = 'Zero Cancelled Bookings';
          if (textEl) textEl.innerText = 'No rides have been cancelled or rejected.';
        } else {
          if (titleEl) titleEl.innerText = 'No Bookings in this Category';
          if (textEl) textEl.innerText = 'No rides matched your active filter or search.';
        }
      } else {
        emptyEl.classList.add('hidden');
      }
    }
  }

  function highlightDeskTabs(statusKey) {
    const navActive = {
      ALL: 'bg-amber-400 text-slate-950 shadow-md',
      PENDING: 'bg-red-500 text-white shadow-md',
      CONFIRMED: 'bg-indigo-600 text-white shadow-md',
      IN_TRANSIT: 'bg-yellow-500 text-slate-950 shadow-md',
      COMPLETED: 'bg-emerald-600 text-white shadow-md',
      CANCELLED: 'bg-slate-700 text-white shadow-md'
    };
    const navIdle = {
      ALL: 'text-slate-400 hover:text-white hover:bg-slate-900',
      PENDING: 'text-red-400 hover:text-white hover:bg-slate-900',
      CONFIRMED: 'text-indigo-400 hover:text-white hover:bg-slate-900',
      IN_TRANSIT: 'text-yellow-400 hover:text-white hover:bg-slate-900',
      COMPLETED: 'text-emerald-400 hover:text-white hover:bg-slate-900',
      CANCELLED: 'text-slate-400 hover:text-white hover:bg-slate-900'
    };
    const tileActive = {
      ALL: 'border-amber-400 bg-slate-900/90 shadow-amber-500/10 shadow-lg',
      PENDING: 'border-red-400 bg-red-950/20 shadow-red-500/10 shadow-lg',
      CONFIRMED: 'border-indigo-400 bg-indigo-950/20 shadow-indigo-500/10 shadow-lg',
      IN_TRANSIT: 'border-yellow-400 bg-yellow-950/20 shadow-yellow-500/10 shadow-lg',
      COMPLETED: 'border-emerald-400 bg-emerald-950/20 shadow-emerald-500/10 shadow-lg',
      CANCELLED: 'border-slate-500 bg-slate-900/90'
    };

    document.querySelectorAll('.desk-nav-tab').forEach(el => {
      const key = el.getAttribute('data-desk-filter');
      const base = 'desk-nav-tab px-3.5 py-2 rounded-xl font-black text-xs uppercase transition whitespace-nowrap flex items-center gap-1.5 ';
      el.className = base + (key === statusKey ? (navActive[key] || navActive.ALL) : (navIdle[key] || navIdle.ALL));
    });
    document.querySelectorAll('.desk-stat-tile').forEach(el => {
      const key = el.getAttribute('data-desk-filter');
      const base = 'desk-stat-tile uber-card p-4 rounded-2xl border text-left w-full transition block group ';
      el.className = base + (key === statusKey ? (tileActive[key] || tileActive.ALL) : 'border-slate-800') + (key === 'PENDING' ? ' relative overflow-hidden' : '');
    });
  }

  function switchRideDesk(e, statusKey) {
    if (e && e.preventDefault) e.preventDefault();
    const bookingKeys = ['ALL', 'PENDING', 'CONFIRMED', 'IN_TRANSIT', 'COMPLETED', 'CANCELLED'];
    if (!bookingKeys.includes(statusKey)) return false;

    if (!document.getElementById('bookings-feed-container')) {
      window.location.href = './index.php?filter=' + encodeURIComponent(statusKey);
      return false;
    }

    currentFilter = statusKey;

    if (Array.isArray(globalBookingsCache) && globalBookingsCache.length) {
      const container = document.getElementById('bookings-feed-container');
      container.innerHTML = globalBookingsCache.map(renderBookingHTML).join('');
    }
    filterBookings(statusKey);
    highlightDeskTabs(statusKey);

    const titleEl = document.getElementById('desk-page-title');
    if (titleEl && deskTitles[statusKey]) {
      titleEl.innerText = deskTitles[statusKey];
    }

    try {
      const url = new URL(window.location.href);
      url.searchParams.set('filter', statusKey);
      history.replaceState({ filter: statusKey }, '', url.pathname + '?' + url.searchParams.toString());
    } catch (err) {}
    if (typeof lucide !== 'undefined') lucide.createIcons();
    return false;
  }

  function searchBookings(val) {
    filterBookings(currentFilter);
  }

  // 1. Assign Driver
  function openAssignModal(idOrObj) {
    const b = findBookingById(idOrObj);
    if (!b) {
      alert('Unable to load booking details for dispatch.');
      return;
    }
    isModalActive = true;
    document.getElementById('assign_booking_id').value = b.id;
    document.getElementById('assign-modal-ref').innerText = `Booking Ref: #${b.booking_ref || b.id} (${b.cab_type || 'Cab'} â€¢ â‚¹${b.total_fare || 0})`;
    document.getElementById('assign_driver_name').value = b.driver_name || '';
    document.getElementById('assign_driver_phone').value = b.driver_phone || '';
    document.getElementById('assign_vehicle_number').value = b.vehicle_number || 'GA-03-T-1234';
    if (document.getElementById('assign_driver_id')) {
      document.getElementById('assign_driver_id').value = b.driver_id || '';
    }
    document.getElementById('assign-msg').innerHTML = '';
    const btn = document.getElementById('assign-btn');
    if (btn) {
      btn.disabled = false;
      btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Dispatch Driver via WhatsApp';
    }
    const warn = document.getElementById('assign-driver-warn');
    if (warn) warn.classList.add('hidden');
    document.getElementById('assign-modal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  function handleSelectSavedDriver(select) {
    const opt = select.options[select.selectedIndex];
    const warn = document.getElementById('assign-driver-warn');
    if (opt && opt.dataset.name) {
      document.getElementById('assign_driver_name').value = opt.dataset.name;
      setPhoneCountryCode('assign', opt.dataset.phone);
      document.getElementById('assign_vehicle_number').value = opt.dataset.plate || 'GA-03-T-1234';

      if (opt.dataset.status && opt.dataset.status.toLowerCase() === 'on_trip') {
        warn.innerText = 'âš ï¸ Note: This driver is currently marked ON TRIP. Assigning will schedule this ride for them.';
        warn.classList.remove('hidden');
      } else {
        warn.classList.add('hidden');
      }
    }
  }

  const WA_COUNTRIES = [
    {c:'IN',n:'India',d:'91',f:'ðŸ‡®ðŸ‡³'},{c:'AE',n:'UAE',d:'971',f:'ðŸ‡¦ðŸ‡ª'},{c:'US',n:'United States',d:'1',f:'ðŸ‡ºðŸ‡¸'},
    {c:'GB',n:'United Kingdom',d:'44',f:'ðŸ‡¬ðŸ‡§'},{c:'AU',n:'Australia',d:'61',f:'ðŸ‡¦ðŸ‡º'},{c:'NP',n:'Nepal',d:'977',f:'ðŸ‡³ðŸ‡µ'},
    {c:'BD',n:'Bangladesh',d:'880',f:'ðŸ‡§ðŸ‡©'},{c:'LK',n:'Sri Lanka',d:'94',f:'ðŸ‡±ðŸ‡°'},{c:'SA',n:'Saudi Arabia',d:'966',f:'ðŸ‡¸ðŸ‡¦'},
    {c:'QA',n:'Qatar',d:'974',f:'ðŸ‡¶ðŸ‡¦'},{c:'KW',n:'Kuwait',d:'965',f:'ðŸ‡°ðŸ‡¼'},{c:'OM',n:'Oman',d:'968',f:'ðŸ‡´ðŸ‡²'},
    {c:'BH',n:'Bahrain',d:'973',f:'ðŸ‡§ðŸ‡­'},{c:'DE',n:'Germany',d:'49',f:'ðŸ‡©ðŸ‡ª'},{c:'FR',n:'France',d:'33',f:'ðŸ‡«ðŸ‡·'},
    {c:'IT',n:'Italy',d:'39',f:'ðŸ‡®ðŸ‡¹'},{c:'ES',n:'Spain',d:'34',f:'ðŸ‡ªðŸ‡¸'},{c:'CA',n:'Canada',d:'1',f:'ðŸ‡¨ðŸ‡¦'},
    {c:'SG',n:'Singapore',d:'65',f:'ðŸ‡¸ðŸ‡¬'},{c:'MY',n:'Malaysia',d:'60',f:'ðŸ‡²ðŸ‡¾'},{c:'TH',n:'Thailand',d:'66',f:'ðŸ‡¹ðŸ‡­'},
    {c:'PH',n:'Philippines',d:'63',f:'ðŸ‡µðŸ‡­'},{c:'ID',n:'Indonesia',d:'62',f:'ðŸ‡®ðŸ‡©'},{c:'CN',n:'China',d:'86',f:'ðŸ‡¨ðŸ‡³'},
    {c:'JP',n:'Japan',d:'81',f:'ðŸ‡¯ðŸ‡µ'},{c:'KR',n:'South Korea',d:'82',f:'ðŸ‡°ðŸ‡·'},{c:'NZ',n:'New Zealand',d:'64',f:'ðŸ‡³ðŸ‡¿'},
    {c:'ZA',n:'South Africa',d:'27',f:'ðŸ‡¿ðŸ‡¦'},{c:'PT',n:'Portugal',d:'351',f:'ðŸ‡µðŸ‡¹'},{c:'RU',n:'Russia',d:'7',f:'ðŸ‡·ðŸ‡º'}
  ];

  function initWaPicker(prefix) {
    const list = document.getElementById('wa-list-' + prefix);
    if (!list) return;
    list.innerHTML = WA_COUNTRIES.map(c =>
      `<div class="wa-country-item" data-cc="${c.d}" data-flag="${c.f}" data-name="${c.n}" onclick="selectWaCountry('${prefix}','${c.d}','${c.f}','${c.n}')">
        <span class="cflag">${c.f}</span>
        <span class="cname">${c.n}</span>
        <span class="ccode">+${c.d}</span>
      </div>`
    ).join('');
  }

  function toggleWaDropdown(prefix) {
    const dd = document.getElementById('wa-dd-' + prefix);
    const isOpen = dd.classList.contains('open');
    document.querySelectorAll('.wa-country-dropdown').forEach(d => d.classList.remove('open'));
    if (!isOpen) {
      dd.classList.add('open');
      dd.querySelector('.wa-country-search').value = '';
      filterWaCountries(prefix, '');
      setTimeout(() => dd.querySelector('.wa-country-search').focus(), 50);
    }
  }

  function selectWaCountry(prefix, code, flag, name) {
    const wrap = document.getElementById('wa-' + prefix);
    wrap.querySelector('.flag').textContent = flag;
    wrap.querySelector('.code').textContent = '+' + code;
    wrap.querySelector('input[type="hidden"]').value = code;
    document.getElementById('wa-dd-' + prefix).classList.remove('open');
    wrap.querySelector('.wa-phone-input').focus();
  }

  function filterWaCountries(prefix, q) {
    const list = document.getElementById('wa-list-' + prefix);
    const items = list.querySelectorAll('.wa-country-item');
    const lower = q.toLowerCase();
    items.forEach(item => {
      const name = item.dataset.name.toLowerCase();
      const cc = item.dataset.cc;
      item.style.display = (name.includes(lower) || cc.includes(lower)) ? '' : 'none';
    });
  }

  document.addEventListener('click', e => {
    if (!e.target.closest('.wa-phone-wrap')) {
      document.querySelectorAll('.wa-country-dropdown').forEach(d => d.classList.remove('open'));
    }
  });

  function fullPhone(prefix, phoneId) {
    const cc = (document.getElementById(prefix + '_cc')?.value || '91').replace(/\D/g, '');
    const raw = (document.getElementById(phoneId)?.value || '').replace(/\D/g, '');
    if (!raw) return '';
    if (raw.startsWith(cc) && raw.length > cc.length) return '+' + raw;
    return '+' + cc + raw;
  }

  function setPhoneCountryCode(prefix, phoneVal) {
    const wrap = document.getElementById('wa-' + prefix);
    if (!wrap || !phoneVal) return;
    const digits = (phoneVal || '').replace(/\D/g, '');
    for (const c of WA_COUNTRIES) {
      if (digits.startsWith(c.d) && digits.length > c.d.length) {
        wrap.querySelector('.flag').textContent = c.f;
        wrap.querySelector('.code').textContent = '+' + c.d;
        wrap.querySelector('input[type="hidden"]').value = c.d;
        document.getElementById(prefix + '_phone').value = digits.substring(c.d.length);
        return;
      }
    }
    document.getElementById(prefix + '_phone').value = digits;
  }

  document.querySelectorAll('.wa-phone-wrap').forEach(w => {
    const prefix = w.id.replace('wa-', '');
    initWaPicker(prefix);
  });

  async function handleAssignDriver(e) {
    e.preventDefault();
    const bookingId = document.getElementById('assign_booking_id').value;
    const driverId = document.getElementById('assign_driver_id').value;
    const driverName = document.getElementById('assign_driver_name').value;
    const driverPhone = fullPhone('assign', 'assign_driver_phone');
    const vehicleNumber = document.getElementById('assign_vehicle_number').value;
    const msg = document.getElementById('assign-msg');
    const btn = document.getElementById('assign-btn');

    if (btn) {
      btn.innerHTML = '<span class="animate-spin inline-block mr-1">â³</span> Dispatching Driver...';
      btn.disabled = true;
    }

    try {
      const res = await fetch(`../api_dashboard.php?action=assign-driver`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          booking_id: bookingId,
          driver_id: driverId,
          driver_name: driverName,
          driver_phone: driverPhone,
          vehicle_number: vehicleNumber
        })
      });
      const data = await res.json();

      if (data.success) {
        msg.innerHTML = '<span class="text-emerald-400 font-bold">âœ“ Driver Assigned & Dispatched via WhatsApp!</span>';
        setTimeout(() => {
          const params = new URLSearchParams(window.location.search);
          const filter = params.get('filter') || currentFilter || 'ALL';
          window.location.replace('./index.php?filter=' + encodeURIComponent(filter) + '&_r=' + Date.now());
        }, 700);
      } else {
        msg.innerHTML = `<span class="text-red-400 font-bold">${data.error || 'Failed to assign driver'}</span>`;
        if (btn) {
          btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Dispatch Driver via WhatsApp';
          btn.disabled = false;
          if (typeof lucide !== 'undefined') lucide.createIcons();
        }
      }
    } catch (err) {
      msg.innerHTML = '<span class="text-red-400 font-bold">Network error dispatching driver</span>';
      if (btn) {
        btn.innerHTML = '<i data-lucide="send" class="w-4 h-4"></i> Dispatch Driver via WhatsApp';
        btn.disabled = false;
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }
    }
  }

  // 2. Adjust Fare
  function openEditFareModal(idOrObj) {
    const b = findBookingById(idOrObj);
    if (!b) return;
    isModalActive = true;
    document.getElementById('fare_modal_booking_id').value = b.id;
    document.getElementById('fare-modal-ref').innerText = `Booking #${b.booking_ref || b.id} (${b.customer_name || ''})`;
    document.getElementById('fare_modal_current_text').innerText = 'â‚¹' + parseFloat(b.total_fare || 0);
    document.getElementById('fare_modal_new_amount').value = parseFloat(b.total_fare || 0);
    document.getElementById('fare-edit-msg').innerHTML = '';
    document.getElementById('edit-fare-modal').classList.remove('hidden');
  }

  function quickAddBoost(amount) {
    const current = parseFloat(document.getElementById('fare_modal_new_amount').value || 0);
    document.getElementById('fare_modal_new_amount').value = current + amount;
    document.getElementById('fare_modal_reason').value = `+â‚¹${amount} Surge / Peak Boost added by Dispatch`;
  }

  async function handleSaveFareAdjustment(e) {
    e.preventDefault();
    const bookingId = document.getElementById('fare_modal_booking_id').value;
    const newFare = parseFloat(document.getElementById('fare_modal_new_amount').value || 0);
    const reason = document.getElementById('fare_modal_reason').value;
    const msg = document.getElementById('fare-edit-msg');

    try {
      const res = await fetch(`../api_dashboard.php?action=edit-fare`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ booking_id: bookingId, new_fare: newFare, reason: reason })
      });
      const data = await res.json();
      if (data.success) {
        msg.innerHTML = '<span class="text-emerald-400 font-bold">âœ“ Fare updated & notifications dispatched!</span>';
        setTimeout(() => {
          const params = new URLSearchParams(window.location.search);
          const filter = params.get('filter') || currentFilter || 'ALL';
          window.location.replace('./index.php?filter=' + encodeURIComponent(filter) + '&_r=' + Date.now());
        }, 700);
      } else {
        msg.innerHTML = `<span class="text-red-400 font-bold">${data.error || 'Failed to update fare'}</span>`;
      }
    } catch (err) {
      msg.innerHTML = '<span class="text-red-400 font-bold">Network error</span>';
    }
  }

  // 3. Edit Booking
  function openEditBookingModal(idOrObj) {
    const b = findBookingById(idOrObj);
    if (!b) return;
    isModalActive = true;
    document.getElementById('eb_id').value = b.id;
    document.getElementById('eb-modal-ref').innerText = `Ref: #${b.booking_ref || b.id}`;
    document.getElementById('eb_name').value = b.customer_name || '';
    setPhoneCountryCode('eb', b.customer_phone || '');
    document.getElementById('eb_pickup').value = b.pickup_location || '';
    document.getElementById('eb_drop').value = b.drop_location || '';
    document.getElementById('eb_date').value = b.pickup_date || '';
    document.getElementById('eb_time').value = b.pickup_time ? b.pickup_time.substring(0, 5) : '';
    document.getElementById('eb_cab').value = b.cab_type || 'Sedan';
    document.getElementById('eb_fare').value = parseFloat(b.total_fare || 0);
    document.getElementById('eb_notes').value = b.special_notes || '';
    document.getElementById('eb-msg').innerHTML = '';
    document.getElementById('edit-booking-modal').classList.remove('hidden');
  }

  async function handleSaveEditBooking(e) {
    e.preventDefault();
    const id = document.getElementById('eb_id').value;
    const msg = document.getElementById('eb-msg');

    try {
      const res = await fetch(`../api_dashboard.php?action=edit-booking`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          booking_id: id,
          customer_name: document.getElementById('eb_name').value,
          customer_phone: fullPhone('eb', 'eb_phone'),
          pickup_location: document.getElementById('eb_pickup').value,
          drop_location: document.getElementById('eb_drop').value,
          pickup_date: document.getElementById('eb_date').value,
          pickup_time: document.getElementById('eb_time').value,
          cab_type: document.getElementById('eb_cab').value,
          total_fare: parseFloat(document.getElementById('eb_fare').value || 0),
          special_notes: document.getElementById('eb_notes').value
        })
      });
      const data = await res.json();
      if (data.success) {
        msg.innerHTML = '<span class="text-emerald-400 font-bold">âœ“ Booking updated successfully!</span>';
        setTimeout(() => {
          const params = new URLSearchParams(window.location.search);
          const filter = params.get('filter') || currentFilter || 'ALL';
          window.location.replace('./index.php?filter=' + encodeURIComponent(filter) + '&_r=' + Date.now());
        }, 700);
      } else {
        msg.innerHTML = `<span class="text-red-400 font-bold">${data.error || 'Failed to save'}</span>`;
      }
    } catch (err) {
      msg.innerHTML = '<span class="text-red-400 font-bold">Network error</span>';
    }
  }

  // 4. Manual Phone Booking
  function openManualBookingModal() {
    isModalActive = true;
    document.getElementById('mb-msg').innerHTML = '';
    const now = new Date();
    const today = now.toISOString().split('T')[0];
    const time = now.toTimeString().substring(0, 5);
    const dateInput = document.getElementById('mb_date');
    const timeInput = document.getElementById('mb_time');
    if (dateInput && !dateInput.value) dateInput.value = today;
    if (timeInput && !timeInput.value) timeInput.value = time;
    document.getElementById('manual-booking-modal').classList.remove('hidden');
  }

  async function handleCreateManualBooking(e) {
    e.preventDefault();
    const msg = document.getElementById('mb-msg');
    const btn = e.target.querySelector('button[type="submit"]');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="animate-spin inline-block mr-1">â³</span> Creating Booking...';
    }
    try {
      const res = await fetch(`../api_dashboard.php?action=create-booking`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          customer_name: document.getElementById('mb_name').value,
          customer_phone: fullPhone('mb', 'mb_phone'),
          pickup_location: document.getElementById('mb_pickup').value,
          drop_location: document.getElementById('mb_drop').value,
          pickup_date: document.getElementById('mb_date').value,
          pickup_time: document.getElementById('mb_time').value,
          cab_type: document.getElementById('mb_cab').value,
          total_fare: parseFloat(document.getElementById('mb_fare').value || 0),
          trip_type: document.getElementById('mb_trip_type').value,
          special_notes: document.getElementById('mb_notes').value
        })
      });
      const data = await res.json();
      if (data.success) {
        msg.innerHTML = `<span class="text-emerald-400 font-bold">âœ“ ${data.message}</span>`;
        setTimeout(() => {
          const params = new URLSearchParams(window.location.search);
          const filter = params.get('filter') || currentFilter || 'ALL';
          window.location.replace('./index.php?filter=' + encodeURIComponent(filter) + '&_r=' + Date.now());
        }, 800);
      } else {
        msg.innerHTML = `<span class="text-red-400 font-bold">${data.error || 'Failed to create booking'}</span>`;
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = 'âš¡ Create & Dispatch Phone Booking';
        }
      }
    } catch (err) {
      msg.innerHTML = '<span class="text-red-400 font-bold">Network error</span>';
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = 'âš¡ Create & Dispatch Phone Booking';
      }
    }
  }

  // 5. Fleet Manager
  function openFleetModal() {
    isModalActive = true;
    const msg = document.getElementById('nd-msg');
    if (msg) msg.innerHTML = '';
    document.getElementById('fleet-modal').classList.remove('hidden');
  }

  async function handleAddDriver(e) {
    e.preventDefault();
    const msg = document.getElementById('nd-msg');
    const btn = e.target.querySelector('button[type="submit"]');
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="animate-spin inline-block mr-1">â³</span> Saving...';
    }
    try {
      const res = await fetch(`../api_dashboard.php?action=add-driver`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          name: document.getElementById('nd_name').value,
          phone: fullPhone('nd', 'nd_phone'),
          plate_number: document.getElementById('nd_plate').value,
          car_model: document.getElementById('nd_model').value
        })
      });
      const data = await res.json();
      if (data.success) {
        msg.innerHTML = '<span class="text-emerald-400 font-bold">âœ“ Driver registered to fleet!</span>';
        // #region agent log
        fetch('http://127.0.0.1:7677/ingest/a4c1ae9b-9c91-43a8-8e20-83b11c083cfd',{method:'POST',headers:{'Content-Type':'application/json','X-Debug-Session-Id':'43d22e'},body:JSON.stringify({sessionId:'43d22e',hypothesisId:'H2',location:'_layout_footer.php:handleAddDriver',message:'fleet add without reload',data:{ok:true},timestamp:Date.now()})}).catch(()=>{});
        // #endregion
        e.target.reset();
        setTimeout(async () => {
          closeModal('fleet-modal');
          await refreshDriversLive();
          if (btn) {
            btn.disabled = false;
            btn.innerHTML = '+ Save Driver to Fleet';
          }
        }, 500);
      } else {
        msg.innerHTML = `<span class="text-red-400 font-bold">${data.error || 'Failed to add driver'}</span>`;
        if (btn) {
          btn.disabled = false;
          btn.innerHTML = '+ Save Driver to Fleet';
        }
      }
    } catch (err) {
      msg.innerHTML = '<span class="text-red-400 font-bold">Network error</span>';
      if (btn) {
        btn.disabled = false;
        btn.innerHTML = '+ Save Driver to Fleet';
      }
    }
  }

  function renderDriverCardHTML(d) {
    const st = String(d.status || 'available').toLowerCase();
    const isAvailable = (st === 'available');
    const isOnTrip = (st === 'on_trip');
    const phone = d.phone || '';
    const clean = cleanDigits(phone);
    let statusClass = 'bg-slate-800 text-slate-400 border-slate-700';
    if (isAvailable) statusClass = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40';
    else if (isOnTrip) statusClass = 'bg-yellow-500/20 text-yellow-300 border-yellow-500/40 animate-pulse';
    return `
      <div class="uber-card p-5 rounded-3xl border border-slate-800 space-y-4 shadow-xl hover:border-amber-400/40 transition" data-driver-id="${d.id}">
        <div class="flex items-start justify-between gap-2">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/10 border border-amber-500/30 flex items-center justify-center text-amber-400 text-xl font-black">ðŸ‘¨â€âœˆï¸</div>
            <div>
              <h3 class="font-extrabold text-white text-base leading-snug">${d.name || ''}</h3>
              <span class="text-xs text-amber-300 font-mono font-bold">ðŸš– ${d.plate_number || 'GA-03-T-1234'}</span>
              <span class="text-slate-400 text-[11px] block">${d.car_model || 'Sedan / Hatchback'}</span>
            </div>
          </div>
          <button onclick="toggleDriverStatus(${d.id}, '${isAvailable ? 'inactive' : 'available'}')" class="px-2.5 py-1 rounded-xl text-[10px] font-black uppercase border transition ${statusClass}">
            ${String(d.status || 'available').toUpperCase()}
          </button>
        </div>
        <div class="bg-slate-950/70 p-3 rounded-2xl border border-slate-800/80 text-xs space-y-1.5">
          <div class="flex justify-between items-center">
            <span class="text-slate-400 font-bold">Direct Phone:</span>
            <a href="tel:${phone}" class="text-amber-400 font-bold hover:underline">ðŸ“ž ${phone}</a>
          </div>
          <div class="flex justify-between items-center">
            <span class="text-slate-400 font-bold">WhatsApp:</span>
            <a href="https://wa.me/${clean}" target="_blank" class="text-emerald-400 font-bold hover:underline flex items-center gap-1">
              <i data-lucide="message-circle" class="w-3.5 h-3.5"></i> Open Chat
            </a>
          </div>
        </div>
        <div class="flex items-center justify-between gap-2 pt-2 border-t border-slate-800/80">
          <a href="tel:${phone}" class="flex-1 bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold py-2 rounded-xl text-center flex items-center justify-center gap-1">
            <i data-lucide="phone" class="w-3.5 h-3.5 text-amber-400"></i> Call
          </a>
          <a href="https://wa.me/${clean}" target="_blank" class="flex-1 bg-emerald-600 hover:bg-emerald-500 text-slate-950 text-xs font-black py-2 rounded-xl text-center flex items-center justify-center gap-1 shadow">
            <i data-lucide="send" class="w-3.5 h-3.5"></i> WhatsApp
          </a>
          <button onclick="deleteDriver(${d.id})" class="p-2 text-slate-500 hover:text-red-400 rounded-xl transition" title="Delete Driver">
            <i data-lucide="trash-2" class="w-4 h-4"></i>
          </button>
        </div>
      </div>`;
  }

  function refreshAssignDriverSelect() {
    const sel = document.getElementById('assign_driver_id');
    if (!sel || !Array.isArray(globalDriversCache)) return;
    const current = sel.value;
    sel.innerHTML = '<option value="">-- Select Saved Driver --</option>' + globalDriversCache.map(d =>
      `<option value="${d.id}" data-name="${(d.name || '').replace(/"/g, '&quot;')}" data-phone="${d.phone || ''}" data-plate="${d.plate_number || 'GA-03-T-1234'}" data-status="${d.status || 'available'}">${d.name || ''} (${d.phone || ''})</option>`
    ).join('');
    if (current) sel.value = current;
  }

  async function refreshDriversLive() {
    try {
      const res = await fetch('../api_dashboard.php?action=drivers&_t=' + Date.now());
      const drivers = await res.json();
      if (!Array.isArray(drivers)) return;
      globalDriversCache = drivers;
      refreshAssignDriverSelect();
      const countEl = document.getElementById('count-FLEET');
      if (countEl) countEl.innerText = drivers.length;
      const grid = document.getElementById('fleet-grid-container');
      if (grid) {
        if (drivers.length === 0) {
          grid.innerHTML = `<div class="col-span-full uber-card p-12 rounded-3xl text-center text-slate-400 border border-slate-800 space-y-3">
            <p class="text-base font-bold text-white">No fleet drivers registered yet.</p>
            <p class="text-xs text-slate-500">Click '+ Register New Fleet Driver' above to add your first driver partner.</p>
          </div>`;
        } else {
          grid.innerHTML = drivers.map(renderDriverCardHTML).join('');
        }
        if (typeof lucide !== 'undefined') lucide.createIcons();
      }
    } catch (e) {}
  }

  async function toggleDriverStatus(driverId, newStatus) {
    try {
      const res = await fetch(`../api_dashboard.php?action=toggle-driver-status`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ driver_id: driverId, status: newStatus })
      });
      const data = await res.json();
      if (data.success) {
        await refreshDriversLive();
      } else {
        alert(data.error || 'Failed to update status');
      }
    } catch (e) {
      alert('Network error');
    }
  }

  async function deleteDriver(driverId) {
    if (!confirm('Are you sure you want to delete this driver from the fleet?')) return;
    try {
      const res = await fetch(`../api_dashboard.php?action=delete-driver`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ driver_id: driverId })
      });
      const data = await res.json();
      if (data.success) await refreshDriversLive();
      else alert(data.error || 'Failed to delete driver');
    } catch (e) {
      alert('Network error');
    }
  }

  // 6. WhatsApp Cloud API Settings
  function openWaConfigModal() {
    isModalActive = true;
    document.getElementById('wa-cfg-msg').innerHTML = '';
    fetch(`../api_dashboard.php?action=whatsapp-config`)
      .then(r => r.json())
      .then(data => {
        if (data.success && data.config) {
          document.getElementById('cfg_phone_id').value = data.config.phone_number_id || '';
          document.getElementById('cfg_token').value = data.config.access_token || '';
        }
      })
      .catch(() => {});
    document.getElementById('wa-config-modal').classList.remove('hidden');
  }

  async function handleSaveWaConfig(e) {
    e.preventDefault();
    const phoneId = document.getElementById('cfg_phone_id').value.trim();
    const token = document.getElementById('cfg_token').value.trim();
    const msg = document.getElementById('wa-cfg-msg');

    try {
      const res = await fetch(`../api_dashboard.php?action=whatsapp-config`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ phone_number_id: phoneId, access_token: token })
      });
      const data = await res.json();
      if (data.success) {
        msg.innerHTML = '<span class="text-emerald-400 font-bold">âœ“ WhatsApp credentials saved!</span>';
        setTimeout(() => closeModal('wa-config-modal'), 800);
      } else {
        msg.innerHTML = `<span class="text-red-400 font-bold">${data.error || 'Failed to save credentials'}</span>`;
      }
    } catch (err) {
      msg.innerHTML = '<span class="text-red-400 font-bold">Network error</span>';
    }
  }

  // 6b. Firebase Cloud Messaging (HTTP v1) Settings & Live Test
  async function openFcmConfigModal() {
    isModalActive = true;
    document.getElementById('fcm-cfg-msg').innerHTML = '';
    const resBox = document.getElementById('fcm-test-result');
    if (resBox) {
      resBox.className = 'hidden';
      resBox.innerHTML = '';
    }

    try {
      const res = await fetch(`../api_dashboard.php?action=fcm-config`);
      const data = await res.json();
      
      const badge = document.getElementById('fcm-status-badge');
      const adminCount = document.getElementById('fcm-admin-count');
      const totalCount = document.getElementById('fcm-total-count');

      if (badge) {
        if (data.configured) {
          badge.innerHTML = `<span class="text-emerald-400">âœ“ Active (${data.source})</span>`;
        } else {
          badge.innerHTML = `<span class="text-amber-400">âš ï¸ Needs Key</span>`;
        }
      }
      if (adminCount) adminCount.textContent = data.admin_tokens_count || '0';
      if (totalCount) totalCount.textContent = data.total_tokens || '0';

    } catch (e) {
      console.warn('FCM Config fetch error:', e);
    }

    document.getElementById('fcm-config-modal').classList.remove('hidden');
    if (typeof lucide !== 'undefined') lucide.createIcons();
  }

  async function handleSaveFcmConfig(e) {
    e.preventDefault();
    const jsonVal = document.getElementById('cfg_fcm_sa_json').value.trim();
    const msg = document.getElementById('fcm-cfg-msg');

    if (!jsonVal) {
      msg.innerHTML = '<span class="text-amber-400 font-bold">Please paste your Firebase Service Account JSON content</span>';
      return;
    }

    try {
      JSON.parse(jsonVal);
    } catch(err) {
      msg.innerHTML = '<span class="text-red-400 font-bold">Invalid JSON format. Please paste valid JSON.</span>';
      return;
    }

    msg.innerHTML = '<span class="text-slate-400">Validating & saving service account key...</span>';

    try {
      const res = await fetch(`../api_dashboard.php?action=fcm-config`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ service_account_json: jsonVal })
      });
      const data = await res.json();
      if (data.success) {
        msg.innerHTML = '<span class="text-emerald-400 font-bold">âœ“ Firebase Service Account saved & active!</span>';
        document.getElementById('cfg_fcm_sa_json').value = '';
        setTimeout(() => openFcmConfigModal(), 800);
      } else {
        msg.innerHTML = `<span class="text-red-400 font-bold">${data.error || 'Failed to save JSON'}</span>`;
      }
    } catch (err) {
      msg.innerHTML = '<span class="text-red-400 font-bold">Network connection error</span>';
    }
  }

  async function handleRemoveFcmConfig() {
    if (!confirm('Remove saved Firebase Service Account key from database?')) return;
    const msg = document.getElementById('fcm-cfg-msg');
    msg.innerHTML = '<span class="text-slate-400">Removing key...</span>';

    try {
      const res = await fetch(`../api_dashboard.php?action=fcm-config`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ service_account_json: 'CLEAR' })
      });
      const data = await res.json();
      if (data.success) {
        msg.innerHTML = '<span class="text-amber-400 font-bold">âœ“ Custom Service Account key removed</span>';
        setTimeout(() => openFcmConfigModal(), 800);
      }
    } catch (err) {
      msg.innerHTML = '<span class="text-red-400 font-bold">Network error</span>';
    }
  }

  async function handleSendFcmTestPush(target) {
    const resBox = document.getElementById('fcm-test-result');
    if (!resBox) return;

    resBox.className = 'text-xs p-2.5 rounded-xl border font-mono bg-slate-900 border-amber-500/40 text-amber-300 block';
    resBox.innerHTML = '<span class="animate-pulse">â³ Dispatching live FCM HTTP v1 push notification...</span>';

    // Play local chime immediately for instant feedback
    try {
      const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
      audio.play().catch(()=>{});
    } catch(e) {}

    const myToken = window.fcmTokenStored || '';

    try {
      const res = await fetch(`../api_dashboard.php?action=fcm-test`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          target: target,
          token: (target === 'my_token' ? myToken : ''),
          title: 'ðŸš• PAVANCAB Live Dispatch Alert',
          body: `Test notification sent successfully at ${new Date().toLocaleTimeString()}!`
        })
      });
      const data = await res.json();

      if (data.success) {
        resBox.className = 'text-xs p-2.5 rounded-xl border font-mono bg-emerald-950/60 border-emerald-500 text-emerald-300 block';
        resBox.innerHTML = `âœ“ <strong>Push Delivered!</strong> Sent to ${data.details.sent} device(s). Check your browser/OS notification tray!`;
        if (typeof showBrowserNotification === 'function') {
          showBrowserNotification('ðŸš• PAVANCAB Live Test', 'FCM Push sent and received successfully!');
        }
      } else {
        resBox.className = 'text-xs p-2.5 rounded-xl border font-mono bg-red-950/60 border-red-500 text-red-300 block';
        resBox.innerHTML = `âœ• <strong>Push Failed:</strong> ${data.error || 'Check server configuration'}`;
      }
    } catch (err) {
      resBox.className = 'text-xs p-2.5 rounded-xl border font-mono bg-red-950/60 border-red-500 text-red-300 block';
      resBox.innerHTML = `âœ• <strong>Network Error:</strong> Failed to reach server`;
    }
  }

  // 7. Printable Trip Voucher
  function openReceiptModal(idOrObj) {
    const b = findBookingById(idOrObj);
    if (!b) return;
    isModalActive = true;

    const content = document.getElementById('receipt-content');
    content.innerHTML = `
      <div class="space-y-1">
        <div class="flex justify-between font-bold">
          <span>Booking Ref:</span>
          <span class="font-mono">#${b.booking_ref || b.id}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Date:</span>
          <span>${b.pickup_date} at ${(b.pickup_time || '').substring(0, 5)}</span>
        </div>
        <div class="flex justify-between">
          <span class="text-slate-500">Customer:</span>
          <span class="font-bold">${b.customer_name} (${b.customer_phone})</span>
        </div>
      </div>

      <div class="bg-slate-100 p-2.5 rounded-xl space-y-1 my-2">
        <div><strong>From:</strong> ${b.pickup_location}</div>
        <div><strong>To:</strong> ${b.drop_location}</div>
        <div><strong>Cab:</strong> ${b.cab_type} (${b.trip_type || 'One-Way'})</div>
      </div>

      ${b.driver_name ? `
        <div class="bg-slate-100 p-2.5 rounded-xl space-y-1">
          <div><strong>Driver:</strong> ${b.driver_name}</div>
          <div><strong>Phone:</strong> ${b.driver_phone || 'N/A'}</div>
          <div><strong>Plate:</strong> ${b.vehicle_number || 'GA-03-T-1234'}</div>
        </div>
      ` : ''}

      <div class="flex justify-between items-center text-sm font-black pt-2 border-t">
        <span>TOTAL FARE:</span>
        <span class="text-base">â‚¹${parseFloat(b.total_fare || 0)}</span>
      </div>
      <div class="text-[10px] text-center text-slate-400 italic pt-1">
        Thank you for traveling with PAVANCAB Goa!
      </div>
    `;

    document.getElementById('receipt-modal').classList.remove('hidden');
  }

  function printReceipt() {
    window.print();
  }

  // 8. Lifecycle & Real-Time Sync
  async function updateBookingStatus(bookingId, newStatus) {
    if (!newStatus) return;
    const card = document.getElementById(`booking-card-${bookingId}`);
    if (card) card.style.opacity = '0.4';

    try {
      const res = await fetch(`../api_dashboard.php?action=update-status&_t=${Date.now()}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ booking_id: bookingId, status: newStatus })
      });
      const data = await res.json();
      if (data.success) {
        const params = new URLSearchParams(window.location.search);
        const filter = params.get('filter') || currentFilter || 'ALL';
        window.location.replace('./index.php?filter=' + encodeURIComponent(filter) + '&_r=' + Date.now());
        return;
      } else {
        if (card) card.style.opacity = '1';
        alert(data.error || 'Failed to update status');
      }
    } catch (e) {
      if (card) card.style.opacity = '1';
      alert('Network error updating status');
    }
  }

  function handleCancelBookingPrompt(bookingId) {
    if (!confirm('Are you sure you want to CANCEL this ride?')) return;
    var f = document.createElement('form');
    f.method = 'POST';
    f.action = 'cancel_ride.php';
    var i1 = document.createElement('input'); i1.type = 'hidden'; i1.name = 'booking_id'; i1.value = bookingId; f.appendChild(i1);
    var i2 = document.createElement('input'); i2.type = 'hidden'; i2.name = 'filter'; i2.value = currentFilter || 'ALL'; f.appendChild(i2);
    document.body.appendChild(f);
    f.submit();
  }

  function handleStatusChange(bookingId, newStatus, currentFilter) {
    if (!newStatus) return;
    if (newStatus === 'CANCELLED_BY_ADMIN') {
      if (!confirm('Are you sure you want to CANCEL this ride?')) {
        var sel = document.getElementById('status-select-' + bookingId);
        if (sel) sel.value = '';
        return;
      }
      var f = document.createElement('form');
      f.method = 'POST';
      f.action = 'cancel_ride.php';
      var i1 = document.createElement('input'); i1.type = 'hidden'; i1.name = 'booking_id'; i1.value = bookingId; f.appendChild(i1);
      var i2 = document.createElement('input'); i2.type = 'hidden'; i2.name = 'filter'; i2.value = currentFilter || 'ALL'; f.appendChild(i2);
      document.body.appendChild(f);
      f.submit();
      return;
    }
    updateBookingStatus(bookingId, newStatus);
  }

  function exportBookingsToCSV() {
    if (!globalBookingsCache || !globalBookingsCache.length) {
      alert('No bookings available to export');
      return;
    }

    const headers = [
      'Booking ID', 'Reference', 'Customer Name', 'Phone', 'Pickup', 'Destination',
      'Date', 'Time', 'Cab Type', 'Trip Type', 'Fare', 'Status',
      'Driver Name', 'Driver Phone', 'Vehicle Plate', 'Notes', 'Created At'
    ];

    let csv = headers.join(',') + '\n';
    globalBookingsCache.forEach(b => {
      const row = [
        b.id,
        `"${b.booking_ref || ''}"`,
        `"${(b.customer_name || '').replace(/"/g, '""')}"`,
        `"${b.customer_phone || ''}"`,
        `"${(b.pickup_location || '').replace(/"/g, '""')}"`,
        `"${(b.drop_location || '').replace(/"/g, '""')}"`,
        b.pickup_date,
        b.pickup_time,
        b.cab_type,
        b.trip_type,
        b.total_fare,
        b.status,
        `"${(b.driver_name || '').replace(/"/g, '""')}"`,
        `"${b.driver_phone || ''}"`,
        `"${b.vehicle_number || ''}"`,
        `"${(b.special_notes || '').replace(/"/g, '""')}"`,
        b.created_at
      ];
      csv += row.join(',') + '\n';
    });

    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.setAttribute('href', url);
    link.setAttribute('download', `PAVANCAB_Bookings_${new Date().toISOString().split('T')[0]}.csv`);
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
  }

  function classifyRideStatusJS(status) {
    const s = String(status || '').toUpperCase().trim();
    if (s.includes('CANCEL') || s === 'REJECTED') return 'CANCELLED';
    if (s === 'COMPLETED' || s === 'FINISHED') return 'COMPLETED';
    if (s === 'IN_TRANSIT' || s === 'ON_TRIP' || s === 'ARRIVED') return 'IN_TRANSIT';
    if (s === 'CONFIRMED' || s === 'ASSIGNED' || s === 'ACCEPTED' || s === 'DRIVER_ASSIGNED') return 'CONFIRMED';
    return 'PENDING';
  }

  function renderBookingHTML(b) {
    const statusUpper = String(b.status || 'PENDING').toUpperCase().trim();
    const unifiedKey = classifyRideStatusJS(b.status);
    const isCancelled = (unifiedKey === 'CANCELLED');
    const isDone = (unifiedKey === 'COMPLETED');
    const isInTransit = (unifiedKey === 'IN_TRANSIT');
    const isAssigned = (unifiedKey === 'CONFIRMED');
    const isPending = (unifiedKey === 'PENDING');

    let cardBorder = 'border-slate-800';
    let statusBadgeClass = 'bg-slate-800 text-slate-300 border-slate-700';

    if (isPending) {
      cardBorder = 'border-red-500/40 bg-red-950/10';
      statusBadgeClass = 'bg-red-500/20 text-red-300 border-red-500/40 animate-pulse';
    } else if (isAssigned) {
      cardBorder = 'border-indigo-500/40 bg-indigo-950/10';
      statusBadgeClass = 'bg-indigo-500/20 text-indigo-300 border-indigo-500/40';
    } else if (isInTransit) {
      cardBorder = 'border-yellow-500/40 bg-yellow-950/10';
      statusBadgeClass = 'bg-yellow-500/20 text-yellow-300 border-yellow-500/40 animate-pulse';
    } else if (isDone) {
      cardBorder = 'border-emerald-500/30 bg-emerald-950/10';
      statusBadgeClass = 'bg-emerald-500/20 text-emerald-300 border-emerald-500/40';
    } else if (isCancelled) {
      cardBorder = 'border-slate-800 opacity-60';
      statusBadgeClass = 'bg-red-500/10 text-red-400 border-red-500/20';
    }

    const hasBoost = b.special_notes && b.special_notes.includes('[PEAK BOOST]');
    const searchString = `${b.booking_ref} ${b.customer_name} ${b.customer_phone} ${b.pickup_location} ${b.drop_location} ${b.cab_type} ${b.driver_name || ''} ${b.driver_phone || ''}`.toLowerCase();

    let driverCard = '';
    if (b.driver_name || b.driver_phone) {
      driverCard = `
        <div class="bg-gradient-to-r from-indigo-950/50 via-slate-900 to-indigo-950/50 border border-indigo-500/30 p-3.5 rounded-2xl flex flex-wrap justify-between items-center gap-2 text-xs">
          <div class="flex items-center gap-2.5">
            <div class="w-8 h-8 rounded-xl bg-indigo-500/20 text-indigo-300 flex items-center justify-center font-black">
              <i data-lucide="car" class="w-4 h-4"></i>
            </div>
            <div>
              <span class="font-extrabold text-white">${b.driver_name || ''}</span>
              <span class="text-indigo-300 ml-2">ðŸ“ž ${b.driver_phone || ''}</span>
              <span class="text-amber-300 font-mono ml-2 font-black">ðŸš– ${b.vehicle_number || 'GA-03-T-1234'}</span>
            </div>
          </div>
          <div class="flex items-center gap-2">
            <a href="tel:${b.driver_phone}" class="bg-slate-800 hover:bg-slate-700 text-white font-bold px-3 py-1.5 rounded-xl flex items-center gap-1">
              <i data-lucide="phone" class="w-3.5 h-3.5 text-amber-400"></i> Call Driver
            </a>
            <a href="https://wa.me/${cleanDigits(b.driver_phone)}" target="_blank" class="bg-emerald-600 hover:bg-emerald-500 text-slate-950 font-black px-3 py-1.5 rounded-xl flex items-center gap-1 shadow">
              <i data-lucide="send" class="w-3.5 h-3.5"></i> WhatsApp
            </a>
          </div>
        </div>
      `;
    }

    let quickButtons = '';
    if (isPending) {
      quickButtons = `
        <button onclick="openAssignModal(${b.id})" class="gradient-btn-gold text-slate-950 font-black text-xs px-3.5 py-2 rounded-xl flex items-center gap-1.5 shadow-lg">
          <i data-lucide="user-plus" class="w-4 h-4"></i> ðŸš€ Dispatch Driver
        </button>
      `;
    } else if (isAssigned) {
      quickButtons = `
        <button onclick="updateBookingStatus(${b.id}, 'IN_TRANSIT')" class="bg-yellow-500 hover:bg-yellow-400 text-slate-950 font-black text-xs px-3.5 py-2 rounded-xl flex items-center gap-1.5 shadow-md">
          <i data-lucide="play" class="w-4 h-4"></i> ðŸš– Start Ride (On Trip)
        </button>
        <button onclick="openAssignModal(${b.id})" class="bg-slate-800 hover:bg-slate-700 text-slate-300 font-bold text-xs px-3 py-2 rounded-xl flex items-center gap-1">
          <i data-lucide="user-check" class="w-3.5 h-3.5 text-amber-400"></i> Re-assign
        </button>
        <button onclick="updateBookingStatus(${b.id}, 'PENDING')" class="bg-slate-900 hover:bg-slate-800 border border-slate-700 text-amber-300 font-bold text-xs px-2.5 py-2 rounded-xl">
          â†©ï¸ Reset
        </button>
      `;
    } else if (isInTransit) {
      quickButtons = `
        <button onclick="updateBookingStatus(${b.id}, 'COMPLETED')" class="bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-black text-xs px-4 py-2 rounded-xl flex items-center gap-1.5 shadow-md">
          <i data-lucide="check-circle" class="w-4 h-4"></i> ðŸ Complete Ride
        </button>
      `;
    } else if (isCancelled) {
      quickButtons = `
        <button onclick="updateBookingStatus(${b.id}, 'PENDING')" class="bg-amber-500/20 hover:bg-amber-500/30 border border-amber-400 text-amber-300 font-black text-xs px-3.5 py-2 rounded-xl flex items-center gap-1">
          <i data-lucide="rotate-ccw" class="w-3.5 h-3.5"></i> ðŸ”„ Re-Open Ride
        </button>
      `;
    }

    const editButtons = (!isDone && !isCancelled) ? `
      <button onclick="openEditFareModal(${b.id})" class="bg-slate-900 hover:bg-amber-400/20 border border-amber-500/30 text-amber-300 font-extrabold text-xs px-3 py-2 rounded-xl flex items-center gap-1">
        <i data-lucide="zap" class="w-3.5 h-3.5"></i> Boost / Fare
      </button>
      <button onclick="openEditBookingModal(${b.id})" class="bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-300 font-bold text-xs px-3 py-2 rounded-xl flex items-center gap-1">
        <i data-lucide="edit-3" class="w-3.5 h-3.5 text-indigo-400"></i> Edit
      </button>
    ` : '';

    const cancelBtn = (!isDone && !isCancelled) ? `
      <button onclick="handleCancelBookingPrompt(${b.id})" class="text-xs font-bold text-red-400 hover:text-red-300 hover:bg-red-500/10 px-3 py-2 rounded-xl border border-red-500/20 transition flex items-center gap-1">
        <i data-lucide="x-circle" class="w-3.5 h-3.5"></i> Cancel
      </button>
    ` : '';

    return `
      <div class="booking-card uber-card p-5 sm:p-6 rounded-3xl border ${cardBorder} space-y-4 shadow-xl transition relative overflow-hidden" 
           id="booking-card-${b.id}"
           data-status="${unifiedKey}" 
           data-search="${searchString}">

        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-800/80 pb-3.5">
          <div class="space-y-1">
            <div class="flex flex-wrap items-center gap-2">
              <span class="text-lg font-black text-white font-outfit tracking-wide">#${b.booking_ref}</span>
              <span class="bg-amber-400/10 text-amber-300 border border-amber-400/30 text-xs font-black px-2.5 py-0.5 rounded-lg uppercase">
                ${b.cab_type}
              </span>
              <span class="text-[10px] bg-slate-800 text-slate-300 font-bold px-2 py-0.5 rounded-md uppercase">
                ${b.trip_type}
              </span>
              ${hasBoost ? `<span class="text-[10px] bg-amber-500/20 text-amber-300 font-black px-2 py-0.5 rounded-md border border-amber-500/40 flex items-center gap-1 animate-pulse">âš¡ Fare Boosted</span>` : ''}
              <span class="text-[10px] text-slate-500 font-bold">${b.created_at || ''}</span>
            </div>
            <div class="text-xs font-bold text-slate-300 flex flex-wrap items-center gap-2 pt-0.5">
              <span>ðŸ‘¤ ${b.customer_name}</span>
              <span>â€¢</span>
              <span>ðŸ“ž <a href="tel:${b.customer_phone}" class="text-amber-400 hover:underline">${b.customer_phone}</a></span>
              <a href="https://wa.me/${cleanDigits(b.customer_phone)}" target="_blank" class="text-emerald-400 hover:underline flex items-center gap-1 text-[11px] bg-emerald-500/10 px-2 py-0.5 rounded-md border border-emerald-500/20">
                <i data-lucide="message-circle" class="w-3 h-3"></i> WhatsApp Passenger
              </a>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <div class="text-right">
              <span class="text-2xl font-black text-amber-400 font-outfit block">â‚¹${parseFloat(b.total_fare || 0)}</span>
              <span class="text-[10px] text-slate-400 uppercase font-bold tracking-wider">Fixed Fare</span>
            </div>
            <span class="text-xs font-black uppercase px-3 py-1.5 rounded-xl border ${statusBadgeClass}">
              ${statusUpper.replace(/_/g, ' ')}
            </span>
          </div>
        </div>

        <div class="bg-slate-950/70 p-4 rounded-2xl border border-slate-800/80 grid grid-cols-1 md:grid-cols-2 gap-3 text-xs">
          <div class="space-y-1">
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-amber-400"></span>
              <span class="text-slate-400 font-bold">Pickup:</span>
              <span class="text-white font-extrabold">${b.pickup_location}</span>
            </div>
            <div class="flex items-center gap-2">
              <span class="w-2.5 h-2.5 rounded-full bg-emerald-400"></span>
              <span class="text-slate-400 font-bold">Destination:</span>
              <span class="text-white font-extrabold">${b.drop_location}</span>
            </div>
          </div>
          <div class="space-y-1 md:text-right">
            <div class="text-slate-300 font-bold">
              ðŸ“… Schedule: <span class="text-amber-300 font-black">${b.pickup_date} at ${b.pickup_time || ''}</span>
            </div>
            ${b.special_notes ? `<div class="text-[11px] text-amber-400 font-semibold italic bg-amber-400/10 p-1.5 rounded-lg inline-block border border-amber-400/20 text-left">ðŸ“ ${b.special_notes}</div>` : ''}
          </div>
        </div>

        ${driverCard}

        <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-slate-800/80">
          <div class="flex flex-wrap items-center gap-2">
            ${quickButtons}
            ${editButtons}
            <button onclick="openReceiptModal(${b.id})" class="bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-400 hover:text-white font-bold text-xs px-2.5 py-2 rounded-xl flex items-center gap-1" title="Print Slip">
              <i data-lucide="printer" class="w-3.5 h-3.5"></i>
            </button>
            ${cancelBtn}
          </div>

          <div class="flex items-center gap-2">
            <span class="text-[10px] text-slate-400 font-bold uppercase hidden sm:inline">Status:</span>
            <select onchange="handleStatusChange(${b.id}, this.value, '${currentFilter}')" class="bg-slate-900 border border-slate-700 text-slate-200 text-xs font-bold px-3 py-2 rounded-xl focus:outline-none focus:border-amber-400">
              <option value="">-- Set Status --</option>
              <option value="PENDING" ${isPending ? 'selected' : ''}>PENDING (Needs Driver)</option>
              <option value="CONFIRMED" ${isAssigned ? 'selected' : ''}>CONFIRMED (Assigned)</option>
              <option value="IN_TRANSIT" ${isInTransit ? 'selected' : ''}>IN_TRANSIT (On Trip)</option>
              <option value="COMPLETED" ${isDone ? 'selected' : ''}>COMPLETED (Finished)</option>
              <option value="CANCELLED_BY_ADMIN" ${isCancelled ? 'selected' : ''}>CANCELLED</option>
            </select>
          </div>
        </div>
      </div>
    `;
  }

  async function fetchLiveUpdates() {
    if (isModalActive) return; // Never disrupt active modal

    try {
      const _t = Date.now();
      const [resBookings, resStats] = await Promise.all([
        fetch('../api_dashboard.php?action=bookings&_t=' + _t),
        fetch('../api_dashboard.php?action=stats&_t=' + _t)
      ]);
      const bookingsData = await resBookings.json();
      const statsData = await resStats.json();

      if (Array.isArray(bookingsData)) {
        globalBookingsCache = bookingsData;
        const currentHash = JSON.stringify(bookingsData.map(x => ({ id: x.id, s: x.status, f: x.total_fare, d: x.driver_id, n: x.special_notes, dn: x.driver_name, dp: x.driver_phone, vn: x.vehicle_number })));
        
        if (!lastKnownDataHash || lastKnownDataHash !== currentHash) {
          if (lastKnownDataHash && lastKnownDataHash !== currentHash) {
            const banner = document.getElementById('live-alert-banner');
            if (banner) {
              banner.classList.remove('hidden');
              setTimeout(() => banner.classList.add('hidden'), 4000);
            }
            try {
              const audio = new Audio('https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3');
              audio.play().catch(()=>{});
            } catch(e) {}
          }

          const container = document.getElementById('bookings-feed-container');
          if (container) {
            container.innerHTML = bookingsData.map(renderBookingHTML).join('');
            filterBookings(currentFilter);
            if (typeof lucide !== 'undefined') lucide.createIcons();
          }

          if (document.getElementById('fleet-grid-container')) {
            refreshDriversLive();
          }
        }
        lastKnownDataHash = currentHash;

        if (statsData) {
          const setEl = (id, val) => { const el = document.getElementById(id); if (el) el.innerText = val; };
          setEl('stat-total', statsData.total || 0);
          setEl('stat-pending', statsData.pending || 0);
          setEl('stat-assigned', statsData.assigned || 0);
          setEl('stat-intransit', statsData.inTransit || 0);
          setEl('stat-completed', statsData.completed || 0);
          setEl('stat-cancelled', statsData.cancelledTotal || 0);
          setEl('stat-revenue', 'â‚¹' + Number(statsData.totalRevenue || 0).toLocaleString());

          setEl('count-ALL', statsData.total || 0);
          setEl('count-PENDING', statsData.pending || 0);
          setEl('count-CONFIRMED', statsData.assigned || 0);
          setEl('count-IN_TRANSIT', statsData.inTransit || 0);
          setEl('count-COMPLETED', statsData.completed || 0);
          setEl('count-CANCELLED', statsData.cancelledTotal || 0);
        }
      }
    } catch (e) {}
  }

  function triggerManualSync() {
    lastKnownDataHash = '';
    fetchLiveUpdates();
  }

  document.addEventListener('DOMContentLoaded', () => {
    const urlFilter = new URLSearchParams(window.location.search).get('filter');
    if (urlFilter && ['ALL', 'PENDING', 'CONFIRMED', 'IN_TRANSIT', 'COMPLETED', 'CANCELLED'].includes(urlFilter)) {
      currentFilter = urlFilter;
    }
    filterBookings(currentFilter);
    highlightDeskTabs(currentFilter);
    if (typeof lucide !== 'undefined') lucide.createIcons();
    setInterval(fetchLiveUpdates, 2000);

    // Escape Key Modal Dismissal
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const modals = ['assign-modal', 'edit-fare-modal', 'edit-booking-modal', 'manual-booking-modal', 'fleet-modal', 'receipt-modal', 'wa-config-modal', 'fcm-config-modal'];
        modals.forEach(m => closeModal(m));
      }
    });

    // Backdrop Click Dismissal
    const modalIds = ['assign-modal', 'edit-fare-modal', 'edit-booking-modal', 'manual-booking-modal', 'fleet-modal', 'receipt-modal', 'wa-config-modal', 'fcm-config-modal'];
    modalIds.forEach(mId => {
      const modalEl = document.getElementById(mId);
      if (modalEl) {
        modalEl.addEventListener('click', (e) => {
          if (e.target === modalEl) closeModal(mId);
        });
      }
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
        const storedToken = token || '';
        if (storedToken) {
          try {
            if (navigator.sendBeacon) {
              const blob = new Blob([JSON.stringify({ fcm_token: storedToken, is_online: 0 })], { type: 'application/json' });
              navigator.sendBeacon('/app/api.php?action=heartbeat', blob);
            }
          } catch(e){}
        }
        const href = link.getAttribute('href');
        const sep = href.includes('?') ? '&' : '?';
        window.location.href = href + sep + 'fcm_token=' + encodeURIComponent(token);
      }
    });
  });

  function showBrowserNotification(title, body, eventType) {
    const FCM_TONES = {
      'NEW_BOOKING':     { url: 'https://assets.mixkit.co/active_storage/sfx/2771/2771-preview.mp3', vibrate: [200, 50, 200, 50, 300] },
      'DRIVER_ASSIGNED': { url: 'https://assets.mixkit.co/active_storage/sfx/2020/2020-preview.mp3', vibrate: [100, 50, 100, 50, 200, 50, 200] },
      'DRIVER_ACCEPTED': { url: 'https://assets.mixkit.co/active_storage/sfx/2000/2000-preview.mp3', vibrate: [100, 30, 100, 30, 100, 30, 300] },
      'RIDE_STARTED':    { url: 'https://assets.mixkit.co/active_storage/sfx/1999/1999-preview.mp3', vibrate: [300, 100, 300, 100, 400] },
      'IN_TRANSIT':      { url: 'https://assets.mixkit.co/active_storage/sfx/1999/1999-preview.mp3', vibrate: [300, 100, 300, 100, 400] },
      'RIDE_COMPLETED':  { url: 'https://assets.mixkit.co/active_storage/sfx/2770/2770-preview.mp3', vibrate: [50, 30, 50, 30, 200, 50, 200] },
      'CANCELLED':       { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [400, 100, 400] },
      'FARE_BOOSTED':    { url: 'https://assets.mixkit.co/active_storage/sfx/2770/2770-preview.mp3', vibrate: [50, 30, 50, 30, 50, 30, 200] },
      'default':         { url: 'https://assets.mixkit.co/active_storage/sfx/2869/2869-preview.mp3', vibrate: [300, 100, 300, 100, 400] }
    };
    const tone = FCM_TONES[eventType] || FCM_TONES['default'];

    try {
      const audio = new Audio(tone.url);
      audio.volume = 1.0;
      audio.play().catch(()=>{});
    } catch(e) {}

    if ('Notification' in window && Notification.permission === 'granted') {
      try {
        if (globalFcmReg && globalFcmReg.showNotification) {
          globalFcmReg.showNotification(title, {
            body: body,
            icon: 'https://pavancab.com/app/logo-pavancab.png',
            badge: 'https://pavancab.com/app/logo-pavancab.png',
            vibrate: tone.vibrate,
            tag: 'pavancab-tower-' + Date.now()
          });
        } else {
          new Notification(title, { body: body, icon: 'https://pavancab.com/app/logo-pavancab.png' });
        }
      } catch (e) {
        try { new Notification(title, { body: body, icon: 'https://pavancab.com/app/logo-pavancab.png' }); } catch(err){}
      }
    }

    const banner = document.getElementById('live-alert-banner');
    if (banner) {
      banner.innerHTML = `<span>ðŸ”” <strong>${title}:</strong> ${body}</span>`;
      banner.classList.remove('hidden');
      setTimeout(() => banner.classList.add('hidden'), 8000);
    }
  }
  </script>

  <!-- FIREBASE CLOUD MESSAGING CLIENT SDK (DASHBOARD TOWER) -->
  <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-app-compat.js"></script>
  <script src="https://www.gstatic.com/firebasejs/10.12.0/firebase-messaging-compat.js"></script>

  <script>
    const firebaseConfig = {
      apiKey: "AIzaSyA9_ZfLPnMew12a0g4EtikIne2jn7nEIkY",
      authDomain: "pavancab-4a1daa.firebaseapp.com",
      projectId: "pavancab-4a1daa",
      storageBucket: "pavancab-4a1daa.firebasestorage.app",
      messagingSenderId: "1064014558872",
      appId: "1:1064014558872:web:bb0b2d5ae8e4e79a0ac2ed"
    };

    const VAPID_KEY = "BBiDdmppgS12OFBx7KLwtb4eD7B2R57ESg5hLTXH384DUWwdoMzyAqhl2RyPWf_hVsSnZXt5uetIHhSOgyfccjA";

    window.fcmTokenStored = window.fcmTokenStored || localStorage.getItem('pavancab_fcm_token') || null;
    let globalFcmMessaging = null;
    let globalFcmReg = null;

    async function initDashboardFcm() {
      try {
        if (!('serviceWorker' in navigator) || !('Notification' in window) || typeof firebase === 'undefined') return;

        if (!firebase.apps.length) {
          firebase.initializeApp(firebaseConfig);
        }
        const messaging = firebase.messaging();
        globalFcmMessaging = messaging;

        const reg = await navigator.serviceWorker.register('/app/firebase-messaging-sw.js', { scope: '/app/' });
        globalFcmReg = reg;

        // If stored token exists, sync immediately on load
        if (window.fcmTokenStored) {
          const adminPhone = "<?php echo cleanPhoneDigits($user['mobile'] ?? $user['phone'] ?? '8199000000'); ?>";
          const adminEmail = "<?php echo htmlspecialchars($user['email'] ?? 'admin@pavancab-demo.local'); ?>";
          const adminRole  = "<?php echo htmlspecialchars($user['role'] ?? 'admin'); ?>";
          fetch('../api_dashboard.php?action=save_fcm_token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'save_fcm_token',
              fcm_token: window.fcmTokenStored,
              user_mobile: adminPhone,
              user_email: adminEmail,
              role: adminRole
            })
          }).catch(()=>{});
        }

        if (Notification.permission === 'granted') {
          fetchAndSaveDashboardFcmToken(messaging, reg);
        } else if (Notification.permission === 'default') {
          try {
            const perm = await Notification.requestPermission();
            if (perm === 'granted') {
              fetchAndSaveDashboardFcmToken(messaging, reg);
            }
          } catch(e) {
            const onFirstInteraction = async () => {
              try {
                const perm2 = await Notification.requestPermission();
                if (perm2 === 'granted') {
                  fetchAndSaveDashboardFcmToken(messaging, reg);
                }
              } catch (e2) {}
            };
            document.addEventListener('click', onFirstInteraction, { once: true });
          }
        }

        // Foreground Message Handlers (Both Firebase SDK + Native Service Worker Channel)
        messaging.onMessage((payload) => {
          console.log('[Dashboard Tower] Foreground push received:', payload);
          const title = payload.notification?.title || payload.data?.title || 'ðŸš• PAVANCAB Dispatch Alert';
          const body  = payload.notification?.body || payload.data?.body || 'New ride or booking update received!';
          const evtType = payload.data?.event_type || payload.data?.status || 'default';
          showBrowserNotification(title, body, evtType);
          triggerManualSync();
        });

        if ('serviceWorker' in navigator) {
          navigator.serviceWorker.addEventListener('message', (event) => {
            console.log('[Dashboard Tower] Message from SW:', event.data);
            if (event.data && (event.data.type === 'FCM_PUSH_RECEIVED' || event.data.title)) {
              const title = event.data.title || 'ðŸš• PAVANCAB Dispatch Alert';
              const body  = event.data.body  || 'New ride or booking update received!';
              const evtType = event.data.event_type || 'default';
              showBrowserNotification(title, body, evtType);
              triggerManualSync();
            }
          });
        }

        // Periodic background re-sync
        setInterval(() => {
          if (Notification.permission === 'granted') {
            fetchAndSaveDashboardFcmToken(messaging, reg);
          }
        }, 180000); // 3 mins

      } catch (err) {
        console.warn('Dashboard FCM Push Init warning:', err);
      }
    }

    async function fetchAndSaveDashboardFcmToken(messaging, reg) {
      try {
        if (!messaging) messaging = globalFcmMessaging;
        if (!reg) reg = globalFcmReg;
        if (!messaging || !reg) return;

        const token = await messaging.getToken({
          vapidKey: VAPID_KEY,
          serviceWorkerRegistration: reg
        });

        if (token) {
          window.fcmTokenStored = token;
          try { localStorage.setItem('pavancab_fcm_token', token); } catch(e){}
          console.log('[Dashboard FCM] Token synchronized:', token.substring(0, 20) + '...');

          const adminPhone = "<?php echo cleanPhoneDigits($user['mobile'] ?? $user['phone'] ?? '8199000000'); ?>";
          const adminEmail = "<?php echo htmlspecialchars($user['email'] ?? 'admin@pavancab-demo.local'); ?>";
          const adminRole  = "<?php echo htmlspecialchars($user['role'] ?? 'admin'); ?>";

          await fetch('../api_dashboard.php?action=save_fcm_token', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
              action: 'save_fcm_token',
              fcm_token: token,
              user_mobile: adminPhone,
              user_email: adminEmail,
              role: adminRole
            })
          });
        }
      } catch (err) {
        console.warn('Dashboard FCM Token sync error:', err);
      }
    }

    document.addEventListener('visibilitychange', () => {
      if (document.visibilityState === 'visible' && Notification.permission === 'granted') {
        fetchAndSaveDashboardFcmToken(globalFcmMessaging, globalFcmReg);
      }
    });

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
      initDashboardFcm();
    } else {
      document.addEventListener('DOMContentLoaded', initDashboardFcm);
    }
  </script>
</body>
</html>
