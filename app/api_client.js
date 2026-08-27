/* =====================================================
   PAVANCAB API CLIENT - Pure JS HTTP Layer
   All backend communication goes through this module.
   ===================================================== */

const API = {
  _baseUrl: (function() {
    var p = window.location.pathname;
    if (p.indexOf('/dashboard/') !== -1) return '../';
    return '';
  })(),

  async _fetch(url, opts = {}) {
    const cfg = {
      credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', ...opts.headers },
      ...opts
    };
    if (opts.body && typeof opts.body === 'object' && !(opts.body instanceof FormData)) {
      cfg.body = JSON.stringify(opts.body);
    }
    const res = await fetch(this._baseUrl + url, cfg);
    const text = await res.text();
    try { return JSON.parse(text); } catch (e) { return { error: text || 'Parse error' }; }
  },

  get(url) { return this._fetch(url); },
  post(url, body) { return this._fetch(url, { method: 'POST', body }); },
  put(url, body) { return this._fetch(url, { method: 'PUT', body }); },

  // Auth
  me() { return this.get('auth.php?action=me'); },
  sendOTP(phone) { return this.post('auth.php?action=send_otp', { phone }); },
  verifyOTP(phone, otp, name, email, fcm_token) {
    return this.post('auth.php?action=verify_otp', { phone, otp, name: name || '', email: email || '', fcm_token: fcm_token || '' });
  },
  logout(fcm_token) { return this.post('auth.php?action=logout', { fcm_token }); },

  // Pickups / Drops / Fares
  pickups(type) { return this.get('pickups.php?type=' + (type || 'all')); },
  drops(pickupId) { return this.get('drops.php?pickup_id=' + pickupId); },
  hourlyFares(placeId) { return this.get('hourly.php?place_id=' + placeId); },
  tourPackages(placeId) { return this.get('tours.php?place_id=' + placeId); },
  tourList() { return this.get('tours.php?place_id=0'); },

  // Bookings
  createBooking(data) { return this.post('bookings.php', data); },
  myBookings() { return this.get('api_rides.php?action=user-bookings'); },
  cancelBooking(bookingId) { return this.post('api_rides.php?action=cancel-booking', { booking_id: bookingId }); },
  boostFare(bookingId, boostAmount) { return this.post('api_rides.php?action=boost-fare', { booking_id: bookingId, boost_amount: boostAmount }); },
  rateRide(bookingId, rating, review) { return this.post('api_rides.php?action=rate-ride', { booking_id: bookingId, rating, review_text: review }); },

  // Dashboard
  dashBookings() { return this.get('api_dashboard.php?action=bookings'); },
  dashStats() { return this.get('api_dashboard.php?action=stats'); },
  dashDrivers() { return this.get('api_dashboard.php?action=drivers'); },
  dashUsers() { return this.get('api_dashboard.php?action=users'); },
  dashUserDetail(params) { return this.get('api_dashboard.php?action=user-detail&' + params); },
  dashTeam() { return this.get('api_dashboard.php?action=team'); },
  dashReports() { return this.get('api_dashboard.php?action=reports'); },

  // Dashboard Mutations
  dashAssignDriver(data) { return this.post('api_dashboard.php?action=assign-driver', data); },
  dashUpdateStatus(data) { return this.post('api_dashboard.php?action=update-status', data); },
  dashEditFare(data) { return this.post('api_dashboard.php?action=edit-fare', data); },
  dashEditBooking(data) { return this.post('api_dashboard.php?action=edit-booking', data); },
  dashCreateBooking(data) { return this.post('api_dashboard.php?action=create-booking', data); },
  dashAddDriver(data) { return this.post('api_dashboard.php?action=add-driver', data); },
  dashToggleDriver(id) { return this.post('api_dashboard.php?action=toggle-driver-status', { driver_id: id }); },
  dashDeleteDriver(id) { return this.post('api_dashboard.php?action=delete-driver', { driver_id: id }); },
  dashUpdateReport(data) { return this.post('api_dashboard.php?action=update-report', data); },
  dashTeamAdd(data) { return this.post('api_dashboard.php?action=team', data); },
  dashTeamRemove(id) { return this.post('api_dashboard.php?action=team-remove', { member_id: id }); },
  dashSendPush(data) { return this.post('api_dashboard.php?action=send-custom-fcm', data); },

  // FCM Config
  dashFCMConfig() { return this.get('api_dashboard.php?action=fcm-config'); },
  dashSaveFCMConfig(data) { return this.post('api_dashboard.php?action=fcm-config', data); },
  dashFCMTest(data) { return this.post('api_dashboard.php?action=fcm-test', data); },

  // WhatsApp Config
  dashWAConfig() { return this.get('api_dashboard.php?action=whatsapp-config'); },
  dashSaveWAConfig(data) { return this.post('api_dashboard.php?action=whatsapp-config', data); },

  // Public APIs
  saveFCMToken(data) { return this.post('api.php?action=save_fcm_token', data); },
  heartbeat(data) { return this.post('api.php?action=heartbeat', data); },
  reportIssue(data) { return this.post('api.php?action=submit_ride_report', data); }
};
