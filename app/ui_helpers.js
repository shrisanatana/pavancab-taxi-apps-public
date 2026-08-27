/* =====================================================
   PAVANCAB UI HELPERS - Shared DOM utilities
   ===================================================== */

const UI = {
  $(sel, ctx) { return (ctx || document).querySelector(sel); },
  $$(sel, ctx) { return [...(ctx || document).querySelectorAll(sel)]; },

  show(id) { const el = document.getElementById(id); if (el) el.classList.remove('hidden'); },
  hide(id) { const el = document.getElementById(id); if (el) el.classList.add('hidden'); },
  toggle(id) { const el = document.getElementById(id); if (el) el.classList.toggle('hidden'); },

  toast(msg, type = 'info') {
    const t = document.createElement('div');
    const colors = { success: 'bg-emerald-500', error: 'bg-red-500', info: 'bg-blue-500', warning: 'bg-amber-500' };
    t.className = `fixed top-4 right-4 z-[999] px-5 py-3 rounded-xl text-sm font-bold text-white shadow-2xl animate-fadeIn ${colors[type] || colors.info}`;
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => { t.style.opacity = '0'; t.style.transition = 'opacity .3s'; }, 2500);
    setTimeout(() => t.remove(), 3000);
  },

  escapeHtml(str) {
    if (!str) return '';
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
  },

  formatCurrency(n) {
    return '₹' + Number(n || 0).toLocaleString('en-IN');
  },

  formatDate(d) {
    if (!d) return '';
    const months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
    const p = d.split('-');
    if (p.length === 3) return parseInt(p[2]) + ' ' + months[parseInt(p[1]) - 1] + ' ' + p[0];
    return d;
  },

  formatTime(t) {
    if (!t) return '';
    const p = t.split(':');
    const h = parseInt(p[0]);
    const ampm = h >= 12 ? 'PM' : 'AM';
    const h12 = h % 12 || 12;
    return h12 + ':' + (p[1] || '00') + ' ' + ampm;
  },

  formatDateTime(dt) {
    if (!dt) return '';
    const parts = dt.split(' ');
    return this.formatDate(parts[0]) + ' ' + this.formatTime(parts[1]);
  },

  timeAgo(dt) {
    if (!dt) return '';
    const diff = (Date.now() - new Date(dt).getTime()) / 1000;
    if (diff < 60) return 'Just now';
    if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
    if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
    return Math.floor(diff / 86400) + 'd ago';
  },

  statusColor(s) {
    const map = {
      'PENDING': 'bg-amber-500/20 text-amber-300 border-amber-500/30',
      'CONFIRMED': 'bg-blue-500/20 text-blue-300 border-blue-500/30',
      'ASSIGNED': 'bg-blue-500/20 text-blue-300 border-blue-500/30',
      'IN_TRANSIT': 'bg-purple-500/20 text-purple-300 border-purple-500/30',
      'ON_TRIP': 'bg-purple-500/20 text-purple-300 border-purple-500/30',
      'ARRIVED': 'bg-indigo-500/20 text-indigo-300 border-indigo-500/30',
      'COMPLETED': 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30',
      'CANCELLED_BY_USER': 'bg-red-500/20 text-red-300 border-red-500/30',
      'CANCELLED_BY_ADMIN': 'bg-red-500/20 text-red-300 border-red-500/30',
      'CANCELLED': 'bg-red-500/20 text-red-300 border-red-500/30',
      'REJECTED': 'bg-red-500/20 text-red-300 border-red-500/30'
    };
    return map[s] || 'bg-slate-500/20 text-slate-300 border-slate-500/30';
  },

  statusLabel(s) {
    const map = {
      'PENDING': '⏳ Pending', 'CONFIRMED': '✅ Confirmed', 'ASSIGNED': '👤 Assigned',
      'IN_TRANSIT': '🚗 In Transit', 'ON_TRIP': '🚗 On Trip', 'ARRIVED': '📍 Arrived',
      'COMPLETED': '🏁 Completed', 'CANCELLED_BY_USER': '❌ Cancelled', 'CANCELLED_BY_ADMIN': '❌ Cancelled',
      'CANCELLED': '❌ Cancelled', 'REJECTED': '🚫 Rejected'
    };
    return map[s] || s;
  },

  driverStatusColor(s) {
    const st = String(s).toLowerCase();
    if (st === 'available') return 'bg-emerald-500/20 text-emerald-400';
    if (st === 'on_trip') return 'bg-amber-500/20 text-amber-400';
    return 'bg-red-500/20 text-red-400';
  },

  stars(rating, interactive, onRate) {
    let html = '';
    const val = Math.round(rating || 0);
    for (let i = 1; i <= 5; i++) {
      if (interactive) {
        html += `<button type="button" data-star="${i}" class="star-btn text-lg transition-transform hover:scale-125">${i <= val ? '★' : '☆'}</button>`;
      } else {
        html += `<span class="${i <= val ? 'text-amber-400' : 'text-slate-600'}">★</span>`;
      }
    }
    return html;
  }
};
